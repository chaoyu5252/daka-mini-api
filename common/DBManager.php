<?php
namespace Fichat\Common;

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

include_once '../fichat_header.php';

use Fichat\Models\AssociationLevel;
use Fichat\Models\AssociationMemberTitle;
use Fichat\Models\AssociationTag;
use Fichat\Models\BalanceFlow;
use Fichat\Models\BestID;
use Fichat\Models\BestNumber;
use Fichat\Models\ExchangeKaMiRecord;
use Fichat\Models\Gift;
use Fichat\Models\GiftRecord;
use Fichat\Models\PetTreatment;
use Fichat\Models\RedPacket;
use Fichat\Models\RedPacketRecord;
use Fichat\Models\RewardTask;
use Fichat\Models\RewardTaskRecord;
use Fichat\Models\SystemConfig;
use Fichat\Models\SystemDyn;
use Fichat\Models\SystemHot;
use Fichat\Models\Tag;
use Fichat\Models\User;
use Fichat\Models\Account;
use Fichat\Models\LoginToken;
use Fichat\Models\SignToken;
use Fichat\Models\UserAttr;
use Fichat\Models\UserGrabRedPacket;
use Fichat\Models\UserRelationPerm;
use Fichat\Models\UserMsg;
use Fichat\Models\UserOrder;
use Fichat\Models\Friend;
use Fichat\Models\Association;
use Fichat\Models\AssociationMember;
use Fichat\Models\Attention;
use Fichat\Models\Moments;
use Fichat\Models\MomentsReply;
use Fichat\Models\MomentsReplyLike;
use Fichat\Models\MomentsLike;
use Fichat\Models\FriendRequest;
use Fichat\Models\Title;
use Fichat\Models\Cluster;
use Fichat\Models\ClusterMember;
use Fichat\Models\AssociationRequest;
use Fichat\Models\MomentsGive;
use Fichat\Models\UserTag;
use Fichat\Proxy\BaiduPushProxy;

use Fichat\Utils\KaException;
use Fichat\Utils\MessageSender;
use Fichat\Utils\KakaPay;
use Fichat\Utils\OssApi;
use Fichat\Utils\RedisClient;
use Fichat\Utils\RedpackDist;
use Fichat\Utils\Utils;
use Fichat\Proxy\WxPayProxy;
use Fichat\Proxy\AlipayProxy;
use Phalcon\Cache\Backend\Redis;
use Phalcon\Di;
use Phalcon\Forms\Manager;
use Phalcon\Mvc\Micro;
use Phalcon\Db\Column;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Transaction;
use Phalcon\Mvc\Model\Transaction\Manager as TcManager;
use Phalcon\Mvc\Model\Transaction\Failed as TcFailed;



class DBManager {
	
	// 检查当日任务数量
	public static function checkDayTaskTimes($uid)
	{
		$now = time();
		$dayBeginTime = $now - ($now % 86400);
		$dayEndTime = $dayBeginTime + 86400;
		$records = RewardTaskRecord::find([
			"conditions" => "uid = ".$uid." AND create_time >= ".$dayBeginTime." AND create_time <=".$dayEndTime,
			"columns" => "distinct(id)"
		]);
		$count = 0;
		if ($records) {
			$count = count($records -> toArray());
		}
		if ($count == 5) {
			return false;
		}
		return true;
	}
	
	// 保存流水
	
	// accountId获取用户信息
	public static function getUserByAccountId($accountId) {
		return User::findFirst("account_id = $accountId");
	}
	
	// id获取account
	public static function getAccountById($accountId) {
		return Account::findFirst("id = $accountId");
	}
	
	// 检查任务各项参数
	public static function checkTaskParams($di, $user)
	{
		// 检查余额
		$needMoney = $_POST['task_money'] ? $_POST['task_money'] : 0;
		if ($user->balance < $needMoney) {
			return ReturnMessageManager::buildReturnMessage(ERROR_MONEY);
		}
		
		// 检查手续费
		$fee = $_POST['fee'] ? floatval($_POST['fee']) : 0;
		$needMoney = $needMoney - $fee;
		
		// 点击单价
		$clickPrice = $_POST['click_price'] ? $_POST['click_price'] : 0;
		if ($clickPrice < 0.01) {
			return ReturnMessageManager::buildReturnMessage(ERROR_TASK_CLICK_PRICE);
		}
		
		// 点击赏金分数
		$clickCount = $_POST['click_count'] ? intval($_POST['click_count']) : 0;
		
		if($clickCount < 50) {
			return ReturnMessageManager::buildReturnMessage(ERROR_TASK_CLICK_COUNT_LESS);
		}
		
		// 检查分享份数
		$shareCount = $_POST['share_count'] ? intval($_POST['share_count']) : 0;
		if ($shareCount < 20) {
			return ReturnMessageManager::buildReturnMessage(ERROR_TASK_SHARE_COUNT_LESS);
		}
		
		if ($shareCount > 100) {
			return ReturnMessageManager::buildReturnMessage(ERROR_TASK_SHARE_COUNT_MORE);
		}
		
		// 分享参与人数
		$shareJoinType = $_POST['share_join_count'] ? intval($_POST['share_join_count']) : 0;
		$shareJoinCount = 50;
		$minSharePrice = 25.00;
		switch ($shareJoinType)
		{
			case 1:
				$shareJoinCount = 10;
				$minSharePrice = 2.50;
				break;
			case 2:
				$shareJoinCount = 20;
				$minSharePrice =6.00;
				break;
			case 3:
				$shareJoinCount = 30;
				$minSharePrice = 9.00;
				break;
			case 4:
				$shareJoinCount = 40;
				$minSharePrice = 15.00;
				break;
			default:
				$shareJoinCount = 50;
				$minSharePrice = 25.00;
		}
		
		// 检查分享奖励金额
		$sharePrice = $_POST['share_price'] ? floatval($_POST['share_price']) : 0;
		if ($sharePrice < $minSharePrice) {
			return ReturnMessageManager::buildReturnMessage(ERROR_TASK_SHARE_PRICE);
		}
		
		// 检查点击和分享的总金额是否大于任务的总金额
		$sumCost = $clickPrice * $clickCount + $sharePrice * $shareCount;
		if ($sumCost > $needMoney) {
			return ReturnMessageManager::buildReturnMessage(ERROR_TASK_CLICK_AND_SHARE_SUM_MORE);
		}
		
		$content = trim($_POST['task_desp']);
		// 检查发送的相关信息
		if (!Utils::checkMsg($di, $content)) {
			return ReturnMessageManager::buildReturnMessage(ERROR_TASK_DESP_UNLAW);
		}
		
		// 返回任务信息
		return [
			'click_price' => $clickPrice,
			'click_count' => $clickCount,
			'share_count' => $shareCount,
			'share_join_count' => $shareJoinCount,
			'share_price' => $sharePrice,
			'task_money' => $needMoney,
			'task_desp' => $content,
			'task_cover' => $_POST['task_cover'] ? intval($_POST['task_cover']) : 0,
			'order_amount' => $needMoney + $fee
		];
	}
	
	// username获取用户
	public static function getUserByUsername($username) {
		if(strlen($username) > 11){
			return Account::findFirst("uid = '$username'");
		}
		
		return Account::findFirst("phone = '$username'");
	}
	
	// 获取全部称号
	public static function getTitleList() {
		return Title::find();
	}
	
	// 批量验证用户是否存在
	public static function checkUsers($idList) {
		foreach($idList as $id){
			$user = User::findFirst($id);
			if(!$user){
				return false;
			}
		}
		
		return true;
	}
	
	public static function test0()
	{
		KaException::throwErrCode('E0121');
	}
	
	// 插入更新登录token
	public static function saveToken($di, $token, $user){
		$transaction = Utils::getDiTransaction($di);
		$loginToken = LoginToken::findFirst("user_id = $user->id");
		if(!$loginToken){
			$loginToken = new LoginToken();
			$loginToken->setTransaction($transaction);
			$loginToken->user_id = $user->id;
			$loginToken->token = $token;
		}else{
			$loginToken->setTransaction($transaction);
			$loginToken->token = $token;
		}
		if (!$loginToken->save()){
//			Utils::echo_debug('3335');
			$transaction -> rollback();
		}
		return true;
	}
	
	// 删除用户token
	public static function delUserToken($userId) {
		$loginToken = LoginToken::findFirst("user_id = $userId");
		if($loginToken){
			$loginToken->delete();
		}
		return true;
	}
	
	// 创建帐号
	public static function createAccount($di, $uid, $openId, $phone, $password){
		// 事务
		$transaction = Utils::getDiTransaction($di);
		$account= new Account();
		$account->setTransaction($transaction);
		$accountIds = self::getAllAccount();
		while (true) {
            $aid = Utils::getRandInt(6);
            if(!in_array($aid, $accountIds)) {
                break;
            }
        }
		$accountData=array(
		        'id' => $aid,
				'uid' => $uid,
				'openid' => $openId,
				'phone' => $phone ? $phone : '',
				'password' => $password,
				'pay_password' => '',
				'status' => 1,
				'create_time' => date('Y-m-d H:i:s'),
		);
		$account->assign($accountData);
		if(!$account->save()){
			$transaction->rollback();
		}
		return $account;
	}

	// 获取用户的数量
	public static function getUserCount() {
	    return User::count();
    }

	// 获取所有的账号ID
	public static function getAllAccount() {
        $accountIds = Account::find(array("columns" => "id"));
        return $accountIds->toArray();
    }

	// 添加、更新用户手机号
	public static function updateUserPhone($account, $user, $phone) {
		$account->phone = $phone;
		if(!$account->save()){ throw new \RuntimeException(__METHOD__.$account->getMessages()[0]); }
		$user->phone = $phone;
		if(!$user->save()){ throw new \RuntimeException(__METHOD__.$user->getMessages()[0]); }
		return $user;
	}

	// 创建用户
	public static function createUser($di, $params){
		// 事务
		$transaction = Utils::getDiTransaction($di);
//		$redis = Utils::getDiRedis($di);
		// 用户头像
		if(!$params['user_avatar']){
			$params['user_avatar'] = $params['wx_avatar'] ? $params['user_oss_avatar'] : "https://dakaapp-avatar.oss-cn-beijing.aliyuncs.com/default_user_avatar.png";
		}
		// 微信头像
		if(!$params['wx_avatar']) {
			$params['wx_avatar'] = '';
		}
		$user= new User();
		$user->setTransaction($transaction);
		$userData = array(
			'account_id' => $params['account_id'],
			'phone' => $params['phone'] ? $params['phone'] : '',
			'nickname' => $params['nickname'],
			'gender' => $params['gender'] ? $params['gender'] : 1,
			'signature' => $params['introd'],
			'user_avatar' => $params['user_avatar'],
			'wx_avatar' => $params['wx_avatar'],
			'channel' => $params['channel'],
			'platform' => $params['platform'],
			'title_id' => 1,
			'level' => 1,
			'exp' => 0,
			'balance' => 0,
			'verify' => 1,
			'birthday' => $params['birthday']
		);
		$user->assign($userData);
		if (!$user->save()) {
			$transaction->rollback();
		}
		return $user;
	}
	
	// curl_get
	public static function curl_get($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		curl_close($ch);
		
		return $res;
	}


	// 查询关注信息
	public static function checkAttention($userId, $targetId) {
		return Attention::findFirst("user_id = $userId AND target_id = $targetId");
	}
	

	// 插入关注信息
	public static function insertAttentionData($di, $user, $target) {
		try {
			$userId = $user->id;
			$targetId = $target->id;
			// 创建事物
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			// 查看是否查看关系
			$userRelationPerm = self::updateUserRelationByAdd($transaction, $userId, $targetId, 2);
			$isLook = $userRelationPerm->is_look;
			// 初始化关注
			$attention = new Attention();
			$attention->setTransaction($transaction);
			// 更新关注字段只
			$attention->user_id = $userId;
			$attention->target_id = $targetId;
			$attention->confirm = 0;
			$attention->is_look = $isLook;
			$attention->forbid_look = 1;
			$attention->is_new = 1;
			$attention->create_time = date('Y-m-d H:i:s',time());
			// 保存
			if(!$attention->save()){
				$transaction->rollback();
			}
			// 提交事物
			return $attention;
		} catch (TcFaild $e) {
			return false;
		}
	}
	
	// 插入多条关注信息
	public static function insertMultAttentions($di, $user, $targetUsers) {
		try {
			$userId = $user->id;
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			$newFans = [];
			foreach ($targetUsers as $targetUser) {
				// 关注的ID
				$targetId = $targetUser->id;
				
//				// 检查是否已经关注
				$attention = DBManager::checkAttention($userId, $targetId);
//				// 如果没有关注, 则为用户增加一条关注关系
				if(!$attention){
					// 插入关注信息
					$attention = self::insertAttentionData($di, $user, $targetUser);;
					// 检查目标用户是否关注你
					$targetAttention = DBManager::checkAttention($targetId, $userId);
					if ($targetAttention) {
						if(!DBManager::updateAttentionData($di, $attention, $targetAttention, 2) ) {
							$transaction->rollback();
						}
					}
					// 发送关注信息
					if (!MessageSender::sendUserNewFans($di, $user, $targetUser)) {
						$transaction->rollback();
					}
					array_push($newFans, $targetUser);
				}
			}
			return $newFans;
		} catch (TcFailed $e) {
			return false;
		}
	}
	
	// 更新关注状态
	public static function updateAttentionData($di, $attention, $targetAttention, $type) {
		try {
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			if($type == 1){
				$attention->setTransaction($transaction);
				$targetAttention->setTransaction($transaction);
				$attention->confirm = 1;
				$targetAttention->confirm = 1;
				if(!$attention->save()){
					$transaction->rollback();
				}
				if(!$targetAttention->save()){
					$transaction->rollback();
				}
			}else if($type == 2){
				$targetAttention->setTransaction($transaction);
				$targetAttention->confirm = 0;
				if(!$targetAttention->save()){
					$transaction->rollback();
				}
			}
			return true;
		} catch (TcFailed $e) {
			return false;
		}
	}
	
	// 取消关注
	public static function delAttention($di, $attention, $targetAttention) {
		try {
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			$attention ->setTransaction($transaction);
			// 更新用户说说查看状态
			self::updateUserRelationByRemove($transaction, $attention->user_id, $attention->target_id, 2);
			// 删除关系
			if (!$attention->delete()) {
				$transaction->rollback();
			}
			// 检查目标关注是否存在
			return true;
		} catch (TcFailed $e) {
			return false;
		}
	}
	
	// 获取关注列表
	public static function getAttentionList($userId, $type) {
		if($type == 1){
			return Attention::find("user_id = $userId");
		}else if($type == 2){
			return Attention::find("target_id = $userId");
		}
	}
	
	// 获取用户间的查看说说关系
	public static function getUserRelationPerm($userId, $targetId) {
		return UserRelationPerm::findFirst("user_id=".$userId." AND target_id=".$targetId);
	}
	
	// userid查询公会
	public static function getUserAssociation($userId){
		return AssociationMember::findFirst("member_id = $userId AND type = 1");
	}

    // userid查询家族
    public static function getUserAssociations($userId){
        return AssociationMember::find("member_id = $userId AND type = 1");
    }
    
    // userid查询家族(所有)
	public static function getUserAllAssociations($uid)
	{
		return AssociationMember::find("member_id = ".$uid);
	}
	
	// 环信guoupId获取公会信息
	public static function getAssociationByGroupId($groupId) {
		return Association::findFirst("group_id = $groupId");
	}

	// 获取所有家族、群聊的ID
	public static function getAllAssocIds() {
        $assocIds = Association::find(array("columns" => "assoc_id"));
        return $assocIds->toArray();
    }

	// 验证用户群聊、公会是否存在
	public static function checkUserAssociation($userId, $associationId) {
		return AssociationMember::findFirst("member_id = $userId AND association_id = $associationId");
	}
	
	// 随机获取10个公会
	public static function randomAssociationList($associationList) {
	    $myAssocIds = '';
        foreach ($associationList as $key => $association) {
//            var_dump($association->toArray());
            $id = $association->association_id;
            if ($myAssocIds) {
                $myAssocIds .= ','.$id;
            } else {
                $myAssocIds .= $id;
            }
//            array_push($data, (int)$id);
        }
        if ($myAssocIds) {
            $result =Association::find(["type = 1 AND id not in (".$myAssocIds.")",
                "limit" => 10,
                "order" => "rand()",
            ]);
        } else {
            $result = Association::find([
                "type = 1",
                "order" => "rand()",
                "limit" => 10
            ]);
        }
	    return $result;
	}
	
	// 更新公会头像
	public static function updateAssociationAvatar($association, $assoc_avatar, $assoc_thumb = '') {
		$association->assoc_avatar = $assoc_avatar ? $assoc_avatar : $association->assoc_avatar;
		$association->assoc_thumb = $assoc_thumb ? $assoc_thumb : $association->assoc_thumb;
		if(!$association->save()){ throw new \RuntimeException(__METHOD__.$association->getMessages()[0]); }
		return $association;
	}
	
	// 获取该用户全部公会申请记录
	public static function getUserAllApplyAssociation($userId, $associationList) {
		$apply = array();
		foreach($associationList as $key => $association){
			$applyAssociation = self::getUserApplyAssociation($userId, $association->id);
			$apply[$key] = $applyAssociation;
		}
		
		return $apply;
	}
	
	// 获取用户申请记录
	public static function getUserApplyAssociation($userId, $associationId) {
		return AssociationRequest::findFirst("association_id = $associationId AND user_id = $userId AND status != 1");
	}
	
	// 获取用户申请指定公会记录
	public static function getUserApplyAssociationRecord($userId, $associationId) {
		return AssociationRequest::find("user_id = $userId AND association_id = $associationId");
	}
	
	// 名称查找公会
	public static function getAssociationByName($name) {
		return Association::findFirst("nickname = '$name' AND type = 1");
	}
	
	// 创建公会
	public static function createAssociation($redis, $userId, $name, $groupId, $number, $type, $maxNumber, $assoc_avatar = '', $assoc_thumb = '', $info = null, $confirm = null) {
		$assocIds = self::getAllAssocIds();
	    while (true) {
		    $assocId = Utils::getRandInt(8);
		    if(!in_array($assocId, $assocIds)) {
                break;
            }
        }
	    $association = new Association();
        $association->assoc_id = $assocId;
        $association->owner_id = $userId;
        $association->group_id = $groupId;
		$association->nickname = $name;
		$association->level = 1;
		$association->info = $info !== null ? $info : ' ';
		$association->assoc_avatar = $assoc_avatar ? $assoc_avatar : 'https://dakaapp-avatar.oss-cn-beijing.aliyuncs.com/default_group_avatar.png';
		$association->assoc_thumb = $assoc_thumb ? $assoc_thumb : '';
		$association->type = $type;
		$association->open = 2;
		$association->confirm = $confirm;
		$association->current_number = $number;
		$association->max_number = $maxNumber;
		$association->create_time = date('Y-m-d H:i:s');
		$association->speak_mode = 0;
        $association->speak_time_interval = 0;
		if(!$association->save()){ throw new \RuntimeException(__METHOD__.$association->getMessages()[0]); }
        // 将群组推入排行榜
        if ($type == 1) {
            RedisManager::pushAssocLevToRank($redis, $groupId, 1, 0);
        }
		return $association;
	}
	
	// 添加公会成员
	public static function addAssociationMember($userId, $nickname, $associationId, $type) {
		$associationMember = new AssociationMember();
		$associationMember->association_id = $associationId;
		$associationMember->member_id = $userId;
		$associationMember->nickname = $nickname ? $nickname : '';
		if($type == 1){
			$associationMember->perm = FAMILY_PERM_OWNER;
			$associationMember->user_type = 1;
		}else if($type == 2){
			$associationMember->perm = FAMILY_PERM_MEMBER;
			$associationMember->user_type = 3;
		}
		$associationMember->type = 1;
		$associationMember->add_time = date('Y-m-d H:i:s');
		if(!$associationMember->save()){ throw new \RuntimeException(__METHOD__.$associationMember->getMessages()[0]); }
		
		return $associationMember;
	}
	
	// 申请加入公会
	public static function applyAddAssociation($di, $user, $association, $adminIds, $message) {
		$associationRequest = new AssociationRequest();
		$associationRequest->user_id = $user->id;
		$associationRequest->inviter_id = 0;
		$associationRequest->association_id = $association->id;
		$associationRequest->status = 0;
		$associationRequest->message = $message;
		$associationRequest->is_new = 1;
        $associationRequest->create_time = date('Y-m-d H:i:s');
		if(!$associationRequest->save()){ throw new \RuntimeException(__METHOD__.$associationRequest->getMessages()[0]); }
		
		// 发送加入公会的消息
		// MessageSender::sendApplyAssociation($di, $user, $association, $adminIds, $message);
		
		return $associationRequest;
	}

    /**
     * 更新申请家族记录
     *
     * @param $applyRecord
     * @param $message
     */
    public static function updateApplyAssociation($applyRecord, $message)
    {
        $applyRecord->message = $message;
        if (!$applyRecord->save()) {
            throw new \RuntimeException(__METHOD__ . $applyRecord->getMessages()[0]);
        }
        return $applyRecord;

    }
	// 验证用户是否公会管理层
	public static function checkAssociationUserType($userId, $associationId) {
		return AssociationMember::findFirst("member_id = $userId AND association_id = $associationId AND (user_type = 1 OR user_type = 2)");
	}
	
	// 是否申请此公会
	public static function isApplyAssociation($userId, $associationId) {
		return AssociationRequest::find("association_id = $associationId AND user_id = $userId AND (status = 0 OR status = 2)");
	}
	
	// 获取公会所有管理层
	public static function getAssociationAdminList($associationId) {
		return AssociationMember::find("association_id = $associationId AND user_type != 3");
	}
	
	// 邀请加入公会
	private static function invitAddAssociation($userId, $inviterId, $associationId, $message) {
		$associationRequest = new AssociationRequest();
		$associationRequest->user_id = $userId;
		$associationRequest->inviter_id = $inviterId;
		$associationRequest->association_id = $associationId;
		$associationRequest->status = 1;
		$associationRequest->message = $message;
		$associationRequest->is_new = 1;
        $associationRequest->create_time = date('Y-m-d H:i:s');
		if(!$associationRequest->save()){ throw new \RuntimeException(__METHOD__.$associationRequest->getMessages()[0]); }
		
		return $associationRequest;
	}
	
	// 批量邀请加入公会
	public static function batchInvitAddAssociation($idList, $userId, $association) {
		foreach($idList as $id){
			$message = '邀您加入家族<' . $association->nickname . '>';
			self::invitAddAssociation($id, $userId, $association->id, $message);
		}
		
		return true;
	}

    /**
     * 批量加入群聊
     *
     * @params $idList
     * @params $groupChat
     */
    public static function InviteGroupChat($idList, $groupChat)
    {
        foreach ($idList as $userId) {
            $data['association_id'] = $groupChat->id;
            $data['member_id'] = $userId;
            $data['nickname'] = $groupChat->nickname;
            $data['user_type'] = 3;
            $data['type'] = $groupChat->type;
            $data['add_time'] = date('Y-m-d H:i:s');
            $groupChatMember = new AssociationMember();
            $groupChatMember->assign($data);
            if (!$groupChatMember->save()) {
                throw new \RuntimeException(__METHOD__ . $groupChatMember->getMessages()[0]);
            }
        }
        return true;
	}
	
	// 删除申请记录
	public static function delApplyAssociationRecord($applyRecord) {
		foreach ($applyRecord as $apply){
			$apply->delete();
		}
		
		return true;
	}
	
	// 确认添加成员
	public static function confirmAddAssociation($applyRecord) {
        $applyRecord->status = 2;
        $applyRecord->save();
		return true;
	}
	
	// 删除用户全部申请公会记录
	public static function delMemberApplyRecord($userId) {
		$list = AssociationRequest::find("user_id = $userId AND (status = 0 OR status = 2)");
		foreach($list as $apply){
			$apply->delete();
		}
		
		return true;
	}
	
	// 获取申请加入公会成员列表
	public static function getApplyAddAssociation($associationId, $pageIndex = 1) {
        if ($pageIndex == 1) {
            $data = AssociationRequest::find(array(
                "inviter_id = 0 and association_id = ".$associationId,
                "order" => "create_time DESC",
                "limit" => PAGE_SIZE
            ));
        } else {
            $startPos = ($pageIndex - 1) * PAGE_SIZE;
            $data = AssociationRequest::find(array(
                "inviter_id = 0 and association_id = ".$associationId." ORDER BY create_time DESC LIMIT ".$startPos.",".PAGE_SIZE
            ));
        }
        return $data;
	}

    /**
     * 获取申请加入家族列表
     *
     * @param $userId
     * @param $associationList
     * @return mixed
     */
    public static function getApplyAddAssociationMember($userId, $associationList, $pageIndex)
    {
        $members = [];
        foreach ($associationList as $key => $association) {
            $isAdmin = self::checkAssociationUserType($userId, $association->association_id);
            if ($isAdmin) {
                $members[$key] = self::getApplyAddAssociation($association->association_id, $pageIndex);
            }
        }
        return $members;
	}
	
	// 获取公会所有成员
	public static function getAssociationMember($di, $associationId, $pageIndex = 1) {
    	$startPos = ($pageIndex - 1) * 50;
		$sql = "SELECT * FROM Fichat\Models\AssociationMember WHERE association_id = ".$associationId." ORDER BY user_type ASC, level DESC, exp DESC LIMIT ".$startPos.", 50";
		$query = new Query($sql, $di);
		return $query->execute();
	}
	
	// 获取公会申请、邀请记录
	public static function getAssociationApplyRecord($associationId) {
		return AssociationRequest::find("association_id = $associationId");
	}
	
	// 解散公会
	public static function dissolveAssociation($association, $members, $applyRecord) {
		// 清除成员
		foreach($members as $member){
			$member->delete();
		}
		
		// 清除申请、邀请记录
		foreach($applyRecord as $apply){
			$apply->delete();
		}
		
		// 清除公会
		$association->delete();
		
		return true;
	}
	
	// 更新公会成员昵称
	public static function updateMemberNickname($userAssociation, $nickname) {
		$userAssociation->nickname = $nickname;
		if(!$userAssociation->save()){ throw new \RuntimeException(__METHOD__.$userAssociation->getMessages()[0]); }
		
		return $userAssociation;
	}
	
	// 更新公会信息
	public static function updateAssociationInfo($di, $association, $nickname, $bulletin, $open, $confirm, $info) {
		$association->nickname = $nickname !== null ? $nickname : $association->nickname;
		$association->bulletin = $bulletin !== null ? $bulletin : $association->bulletin;
		$association->open = $open !== null ? $open : $association->open;
		$association->info = $info !== null ? $info : $association->info;
		// 如果验证值是正确的, 更新验证的值
		if ($confirm!==null) {
			$association->confirm = $confirm;
		}
//		if ($bulletin!==null) {
//			$hxConfig = $di->get('config')['hxConfig'];
//			HxChatProxy::sendGroupMessages('admin', $association->group_id, $bulletin, $hxConfig);
//		}
		if(!$association->save()){
			Utils::throwDbException($association);
		}
		return $association;
	}
	
	// 更新公会人数
	public static function updateAssociation($association, $type) {
		if($type == 1){
			$association->current_number += 1;
		}else if($type == 2){
			$association->current_number -= 1;
		}
		
		if(!$association->save()){ throw new \RuntimeException(__METHOD__.$association->getMessages()[0]); }
		
		return $association;
	}
	
	// 用户id公会id获取公会
	public static function getAssociationMemberByUserId($userId, $associationId) {
		return AssociationMember::findFirst("member_id = $userId AND association_id = $associationId AND type = 1");
	}

    // 确认是否是群聊成员
    public static function checkGroupChatMemberById($userId, $groupChatId) {
        return AssociationMember::findFirst("member_id = $userId AND association_id = $groupChatId AND type = 2");
    }

	// 踢出公会
	public static function tickAssociation($di, $target, $association, $userAssociation) {
    	try {
    		$transaction = $di->getShared(SERVICE_TRANSACTION);
    	    if (!$userAssociation->delete()) {
		        $transaction->rollback();
	        }
	        // 发送透传消息
	        MessageSender::sendUserTickFromAssociation($di, $target, $association);
            return true;
	    } catch (TcFailed $e) {
    		return false;
	    }
	}
	
	public static function quitAssociation($userAssociation) {
		return $userAssociation->delete();
	}
	
	// 增减管理员
	public static function addDelAssociationAdmin($userAssociation, $type) {
		if($type == 1){
			$userAssociation->user_type = 2;
		}else if($type == 2){
			$userAssociation->user_type = 3;
		}
		
		if(!$userAssociation->save()){ throw new \RuntimeException(__METHOD__.$userAssociation->getMessages()[0]); }
		
		return $userAssociation;
	}
	
	// 获取公会所有管理员
	public static function getAllAssociationAdmin($associationId) {
		return AssociationMember::find("association_id = $associationId AND user_type = 2");
	}
	
	// 搜索公会
	public static function searchAssociation($condition) {
		return Association::findFirst("(assoc_id = '$condition' AND type = 1) OR (nickname = '$condition' AND type = 1)");
	}

	// 搜索家族成员
	public static function searchAssociationMember($di, $condition, $associationId) {
        $phql = "select a.* from Fichat\Models\User u,Fichat\Models\AssociationMember a where u.id = a.member_id and association_id = $associationId and (u.nickname like '%$condition%' or account_id = '$condition')";
        $query = new Query($phql, $di);
        $result = $query->execute();
        return $result;
    }
	
	// 更新公会会长
	public static function updateAssociationMaster($association, $userAssociation, $targetAssociation) {
		$association->owner_id = $targetAssociation->member_id;
		if(!$association->save()){ throw new \RuntimeException(__METHOD__.$association->getMessages()[0]); }
		
		$userAssociation->user_type = 3;
		if(!$userAssociation->save()){ throw new \RuntimeException(__METHOD__.$userAssociation->getMessages()[0]); }
		
		$targetAssociation->user_type = 1;
		if(!$targetAssociation->save()){ throw new \RuntimeException(__METHOD__.$targetAssociation->getMessages()[0]); }
		
		return true;
	}
	
	// 获取已存在公会中用户id集合
	public static function getExistAssociationMemberId($associationId) {
		$data = array();
		$i = 0;
		$members = AssociationMember::find("association_id = $associationId");
		foreach($members as $member){
			$data[$i] = $member->member_id;
			$i++;
		}
		
		return $data;
	}
	
	// 获取已存在公会申请中用户id集合
	public static function getExistApplyAssociationMemberId($associationId) {
		$data = array();
		$i = 0;
		$members = AssociationRequest::find("association_id = $associationId");
		foreach($members as $member){
			$data[$i] = $member->user_id;
			$i++;
		}
	
		return $data;
	}

	// 获取已经达到家族数量上限的用户id集合
	public static function getReachAssociationLevelLimit($userIds) {
        if(count($userIds) > 0) {
            foreach ($userIds as $idx => $value) {
                $user = self::getUserById($value);
                $checkResult = self::checkUserLevelLimit($user->id, $user->level, 2);
                if(!$checkResult) {
                    unset($userIds[$idx]);
                }
            }
        }
        return $userIds;
    }

    /**
     * 提升公会等级
     *
     * @params $user
     * @params $amount
     *
     */
    public static function changeAssocLevel($redis, $association, $amount)
    {
        // 计算家族总经验
        $totalExp = $amount + $association->exp;
        // 获取家族等级经验信息
        $assocLevel = self::getAssocLevel($association->level);
        $maxAssocLevel = $assocLevel[count($assocLevel) - 1]['level'];
        if ($association->level < $maxAssocLevel) {
            foreach ($assocLevel as $key => $value) {
                if ($totalExp >= $value['exp']) {
                    if ($association->level < $value['level']) {
                        $association->level += 1;
                    }
                } else {
                	break;
                }
            }
        } else {
            $totalExp = $assocLevel[count($assocLevel) - 1]['exp'];
        }
        $association->exp = $totalExp;
        // 推送新的等级经验到用户等级排行中
        RedisManager::pushAssocLevToRank($redis, $association->group_id, $association->level, $association->exp);
        if (!$association->save()) {
            throw new \RuntimeException(__METHOD__.$association->getMessages()[0]);
        }
        return true;
    }
    
    // 获取家族当前等级的信息
    public static function getAssocLevel($level)
    {
        return AssociationLevel::find([
            "conditions" => "level >= $level",
            "columns" => "level, exp"
        ])->toArray();
    }
	
	// 获取家族所有悬赏任务余额
	public static function getAssocRewardTaskSumBalance($groupId)
	{
		$result = RewardTask::findFirst([
			"status = 1 AND group_id = ".$groupId,
			"columns" => "sum(balance) as sum_balance"
		]);
		if ($result) {
			return $result->sum_balance ? $result->sum_balance : 0;
		} else {
			return 0;
		}
	}
    
    /**
     * 获取推荐公会
     *
     */
    public static function getUserRecommandFaimlies($di)
    {
    	// 获取当前正在进行中的任务, 按余额金额进行排序
	    $phpl = "SELECT group_id, SUM(balance) as sum_balance FROM Fichat\Models\RewardTask WHERE status = 1 AND parent_id > 0 AND type = 1 ".
			    "AND parent_id > 0 AND type = 1 GROUP BY group_id ORDER BY balance DESC LIMIT 0, 20";
	    $query = new Query($phpl, $di);
	    $rewardTaskResult = $query->execute();
	    
	    if ($rewardTaskResult) {
		    $rewardTaskResult = $rewardTaskResult->toArray();
		    $groupIds = '';
		    $assocTaskBalanceData = array();
		    foreach($rewardTaskResult as $rewardTaskData) {
		    	$groupId = $rewardTaskData['group_id'];
		        if ($groupIds == '') {
		            $groupIds = $groupId;
		        } else {
		            $groupIds .= ','.$groupId;
		        }
			    $assocTaskBalanceData[$groupId] = $rewardTaskData['sum_balance'];
		    }
		    if ($groupIds) {
		    	$phpl = "SELECT a.*, count(am.id) as member_count, al.member_limit FROM Fichat\Models\Association as a, ".
				        "Fichat\Models\AssociationMember as am , Fichat\Models\AssociationLevel as al ".
						"WHERE a.id = am.association_id AND a.level = al.level AND a.group_id in (".$groupIds.") ".
						"GROUP BY a.id";
		    	$assocQuery = new Query($phpl, $di);
			    $assocResult = $assocQuery->execute();
		        if ($assocResult) {
			        return ReturnMessageManager::buildRcmdAssociationList($assocResult, $assocTaskBalanceData);
		        } else {
		        	return [];
		        }
		    } else {
		    	return [];
		    }
	    } else {
	    	return [];
	    }
    }

	
	// 获取积分排行
	public static function getScoreRankingList($limit) {
		if($limit){
			return User::find(array('order' => 'score DESC', 'limit' => $limit));
		}else{
			return User::find(array('order' => 'score DESC'));
		}
	}
	
	// 获取战力排行
	public static function getCombatPowerRankingList($limit) {
		if($limit){
			return User::find(array('order' => 'combat_power DESC', 'limit' => $limit));
		}else{
			return User::find(array('order' => 'combat_power DESC'));
		}
	}
	
	// 获取我的排行
	public static function getMyRanking($rankingList, $userId) {
		foreach($rankingList as $key => $value){
			if($value->id == $userId){
				return $key + 1;
			}
		}
	}
	
	// 根据member_id获取所有需要添加群组的用户信息TODO
	public static function getMemberList($idList) {
		$data = array();
		$i = 0;
		foreach($idList as $id){
			$data[$i] = User::findFirst($id);
			$i++;
		}
		
		return $data;
	}
	
	// 拼接群聊名称
	public static function jointClusterName($userList) {
		$count = count($userList);
		$name = '';
		for($i = 0; $i < $count; $i++){
			$name .= $userList[$i]->nickname . '、';
		}
		$name = mb_substr($name, 0, 6, 'utf-8');
		$name = self::mb_trim($name, '、');
		$name = $name . "...";
		
		return $name;
	}
	
	// 解决trim中文乱码
	private static function mb_trim($string, $trim_chars = '\s'){
		return preg_replace('/^['.$trim_chars.']*(?U)(.*)['.$trim_chars.']*$/u', '\\1',$string);
	}
	
	// 获取成员usernameList
	public static function getMemberUsernameList($members) {
		$names = array();
		$i = 0;
		foreach($members as $member){
			$names[$i] = $member->member_id;
			$i++;
		}
		
		return $names;
	}
	
	// 根据权限获取管理员ID
	public static function getAdminsByPerm($admins, $opPermId) {
		$permAdmins = [];
		foreach ($admins as $admin) {
			$perm = (int)$admin->perm[$opPermId];
			if ($perm) {
				array_push($permAdmins, $admin->member_id);
			}
		}
		return $permAdmins;
	}
	
	// 批量添加成员
	public static function batchAddClusterMember($association, $memberList) {
		$addTime = date('Y-m-d H:i:s');
		$data = array();
		$i = 0;
		foreach($memberList as $member){
			$associationMember = new AssociationMember();
			$associationMember->association_id = $association->id;
			$associationMember->member_id = $member->id;
			$associationMember->nickname = $member->nickname;
			if($association->owner_id == $member->id){
				$associationMember->user_type = 1;
			}else{
				$associationMember->user_type = 3;
			}
			$associationMember->type = $association->type;
			$associationMember->add_time = $addTime;
			$associationMember->save();
			$data[$i] = $associationMember;
			$i++;
		}
		
		return $data;
	}
	
	// 批量验证用户是否在群聊中
	public static function batchCheckClusterMember($clusterId, $idList) {
		foreach($idList as $id){
			$clusterMember = AssociationMember::findFirst("association_id = $clusterId AND member_id = $id");
			if($clusterMember){
				return false;
			}
		}
		
		return true;
	}
	
	// 检查用户是否是群组成员
	public static function existAssociationMember($assocId, $uid)
	{
		if (AssociationMember::findFirst("association_id = ".$assocId." AND member_id = ".$uid)){
			return true;
		}
		return false;
	}
	
	// 更新群组成员数
	public static function updateClusterNumber($cluster, $number) {
		if($number){
			$cluster->current_number += $number;
		}else{
			$cluster->current_number -= 1;
		}
		if(!$cluster->save()){ throw new \RuntimeException(__METHOD__.$cluster->getMessages()[0]); }
		
		return $cluster;
	}
	
	// 获取新群主信息
	public static function getClusterMemberByAscSort($clusterId, $userId) {
		$newAdmin =  AssociationMember::find(array(
				"association_id = $clusterId AND member_id != $userId",
				'order' => 'nickname ASC',
				'limit' => 1,
		));
		
		return $newAdmin[0];
	}

	// 获取所有的靓号
	public static function getAllBestID($type) {
        $displayIds = BestNumber::find(array(
            "conditions" => "type = $type",
            "columns" => "display_id"
        ));
        return $displayIds->toArray();
    }
	
	// 指定新群主
	public static function assignNewAdmin($cluster, $clusterMember) {
		$cluster->owner_id = $clusterMember->member_id;
		if(!$cluster->save()){ throw new \RuntimeException(__METHOD__.$cluster->getMessages()[0]); }
		
		$clusterMember->user_type= 1;
		if(!$clusterMember->save()){ throw new \RuntimeException(__METHOD__.$clusterMember->getMessages()[0]); }
		
		return $clusterMember;
	}
	
	// 环信群id获取群信息
	public static function getClusterByGroupId($groupId) {
		return Association::findFirst("group_id = '$groupId'");
	}

	//根据群id获取群信息
    public static function getAssociationById($id) {
        return Association::findFirst("id = $id");
    }


    // 获取群聊全部成员
	public static function getClusterMembers($clusterId) {
		return AssociationMember::find(array(
				"association_id = $clusterId",
				'order' => 'user_type ASC',
		));
	}

	// 获取用户在群组中的身份
    public static function getGroupUserType($assoc_index_id, $userId) {
        $clusterMembers = DBManager::getClusterMembers($assoc_index_id);
        $clusterMembers = $clusterMembers->toArray();
        $userType = 4;
        foreach ($clusterMembers as $value) {
            if ($value['member_id'] == $userId)
            {
                $userType = $value['user_type'];
                break;
            }
        }
        return $userType;
    }
	
	// 获取群组已存在成员
	public static function getExistedMemberIdList($clusterId) {
		$members = AssociationMember::find("association_id = $clusterId");
		$data = array();
		$i = 0;
		foreach($members as $member){
			$data[$i] = $member->member_id;
			$i++;
		}
		
		return $data;
	}
	
	public static function saveSignToken($di, $code, $phone, $type){
		$signToken = new SignToken();
		// 短信码有效时间
		$expire = Utils::smsCodeExpire($di);
		$signToken->code = $code;
		$signToken->phone = $phone;
		$signToken->type = $type;
		$signToken->time = time() + $expire;
		if ($signToken->save()) {
			return $signToken;
		} else {
			throw new \RuntimeException(__METHOD__.$signToken->getMessages()[0]);
		}
	}
	
	public static function vaildSignToken($phone, $vCode, $type)
	{
		$data = SignToken::findFirst([
			"phone = ".$phone." AND code = ".$vCode." AND type = ".$type
		]);
		if ($data) {
			$now = time();
			if ($now > $data->time) {
				return false;
			} else {
				return $data->toArray();
			}
		} else {
			return false;
		}
	}

	// 根据条件搜索用户
	public static function searchUser($condition) {
        return User::find("account_id = '$condition' or phone = '$condition' or nickname like '%$condition%'");
    }

	// 根据userId获取user表数据
	public static function getUserById($userId) {
		$user = User::findFirst("id = '$userId'");
		return $user;
	}
	
	// 批量获取用户username
	public static function batchGetUsernameById($idList) {
		$usernameList = array();
		$i = 0;
		foreach($idList as $id){
			$user = User::findFirst("id = $id");
			$usernameList[$i] = $user->id;
			$i++;
		}
		
		return $usernameList;
	}
	
	// 获取用户所有的消息
	public static function getUserMsgList($uid, $lastMsgId) {
        if($lastMsgId == 0) {
            $msgList = UserMsg::find([
                "user_id = $uid",
                "order" => "update_time desc",
                "limit" => PAGE_SIZE
            ]);
        } else {
            $msgList = UserMsg::find([
                "user_id = $uid and id < $lastMsgId",
                "order" => "update_time desc",
                "limit" => PAGE_SIZE
            ]);
        }

		if ($msgList) {
			return $msgList;
		} else {
			return false;
		}
	}
	
	// 更新消息状态
	public static function updateUserMsgStatus($di, $userMsgs)
	{
		$msgIds = '';
		foreach ($userMsgs as $userMsg) {
			if ($msgIds) {
				$msgIds .= ','.$userMsg->id;
			} else {
				$msgIds = $userMsg->id;
			}
		}
		$phpl = 'UPDATE Fichat\Models\UserMsg SET status = 0 WHERE id in('.$msgIds.')';
		$query = new Query($phpl, $di);
		// 执行更新
		$query->execute();
	}
	
	// 获取家族当前等级的信息
	public static function getUserCurLevel($level)
	{
		return UserAttr::findFirst([
			"conditions" => "level = $level",
			"columns" => "level, exp, atten_num"
		])->toArray();
	}

	// 获取大于等于当前等级的用户属性
    public static function getUserAttrMoreThanCurrentLevel($level)
    {
        $userAttrList = UserAttr::find(array(
            "level >= $level"
        ));
        return $userAttrList->toArray();
    }

	// 获取用户所有红包余额
	public static function getUserRedPacketSumBalance($uid)
	{
		$result = RedPacket::findFirst([
			"status = 0 AND invalid = 0 AND user_id = ".$uid,
			"columns" => "sum(balance) as sum_balance"
		]);
		if ($result) {
			return $result->sum_balance ? $result->sum_balance : 0;
		} else {
			return 0;
		}
	}
	
//	// 创建用户角标
//	public static function createBadge($userId) {
//		$badge = new Badge();
//		$badge->user_id = $userId;
//		$badge->funs = 0;
//		$badge->friend = 0;
//		if(!$badge->save()){ throw new \RuntimeException(__METHOD__.$badge->getMessages()[0]); }
//
//		return $badge;
//	}
//
//	// 获取用户角标
//	public static function getUserBadge($userId) {
//		return Badge::findFirst("user_id = $userId");
//	}
//
//	// 修改用户角标,1:粉丝,3:好友申请
//	public static function updateUserBadge($badge, $type){
//		if($type == 1){
//			$badge->funs += 1;
//		}else if($type == 3){
//			$badge->friend += 1;
//		}
//
//		if(!$badge->save()){ throw new \RuntimeException(__METHOD__.$badge->getMessages()[0]); }
//
//		return $badge;
//	}
//
//	// 批量添加角标
//	public static function batchAddBadge($users) {
//		foreach($users as $user){
//			$badge = self::getUserBadge($user->member_id);
//			self::updateUserBadge($badge, 3);
//		}
//
//		return true;
//	}
//
//	// userId批量添加角标
//	public static function batchAddBadgeByUserId($idList) {
//		foreach($idList as $id){
//			$badge = self::getUserBadge($id);
//			self::updateUserBadge($badge, 3);
//		}
//
//		return true;
//	}
//
//	// 清除用户角标
//	public static function clearUserBadge($badge, $type){
//		if($type == 1){
//			$badge->funs = 0;
//		}else if($type == 3){
//			$badge->friend = 0;
//		}
//
//		if(!$badge->save()){ throw new \RuntimeException(__METHOD__.$badge->getMessages()[0]); }
//
//		return $badge;
//	}
	
	// 根据手机号获取user表数据
	public static function getUserByPhone($phone) {
		$account = Account::findFirst("phone = '$phone'");
		
		if(!$account){ return false; }
		
		return User::findFirst("account_id = $account->id");
	}
	
	// 检测是否为好友
	public static function checkFriend($userId, $friendId) {
		$friend = Friend::findFirst(array(
				"conditions" => "(user_id = $userId AND friend_id = $friendId)
				OR (user_id = $friendId AND friend_id = $userId)"
		));
		if ($friend) return true;
		else return false;
	}
	
	// 检测是否为好友
	public static function isFriend($userId, $friendId) {
		$friend =  Friend::findFirst("user_id = $userId AND friend_id = $friendId AND confirm = 1");
		return $friend ? true : false;
	}
	
	// 检测不是申请的好友关系
	public static function isFriendByNotApply($userId, $friendId) {
		return Friend::findFirst("user_id = $userId AND friend_id = $friendId AND confirm != 0");
	}
	
	// 检测是否以前是好友
	public static function getOldFriend($userId, $friendId) {
		return Friend::findFirst("user_id = $userId AND friend_id = $friendId AND confirm = 2");
	}
	
	// 检测是否申请添加好友
	public static function getApplyFriend($userId, $friendId) {
		return Friend::findFirst("user_id = $userId AND friend_id = $friendId AND confirm != 1");
	}
	
	// 消息免打扰
	public static function messageDoNotDisturb($friend) {
		$friend->disturb = $friend->disturb == 1 ? 2 : 1 ;
		if(!$friend->save()){ throw new \RuntimeException(__METHOD__.$friend->getMessages()[0]); }
		
		return $friend;
	}
	
	// 获取消息免打扰用户列表
	public static function getDoNotDisturbUserList($userId) {
		return Friend::find("user_id = $userId AND disturb = 1");
	}

    /**
     * 群组消息免打扰
     *
     * @param $groupChat
     * @return mixed
     */
    public static function GroupChatMessageFree($groupChat)
    {
        $groupChat->confirm = $groupChat->confirm == 1 ? 0 : 1;
        if(!$groupChat->save()){ throw new \RuntimeException(__METHOD__.$groupChat->getMessages()[0]); }
        return $groupChat;
    }

    /**
     * 获取群组免打扰列表
     *
     * @param $userId
     * @return mixed
     */
    public static function getGroupChatMessageFreeList($userId)
    {
        return AssociationMember::find("member_id = $userId AND confirm = 1");
    }
	
	// 修改旧的好友请求数据
	public static function updateOldFriendRequest($di, $friendRequest, $user, $target, $message) {
    	try {
		    $transaction = $di->getShared(SERVICE_TRANSACTION);
		    $friendRequest->setTransaction($transaction);
		    $friendRequest->status = 2;
		    $friendRequest->message = $message;
		    $friendRequest->is_new = 1;
            $friendRequest->create_time = date('Y-m-d H:i:s');
		    // 保存请求
		    if(!$friendRequest->save()){
			    $transaction->rollback();
		    }
		    // 发送好友请求
		    // MessageSender::sendApplyFriend($di, $user, $target, $friendRequest->id, $message);
		    return $friendRequest;
	    } catch (TcFailed $e) {
    		return false;
	    }
	}
	
	// 添加好友请求
	public static function applyFriendRequest($di, $user, $target, $message) {
    	try {
		    $transaction = $di->getShared(SERVICE_TRANSACTION);
		    $friendRequest = new FriendRequest();
		    $friendRequest->setTransaction($transaction);
		    $friendRequest->user_id = $user->id;
		    $friendRequest->friend_id = $target->id;
		    $friendRequest->status = 2;
		    $friendRequest->message = $message;
		    $friendRequest->is_new = 1;
            $friendRequest->create_time = date('Y-m-d H:i:s');
		    if(!$friendRequest->save()){
			    $transaction->rollback();
		    }
		    // 发送好友请求
		    // MessageSender::sendApplyFriend($di, $user, $target, $friendRequest->id, $message);
		    return true;
	    } catch (TcFailed $e) {
    		return false;
	    }
	}
	
	// 获取好友请求
	public static function getFriendRequest($userId, $friendId) {
		return FriendRequest::findFirst("user_id = $userId AND friend_id = $friendId");
	}
	
	// 更新好友请求状态
	public static function updateFriendRequest($di, $friendRequest) {
		// 事物
		$transaction = $di->getShared(SERVICE_TRANSACTION);
		$friendRequest -> setTransaction($transaction);
		$friendRequest->status = 1;
		if(!$friendRequest->save()){
			$transaction->rollback();
		}
//		return true;
	}
	
	// 删除好友请求
	public static function delFriendRequest($friendRequest) {
		return $friendRequest->delete();
	}
	
	// 申请添加好友
	public static function applyAddFriend($userId, $targetId){
		$addFriend = new Friend();
		$addData = array('user_id' => $userId, 'friend_id' => $targetId, 'intimacy' => 0, 'confirm' => 0);
		$addFriend->assign($addData);
		if(!$addFriend->save()){ throw new \RuntimeException(__METHOD__.$addFriend->getMessages()[0]); }
		
		return $addFriend;
	}
	
	// 添加好友
	public static function addFriend($di, $userId, $targetId) {
		try {
			$redis = $di->getShared(SERVICE_REDIS);
			// 事物
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			// 添加双向好友关系 (需求是单向的)
			foreach([0, 1] as $i) {
				$applyFriend = new Friend();
				$applyFriend->setTransaction($transaction);
				// 是否看好友的朋友圈
				$isLook = LOOK_UMOMENTS_YES;
				if($i == 0){
					$applyFriend->user_id = $targetId;
					$applyFriend->friend_id = $userId;
					// 查看对应的查看关系
					$userRelationPerm = self::updateUserRelationByAdd($transaction, $targetId, $userId, 1);
//					$userRelationPerm = DBManager::getUserRelationPerm($targetId, $userId);
				} else {
					$applyFriend->user_id = $userId;
					$applyFriend->friend_id = $targetId;
					// 查看对应的查看关系
					$userRelationPerm = self::updateUserRelationByAdd($transaction, $userId, $targetId, 1);
				}
				$isLook = $userRelationPerm->is_look;
				$applyFriend->intimacy = 0;
				$applyFriend->confirm = 1;
				$applyFriend->disturb = 2;
				$applyFriend->is_look = $isLook;
				$applyFriend->forbid_look = 1;
				if (!$applyFriend->save()) {
					$transaction->rollback();
				};
			}
			RedisManager::pushWeek($redis, RedisClient::weekFriendKey(), $userId, 1, 1);
			// 执行事物提交
			return true;
		} catch (TcFailed $e) {
			return false;
		}
	}
	
	// 添加好友
//	public static function replyAddFriend($di, $userId, $friendId) {
//		try {
//			// 事物
//			$transaction = $di->getShared(SERVICE_TRANSACTION);
//			$applyFriend->setTransaction($transaction);
//			$applyFriend->confirm = 1;
//			
//			if(!$applyFriend->save()){
//				$transaction->rollback();
//			}
//			
//			if(!$oldFriend){
//				$oldFriend = new Friend();
//				$oldFriend -> setTransaction($transaction);
//				$oldFriend->user_id = $userId;
//				$oldFriend->friend_id = $friendId;
//				$oldFriend->intimacy = 0;
//				$oldFriend->disturb = 2;
//				$oldFriend->is_look = 1;
//				$oldFriend->forbid_look = 1;
//			}
//			$oldFriend->confirm = 1;
//			if(!$oldFriend->save()){
//				$transaction->rollback();
//			}
//			// 事物提交
//			return $transaction->commit();
//		} catch (TcFailed $e) {
//			return false;
//		}
//	}
	
	// 获取好友请求列表
	public static function getRequestFriend($userId, $pageIndex = 1) {
        if ($pageIndex == 1) {
            $data = FriendRequest::find(array(
                "friend_id = ".$userId,
                "order" => "status DESC, create_time DESC",
                "limit" => PAGE_SIZE
            ));
        } else {
            $startPos = ($pageIndex - 1) * PAGE_SIZE;
            $data = FriendRequest::find(array(
                "friend_id = ".$userId." ORDER BY status DESC, create_time DESC LIMIT ".$startPos.",".PAGE_SIZE
            ));
        }
        return $data;
	}

	// 检查好友请求是否有新消息
    public static function checkNewRequestFriend($userId) {
        $newFriendRequestList = FriendRequest::find(array(
            "is_new = 1 and friend_id = " . $userId
        ))->toArray();
        return $newFriendRequestList ? 1 : 0;
    }

	// 获取家族请求列表
	public static function getRequestFamily($app, $userId, $pageIndex = 1) {
        $startPos = ($pageIndex - 1) * PAGE_SIZE;
        $phql = "select * from Fichat\Models\AssociationRequest as r where (inviter_id = 0 and r.association_id in (select m.association_id from Fichat\Models\AssociationMember as m
        where member_id = $userId and user_type != 3)) or (user_id = $userId and inviter_id != 0) order by status asc, create_time desc limit $startPos, " .PAGE_SIZE;

        $familyRequestList = $app->modelsManager->executeQuery($phql);
        return $familyRequestList;
    }

    // 检查家族请求是否有新消息
    public static function checkNewRequestFamily($app, $userId) {
        $phql = "select * from Fichat\Models\AssociationRequest as r where ((inviter_id = 0 and r.association_id in (select m.association_id from Fichat\Models\AssociationMember as m
        where member_id = $userId and user_type != 3)) or (user_id = $userId and inviter_id != 0)) and is_new = 1";
        $newFamilyRequestList = $app->modelsManager->executeQuery($phql)->toArray();
        return $newFamilyRequestList ? 1 : 0;
    }

    // 检查是否有好友的新动态
    public static function checkNewFriendMoment($app, $userId, $momentId) {
        $phql = "select * from Fichat\Models\Moments m
        where (m.user_id = $userId or m.user_id in (select friend_id from Fichat\Models\Friend f where f.user_id = $userId and confirm = 1 and is_look = 1)) and m.friend = 1 and id > $momentId";
        $newFriendMoments = $app->modelsManager->executeQuery($phql)->toArray();
        return $newFriendMoments ? 1 : 0;
    }

    // 检查是否有关注用户的新动态
    public static function checkNewAttentionMoment($app, $userId, $momentId) {
        $phql = "select * from Fichat\Models\Moments m where (m.user_id in (select target_id from Fichat\Models\Attention a where a.user_id = $userId and is_look = 1)) and m.attention = 1 and id > $momentId";
        $newAttentionMoments = $app->modelsManager->executeQuery($phql)->toArray();
        return $newAttentionMoments ? 1 : 0;
    }

    // 检查是否有新的左侧栏用户消息
    public static function checkNewUserMsg($userId) {
        $newUserMsgList = UserMsg::find(array(
            "user_id = $userId and status = 1"
        ))->toArray();
        return $newUserMsgList ? 1 : 0;
    }

    /**
     * 根据id获取请求信息
     *
     * @param $id
     * @return mixed
     */
	public static function getFriendRequestById($id)
    {
        return FriendRequest::findFirst("id = $id");
	}
	// 获取邀请加入公会列表
	public static function getInviteAddAssociation($userId, $pageIndex = 1) {
        if ($pageIndex == 1) {
            $data = AssociationRequest::find(array(
                "inviter_id != 0 and user_id = ".$userId,
                "order" => "create_time DESC",
                "limit" => PAGE_SIZE
            ));
        } else {
            $startPos = ($pageIndex - 1) * PAGE_SIZE;
            $data = AssociationRequest::find(array(
                "inviter_id != 0 and user_id = ".$userId." ORDER BY create_time DESC LIMIT ".$startPos.",".PAGE_SIZE
            ));
        }
        return $data;
	}
	
	// 获取用户公会邀请记录
	public static function getApplyAssociationRecord($userId, $associationId) {
		return AssociationRequest::find("user_id = $userId AND association_id = $associationId");
	}
	
	// 获取公会邀请申请列表
//	public static function getApplyInvitAssociationList($userId, $associationId) {
//    	$cond = "(user_id = ".$userId." AND status = 2) OR (user_id = ".$userId." AND association_id = ".$associationId.")";
//		return AssociationRequest::find($cond);
//	}

    /**
     * 根据id获取申请信息
     *
     * @param $id
     * @return mixed
     */
    public static function getAssociationRequestById($id)
    {
        return AssociationRequest::findFirst("id = $id");
	}

	// 赠送钻石
	public static function giveUserDiamond($user, $target, $diamondNo) {
		$target->diamond += $diamondNo;
		if(!$target->save()){ throw new \RuntimeException(__METHOD__.$target->getMessages()[0]); }
		
		$user->diamond -= $diamondNo;
		if(!$user->save()){ throw new \RuntimeException(__METHOD__.$user->getMessages()[0]); }
		
		return true;
	}
	
	// uid验证用户
	public static function checkAccountExixtByUid($uid) {
		return Account::findFirst("uid = '$uid'");
	}
	
	// 验证手机号是否存在
	public static function checkAccountExistByPhone($phone){
		$account = Account::findFirst("phone = '$phone'");
		return $account;
	}

	//修改密码
	public static function updateAccountPassword($account,$password){
		$account->password=$password;
		if ($account->save()) {
			return $account;
		} else {
			throw new \RuntimeException(__METHOD__.$account->getMessages()[0]);
		}
	}
	
	// 更新用户数据
	public static function updateUser($di, $user, $nickName, $gender, $signature, $titleId, $user_avatar = '', $user_thumb = '', $wx_avatar = '') {
		$transaction = Utils::getDiTransaction($di);
		$user->setTransaction($transaction);
		$user->nickname = $nickName ? $nickName : $user->nickname ;
		$user->gender = $gender ? $gender : $user->gender;
		$user->signature = $signature ? $signature : $user->signature;
		$user->title_id = $titleId ? $titleId : $user->title_id;
		$user->user_avatar = $user_avatar ? OssApi::procOssPic($user_avatar) : $user->user_avatar;
		$user->user_thumb = $user_thumb ? OssApi::procOssThumb($user_thumb) : $user->user_thumb;
		$user->wx_avatar = $wx_avatar ? $wx_avatar : $user->wx_avatar;
		// 更新用户
		if(!$user->save()){
			$transaction->rollback();
		}
        // 提交事物
        $transaction->commit();
		return $user;
	}
	
	// 更新用户数据
	public static function updateUserDATA($user, $updateItems)
	{
		$user->assign($updateItems);
		if ($user->save()) {
			return $user;
		} else {
			throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);
		}
	}

    /**
     * 更新用户背景图片
     *
     * @param $user
     * @param $backgroundUrl
     * @return mixed
     */
    public static function updateUserBackgroundPic($user, $backgroundUrl, $background_thumb)
    {
        $user->background = $backgroundUrl;
        $user->background_thumb = $background_thumb;
        if($user->save()){
            return $user;
        }else{
            throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);
        }
	}
	
	/**
	 * TODO 删除好友, 删除一方关系, 另一方标记为被删除
	 */
	public static function delFriend($di, $userId, $friendId) {
		try {
			// 请求一个事物
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			// 获取好友
			$friend = Friend::findFirst("user_id = $userId AND friend_id = $friendId");
			
			// 获取目标好友
			$targetFriend = Friend::findFirst("user_id = $friendId AND friend_id = $userId");
			
			// 解除UserRelationPerm关系
			self::updateUserRelationByRemove($transaction, $userId, $friendId, 1);
			self::updateUserRelationByRemove($transaction, $friendId, $userId, 1);
			
			// 检查是用户是否与目标用户存在好友关系
			if ($friend) {
				$friend->setTransaction($transaction);
				if (!$friend->delete()) {
					$transaction->rollback();
				}
			}
			
			// 检查是目标用户是否与用户存在好友关系
			if ($targetFriend) {
				$targetFriend->setTransaction($transaction);
				if(!$targetFriend->delete()){
					$transaction->rollback();
				}
			}
			return true;
		} catch (TcFailed $e) {
			return false;
		}
//
//		if($targetFriend){
//			$targetFriend->confirm = 2;
//			if(!$targetFriend->save()){ throw new \RuntimeException(__METHOD__.$friend->getMessages()[0]); }
//		}
//		return true;
	}

	// 获取好友列表
	public static function getFriends($userId) {
		return Friend::find("user_id = $userId AND confirm = 1");
	}

	// 获取好友列表详细信息
	public static function getUserFriendList($userFriends) {
		$data = array();
		$i = 0;
		foreach($userFriends as $friend){
			$data[$i] = $friend->friend->toArray();
			$data[$i]['phone'] = $friend->friend->phone;
			$data[$i]['disturb'] = $friend->disturb;
            $data[$i]['user_avatar'] = OssApi::procOssPic($friend->friend->user_avatar);
            $data[$i]['background'] = OssApi::procOssPic($friend->friend->background);
			$i++;
		}
		
		return $data;
	}
	
	// 解除关系更新UserRelationPerm
	public static function updateUserRelationByRemove($transaction, $userId, $targetId, $type) {
		// 更新用户说说查看状态
		$userRelationPerm = DBManager::getUserRelationPerm($userId, $targetId);
		if ($userRelationPerm) {
			$userRelationPerm->setTransaction($transaction);
			$rtype = $userRelationPerm->rtype;
			// 根据类型解除关系
			if ($type == 1) {
				// 好友
				switch ($userRelationPerm->rtype) {
					case URP_TYPE_FAA:
						// 好友&关注 => 好友
						$rtype = URP_TYPE_ATTENSION;
						break;
					case URP_TYPE_FRIEND:
						// 关注 => 陌生人
						$rtype = URP_TYPE_STRANGER;
						break;
				}
			} else if ($type == 2) {
				// 关注
				switch ($userRelationPerm->rtype) {
					case URP_TYPE_FAA:
						// 好友&关注 => 好友
						$rtype = URP_TYPE_FRIEND;
						break;
					case URP_TYPE_ATTENSION:
						// 关注 => 陌生人
						$rtype = URP_TYPE_STRANGER;
						break;
				}
			}
			// 检查是否需要更新关系
			if ($rtype != $userRelationPerm->rtype) {
				$userRelationPerm->rtype = $rtype;
				if (!$userRelationPerm->save()) {
					$transaction->rollback();
				}
			}
		}
		return true;
	}
	
	// 更新用户关系权限
	public static function updateUserRelationByAdd($transaction, $userId, $targetId, $type) {
		// 更新用户说说查看状态
		$userRelationPerm = DBManager::getUserRelationPerm($userId, $targetId);
		if ($userRelationPerm) {
			$userRelationPerm->setTransaction($transaction);
			$rtype = $userRelationPerm->rtype;
			// 根据类型解除关系
			if ($type == 1) {
				// 好友
				switch ($userRelationPerm->rtype) {
					case URP_TYPE_STRANGER:
						// 好友&关注 => 好友
						$rtype = URP_TYPE_FRIEND;
						break;
					case URP_TYPE_ATTENSION:
						// 关注 => 陌生人
						$rtype = URP_TYPE_FAA;
						break;
				}
			} else if ($type == 2) {
				// 关注
				switch ($userRelationPerm->rtype) {
					case URP_TYPE_STRANGER:
						// 好友&关注 => 好友
						$rtype = URP_TYPE_ATTENSION;
						break;
					case URP_TYPE_FRIEND:
						// 关注 => 陌生人
						$rtype = URP_TYPE_FAA;
						break;
				}
			}
			// 检查是否需要更新关系
			if ($rtype != $userRelationPerm->rtype) {
				$userRelationPerm->rtype = $rtype;
				if (!$userRelationPerm->save()) {
					$transaction->rollback();
				}
			}
			return $userRelationPerm;
		} else {
			$rtype = URP_TYPE_STRANGER;
			switch ($type) {
				case 1:
					$rtype = URP_TYPE_FRIEND;
					break;
				case 2:
					$rtype = URP_TYPE_ATTENSION;
					break;
			}
			$userRelationPerm = new UserRelationPerm();
			$userRelationPerm->setTransaction($transaction);
			$userRelationPerm->user_id = $userId;
			$userRelationPerm->target_id = $targetId;
			$userRelationPerm->rtype = $rtype;
			$userRelationPerm->is_look = LOOK_UMOMENTS_YES;
			if (!$userRelationPerm->save()) {
				$userRelationPerm->rollback();
			}
			return $userRelationPerm;
		}
	}
	

	// 获取关注列表 
	public static function getFocus($userId) {
		return Attention::find("user_id = $userId");
	}
	
	// 获取被关注列表
	public static function getFans($userId) {
		return Attention::find(array("target_id = $userId"));
	}

	// 获取随机一个用户
	public static function getRandomUser($app, $userId) {
		$userPhql = "SELECT * FROM Fichat\Models\User WHERE id != $userId AND nickname != ''
		AND id NOT IN (SELECT friend_id FROM Fichat\Models\Friend WHERE user_id = $userId)
		AND Fichat\Models\User.id NOT IN (SELECT target_id FROM Fichat\Models\Attention WHERE user_id = $userId)
		ORDER BY RAND() LIMIT 1";
		
		$user = $app->modelsManager->executeQuery($userPhql);
		
		return $user[0];
	}

	// 上传朋友圈图片(安卓)
	public static function uploadPri($userId, $priList) {
		$path = APP_DIR .'/images/moments/';
		$url = '';
		$initUrl = '';
		foreach($priList['error'] as $key => $val){
			if($val == 0){
				$ext = pathinfo($priList['name'][$key], PATHINFO_EXTENSION);
				$fileName = $userId . time() . mt_rand(100000, 999999) . '.'  . $ext;
				$originUrl = '/images/moments/' . $fileName;
				$pathName = $path . $fileName;
				move_uploaded_file($priList['tmp_name'][$key], $pathName);

				// 压缩图片
				$compressUrl = self::compressedFile($pathName, $ext, $userId);
									
				$url .= $compressUrl . '|';
				$initUrl .= $originUrl . '|';
			}
		}
		$initUrl = trim($initUrl, '|');
		$url = trim($url, '|');
		
		return array('originUrl' => $initUrl, 'compressUrl' => $url);
	}
	
	// 上传朋友圈图片(IOS)
	public static function uploadPriIOS($userId, $streamData) {
		$path = APP_DIR .'/images/moments/';
		$url = '';
		$initUrl = '';
		foreach($streamData as $key => $pri){
			if($key % 2 != 0){ continue; }
			$priStream = base64_decode($pri);
			$fileName = $userId . time() . mt_rand(100000, 999999) . '.' . $streamData[$key + 1];
			$originUrl = '/images/moments/' . $fileName;
			$pathName = $path . $fileName;
			file_put_contents($pathName, $priStream);
			
			// 压缩图片
			$compressUrl = self::compressedFile($pathName, $streamData[$key + 1], $userId);
				
			$url .= $compressUrl . '|';
			$initUrl .= $originUrl . '|';
		}
		$initUrl = trim($initUrl, '|');
		$url = trim($url, '|');
		
		return array('originUrl' => $initUrl, 'compressUrl' => $url);
	}
	
	// 压缩图片
	private static function compressedFile($file, $ext, $userId) {
		// 设置路径
		$path = APP_DIR .'/images/moments/';
		$fileName = $userId . time() . mt_rand(100000, 999999) . '.' . $ext;
		$pathName = $path . $fileName;
		$urlPath = "/images/moments/$fileName";
		
		// 设置宽高
		list($width, $height) = getimagesize($file);
		$compressWidth = 256;
		
		if($width > $compressWidth){
			$new_width = $compressWidth;
			$new_height = $height * ($compressWidth / $width);
		}else{
			$new_width = $width;
			$new_height = $height;
		}
		
		// 压缩
		$image_p = imagecreatetruecolor($new_width, $new_height);
		
		if($ext == 'jpg' || $ext == 'jpeg'){
			$image = imagecreatefromjpeg($file);
			imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			imagejpeg($image_p, $pathName);
		}else if($ext == 'gif'){
			$image = imagecreatefromgif($file);
			imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			imagegif($image_p, $pathName);
		}else if($ext == 'png'){
			$image = imagecreatefrompng($file);
			imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			imagepng($image_p, $pathName);
		}
		
		return $urlPath;
	}
	
	// 保存朋友圈
	public static function saveMoments($uid, $content, $pri_info, $visible, $type = 0, $redPacketId = 0, $amount = 0) {
		$moments = new Moments();
		// 类型
		if ($redPacketId != 0) {
			$type = 2;
		} else {
			$type = 1;
		}
		$data = array(
            'user_id' => $uid,
            'content' => $content,
            'pri_url' => $pri_info['url'],
            'pri_thumb' => $pri_info['thumb'],
			'pri_preview' => $pri_info['preview'],
            'create_time' => date('Y-m-d H:i:s'),
            'friend' => $visible[0],
            'attention' => $visible[1],
            'world' => 0,
            'type' => $type,
            'red_packet_id' => $redPacketId ? $redPacketId : 0,
		);
		$moments->assign($data);
		if(!$moments->save()){
			throw new \RuntimeException(__METHOD__.$moments->getMessages()[0]);
		}
		// 存入系统热点表中
        $hotEffectNum = SystemHot::calHotEffectNum($amount);
        SystemHot::saveNewHot($moments->id, 3, $hotEffectNum['expo_num'], $hotEffectNum['hot_num']);

		// 保存数据到系统动态表中
		SystemDyn::saveNewDyn($moments->id, 2, 0, $uid);
		return $moments;
	}
	
	// 验证用户是否发过世界圈
	public static function checkLastWorldCircle($userId) {
		$lastWorldCircle = self::getLastWorldCircle($userId);
		if($lastWorldCircle){
			$lastTime = strtotime($lastWorldCircle->create_time);
			$theDayZero = strtotime(date('Ymd'));
			$nextDayZero = $theDayZero + 86400;
			if($lastTime > $theDayZero && $lastTime < $nextDayZero){
				return 1;
			}
		}
		return 2;
	}
	
	// 获取用户最后一条时间圈
	public static function getLastWorldCircle($userId) {
		$moments = Moments::find(array(
				"user_id = $userId AND world = 1",
				'order' => 'create_time DESC',
				'limit' => 1,
		));
		if(count($moments) != 0){
			return $moments[0];
		}
		
		return false;
	}
	
	// 说说ID查询说说
	public static function getMomentsByMomentsId($momentsId) {
		return Moments::findFirst("id = $momentsId");
	}
	
	// 查询用户点赞信息
	public static function getMomentsLike($userId, $momentsId) {
		return MomentsLike::findFirst("moments_id = $momentsId AND user_id = $userId");
	}

	// 查询用户点赞评论的信息
    public static function getMomentsReplyLike($userId, $replyId) {
        return MomentsReplyLike::findFirst("reply_id = $replyId AND user_id = $userId");
    }

    // 根据评论id 获取对应的点赞列表
    public static function getReplyLikeListByReplyId($replyId) {
	    return MomentsReplyLike::find("reply_id = $replyId");
    }

	//根据id查询点赞记录
	public static function getMomentsLikeById($likeId) {
        return MomentsLike::findFirst("id = $likeId");
    }

    //分页查询点赞记录
    public static function getMomentsLikeByPage($app, $momentsId, $likeId = 0) {
	    $sql = "SELECT * FROM Fichat\Models\MomentsLike WHERE id > $likeId AND moments_id = $momentsId LIMIT 50";
	    $momentsLikeList = $app->modelsManager->executeQuery($sql);
	    return $momentsLikeList;
    }
	
	// 查询用户回复信息
	public static function getMomentsReply($userId, $momentsId) {
		return MomentsReply::find("moments_id = $momentsId AND (user_id = $userId OR replyer_id = $userId)");
	}

	// 通过父级评论的id查询
	public static function getMomentsReplyByParentId($parentId) {
	    return MomentsReply::find("parent_id = $parentId");
    }
	
	// 说说点赞
	public static function momentsLike($userId, $momentsId) {
		$momentsLike = new MomentsLike();
		$momentsLike->moments_id = $momentsId;
		$momentsLike->user_id = $userId;
		$momentsLike->like_time = date('Y-m-d H:i:s');
		if(!$momentsLike->save()){ throw new \RuntimeException(__METHOD__.$momentsLike->getMessages()[0]); }
		
		return $momentsLike;
	}
	
	// 取消说说点赞
	public static function delMomentsLike($momentsLike) {
		return $momentsLike->delete();
	}

	// 获取点赞数量
	public static function getMomentsLikeCount($momentId) {
	    return MomentsLike::count("moments_id = $momentId");
    }

    // 删除说说评论(采用软删除)
    public static function delMomentsReply($momentsReply) {
        $momentsReply->status = 1;
        if(!$momentsReply->save()){ throw new \RuntimeException(__METHOD__.$momentsReply->getMessages()[0]); }
        return $momentsReply;
    }

    // 评论点赞
    public static function momentsReplyLike($userId, $replyId) {
        $momentsReplyLike = new MomentsReplyLike();
        $momentsReplyLike->reply_id = $replyId;
        $momentsReplyLike->user_id = $userId;
        $momentsReplyLike->like_time = date('Y-m-d H:i:s');
        if(!$momentsReplyLike->save()){ throw new \RuntimeException(__METHOD__.$momentsReplyLike->getMessages()[0]); }
        return $momentsReplyLike;
    }

    // 取消评论点赞
    public static function delMomentsReplyLike($momentsReplyLike) {
        return $momentsReplyLike->delete();
    }
	
	// 说说评论
	public static function momentsReply($userId, $momentsId, $content, $parentId = 0) {
		$momentsReply = new MomentsReply();
		$momentsReply->moments_id = $momentsId;
		$momentsReply->user_id = $userId;
		$momentsReply->content = $content;
		$momentsReply->reply_time = date('Y-m-d H:i:s');
        $momentsReply->parent_id = $parentId;
        $momentsReply->status = 0;
        $momentsReply->like_count = 0;
		if(!$momentsReply->save()){ throw new \RuntimeException(__METHOD__.$momentsReply->getMessages()[0]); }
		
		return $momentsReply;
	}

	//更新说说评论的点赞数量
	public static function updateMomentsReplyLikeCount($momentsReply) {
        if(!$momentsReply->save()){ throw new \RuntimeException(__METHOD__.$momentsReply->getMessages()[0]); }
        return $momentsReply;
    }
	
	// 查询说说全部点赞
	public static function getMomentsAllLike($momentsId) {
		return MomentsLike::find("moments_id = $momentsId");
	}
	
	// 查询说说全部评论
	public static function getMomentsAllReply($momentsId) {
		return MomentsReply::find("moments_id = $momentsId and status = 0");
	}

	//根据点赞数获取评论的前三名
    public static function getMomentsTopThree($app, $momentsId) {
        $sql = "SELECT * FROM Fichat\Models\MomentsReply WHERE like_count > 0 AND status = 0 AND moments_id = $momentsId ORDER BY like_count DESC LIMIT 0, 3";
        $momentsReplyList = $app->modelsManager->executeQuery($sql);
        return $momentsReplyList;
    }

	// 查询说说的单条评论
    public static function getMomentsReplyById($replyId) {
	    return MomentsReply::findFirst("id = $replyId");
    }

    public static function getMomentsReplyByReplyIdAndUserId($replyId, $userId) {
        return MomentsReply::findFirst("id = $replyId and user_id = $userId");
    }


	
	// 查询说说全部打赏
	public static function getMomentsAllGive($momentsId) {
		return MomentsGive::find("moments_id = $momentsId");
	}
	
	// 获取所有好友、关注 朋友圈
	public static function getAllMoments($app, $userId, $type) {
		$lookUserIdsSql = "SELECT target_id FROM Fichat\Models\UserRelationPerm
						   WHERE Fichat\Models\UserRelationPerm.user_id = $userId AND is_look = 1";
		// 朋友圈
	    if($type == 3){
		    $sql = "SELECT * FROM Fichat\Models\Moments WHERE friend = 1 AND user_id = $userId OR
					Fichat\Models\Moments.user_id IN ( $lookUserIdsSql )
					ORDER BY Fichat\Models\Moments.create_time DESC";
		// 关注圈
		}else if($type == 2){
			$sql = "SELECT * FROM Fichat\Models\Moments WHERE attention = 1 AND user_id = $userId OR
					Fichat\Models\Moments.user_id IN ( $lookUserIdsSql )
					ORDER BY Fichat\Models\Moments.create_time DESC";
		}
		// 执行查询
		$momentsList = $app->modelsManager->executeQuery($sql);
		// 返回结果
		return $momentsList;
	}
	
	// 获取最新20条好友、关注 朋友圈
	public static function getTenMoments($app, $userId, $type) {
		$lookUserIdsSql = "SELECT target_id FROM Fichat\Models\UserRelationPerm
						   WHERE Fichat\Models\UserRelationPerm.user_id = $userId AND is_look = 1";
		if($type == 3){
            // 朋友圈
			$sql = "SELECT * FROM Fichat\Models\Moments WHERE friend = 1 AND user_id = $userId OR
					Fichat\Models\Moments.user_id IN ( $lookUserIdsSql )
					ORDER BY Fichat\Models\Moments.create_time DESC LIMIT 20";
		} else if ($type == 2) {
            // 关注圈
			$sql = "SELECT * FROM Fichat\Models\Moments WHERE attention = 1 AND user_id = $userId OR
					Fichat\Models\Moments.user_id IN ( $lookUserIdsSql )
					ORDER BY Fichat\Models\Moments.create_time DESC LIMIT 20";
		}
		$momentsList = $app->modelsManager->executeQuery($sql);
		return $momentsList;
	}

    /**
     * 获取世界圈
     *
     * @param $app
     * @param $startTime
     * @param $endTime
     */
    public static function getWorldMoments($app, $userId, $startTime, $endTime)
    {
//        $nowTime = date('Y-m-d H:i:s');
        /*AND Fichat\Models\Moments.red_packet_id NOT IN (SELECT Fichat\Models\RedPacket.id FROM Fichat\Models\RedPacket WHERE Fichat\Models\RedPacket.start_time > Fichat\Models\RedPacket.create_time AND Fichat\Models\RedPacket.start_time > '$nowTime' )*/
        $sql = "SELECT * FROM Fichat\Models\Moments WHERE world = 1 AND create_time >= '$startTime' AND create_time <= '$endTime'  AND
                Fichat\Models\Moments.user_id NOT IN (SELECT friend_id FROM Fichat\Models\Friend WHERE Fichat\Models\Friend.user_id = $userId AND (is_look = 2 OR forbid_look = 2)) 
                ORDER BY RAND() DESC LIMIT 10";
        $momentsList = $app->modelsManager->executeQuery($sql);
        return $momentsList;
    }

        // 获取所有说说的评论
	public static function getAllReply($momentsList) {
		$reply = array();
		foreach($momentsList as $key => $value){
			$momentsReply = MomentsReply::find("moments_id = $value->id");
			$reply[$key] = $momentsReply;
		}
		
		return $reply;
	}
	
	// 获取所有说说的点赞
	public static function getAllLike($momentsList) {
		$like = array();
		foreach($momentsList as $key => $value){
			$momentsLike = MomentsLike::find("moments_id = $value->id");
			$like[$key] = $momentsLike;
		}
	
		return $like;
	}
	
	// 获取所有说说的打赏
	public static function getAllGive($momentsList) {
		$give = array();
		foreach($momentsList as $key => $value){
			$momentsGive = MomentsGive::find("moments_id = $value->id");
			$give[$key] = $momentsGive;
		}
		
		return $give;
	}
	
	// 获取所有发表说说用户昵称
	public static function getAllMomentsUser($momentsList) {
		$users = array();
		foreach($momentsList as $key => $moments){
			$user = User::findFirst("id = $moments->user_id");
			$users[$key] = $user;
		}
		
		return $users;
	}
	
	// 获取单个用户所有说说
	public static function getUserMomentsList($userId, $pageIndex = 1, $isSeeAll = true) {
        $startPos = ($pageIndex - 1) * PAGE_SIZE;
        if($isSeeAll) {
            $data = Moments::find(array(
                "user_id = ".$userId." ORDER BY create_time DESC LIMIT ".$startPos.",".PAGE_SIZE
            ));
        } else {
            $data = Moments::find(array(
                "attention = 1 AND user_id = ".$userId." ORDER BY create_time DESC LIMIT ".$startPos.",".PAGE_SIZE
            ));
        }

        if ($data) {
	    	return $data;
        } else {
	    	return false;
        }
	}
	
	// 获取用户抢红包记录
	public static function getUserRedpacketRecord($uid, $redpackIds) {
		$data = [];
    	if ($redpackIds) {
		    $redPacketRecords =  RedPacketRecord::find("red_packet_id in (".$redpackIds.") AND grab_user_id = ".$uid);
		    foreach($redPacketRecords as $redPacketRecord) {
			    $recordData = $redPacketRecord->toArray();
			    unset($recordData['red_packet_id']);
			    $redpackId = $redPacketRecord->red_packet_id;
			    $data[$redpackId] = $recordData;
		    }
	    }
        return $data;
	}

	//检查红包记录
    public static function checkRedPacketRecord($uid, $redPacketId){
        $redPacketRecords =  RedPacketRecord::findFirst("red_packet_id = $redPacketId AND grab_user_id = $uid");
        return $redPacketRecords;
    }
	
	// 获取单个用户说说
	public static function getUserNewMoments($userId) {
		return Moments::find(array(
				"user_id = $userId",
				'order' => 'create_time DESC'
		));
	}
	
	// 创建说说打赏
	public static function createMomentsGive($userId, $moments, $number) {
		$momentsGive = new MomentsGive();
		$momentsGive->user_id = $userId;
		$momentsGive->target_id = $moments->user_id;
		$momentsGive->moments_id = $moments->id;
		$momentsGive->amount = $number;
		$momentsGive->give_time = date('Y-m-d H:i:s');
		
		if(!$momentsGive->save()){ throw new \RuntimeException(__METHOD__.$momentsGive->getMessages()[0]); }
		
		return $momentsGive;
	}
		
	// 更新用户余额 1：增加余额,2：减少余额
	public static function updateUserBalance($user, $number, $type) {
		if($type == 1){
			$user->balance += $number;
		}else if($type == 2){
			$user->balance -= $number;
		}
		if(!$user->save()){ throw new \RuntimeException(__METHOD__.$user->getMessages()[0]); }
		return $user;
	}
	
	// 随机分配people
	public static function randomPeople($app, $user, $userId) {
		// 随机10个不是好友user
		$userPhql = "SELECT * FROM Fichat\Models\User WHERE id != $userId AND nickname != ''
					AND id NOT IN (SELECT friend_id FROM Fichat\Models\Friend WHERE user_id = $userId) 
					AND Fichat\Models\User.id NOT IN (SELECT target_id FROM Fichat\Models\Attention WHERE user_id = $userId)
					ORDER BY RAND() LIMIT 10";

		$users = $app->modelsManager->executeQuery($userPhql);
		return $users;
	}
	
	// 获取用户登录token
	public static function getToken($userId) {
		if(!$userId){ throw new \InvalidArgumentException(__METHOD__.'userId不存在'); }
		$user = LoginToken::findFirst("user_id = $userId");
		if(!$user){ throw new \InvalidArgumentException(__METHOD__.'userId对应用户不存在'); }
		$token = $user->token;
		return $token;
	}

	// 更新用户好友验证状态
    public static function changeFriendVerify($user)
    {
        $user->verify = $user->verify == 1 ? 2 : 1;
        if(!$user->save()){ throw new \RuntimeException(__METHOD__.$user->getMessages()[0]); }

        return $user;
    }

    // 根据userId和说说Id获取说说详情
    public static function getMomentById($userId, $momentId)
    {
        $moment = Moments::findFirst("user_id = $userId AND id = $momentId");
        return $moment;
    }

    // 删除说说
    public static function delMomentById($userId, $momentId)
    {
//        $moment = Moments::findFirst("user_id = $userId AND id = $momentId");
//        if(!$moment->delete()){ throw new \RuntimeException(__METHOD__.$moment->getMessages()[0]); }
	    $moment = Moments::findFirst("user_id = $userId AND id = $momentId");
	    if ($moment) {
	    	try {
			    // 创建事物
			    $manager = new TcManager();
			    // 请求一个事物
			    $transaction = $manager->get();
			    // 设置moment的事物
			    $moment->setTransaction($transaction);
			    if ($moment->delete() == false) {
				    $transaction->rollback("delete moment failed");
			    }
			    // 获取点赞和回复
			    foreach(MomentsLike::find("moments_id=".$momentId) as $momentLike){
				    $momentLike->setTransaction($transaction);
				    if ($momentLike->delete() == false) {
					    $transaction->rollback("delete moments like failed");
				    }
			    }
			    foreach(MomentsReply::find("moments_id=".$momentId) as $momentReply){
				    $momentReply->setTransaction($transaction);
				    if ($momentReply->delete() == false) {
					    $transaction->rollback("delete moments reply failed");
				    }
			    }
			    // 检查是否在热点中
			    $sysHot = SystemHot::findFirst("trigger_id = ".$momentId." AND type = 3");
			    
			    if ($sysHot) {
				    $sysHot->setTransaction($transaction);
				    if ($sysHot->delete() == false) {
					    $transaction->rollback("delete system hot failed");
				    }
			    }
			    // 检查是否在动态中
			    $sysDyn = SystemDyn::findFirst("trigger_id = ".$momentId);
			    if ($sysDyn) {
				    $sysDyn->setTransaction($transaction);
				    if ($sysDyn->delete() == false) {
					    $transaction->rollback("delete system dyn failed");
				    }
			    }
			    // 提交事物
			    $transaction->commit();
			    return true;
		    } catch (TcFailed $e) {
	    	    return false;
		    }
	    }
        return true;
    }

    /**
     * 创建订单
     *
     * @param $userId
     * @param $orderId
     * @param $amount
     * @param $payChannel //支付方式 1:支付宝 2:微信
     * @param $payAccount //提现账户
     * @param $consumption_type //交易类型 1,充值；2,提现,3:发红包,6:发悬赏
     * @param $remark
     */
    public static function generateOrder($userId, $orderId, $amount, $payChannel, $payAccount, $consumType, $remark = NULL, $status = NULL, $balance = NULL)
    {
        $orderInfo = new UserOrder();
        // 构建remark
        $remark = KakaPay::buildUserOrderRemark($consumType, $amount);
        // status：订单状态，1成功,0，失败  consumption_type：消费类型，1,充值；2,提现,3:发红包,6:发悬赏
        $data = array(
            'user_id' => $userId,
            'order_num' => $orderId,
            'amount' => $amount,
            'balance' => $balance ? $balance : 0,
            'status' => $status ? $status : 0,
            'consum_type' => $consumType,
            'create_date' => time(),
            'pay_channel' => $payChannel,
            'pay_account' => $payAccount,
            'remark' => $remark
        );
        $orderInfo->assign($data);
        if (!$orderInfo->save()) {
            throw new \RuntimeException(__METHOD__.$orderInfo->getMessages()[0]);
        }
        return array('order_id'=>$orderId,'timestamp'=>time($data['create_date']),'remark'=>$data['remark'], 'orderInfo'=>$orderInfo);
    }


    /**
     * 查询用户充值记录
     *
     * @param $userId
     * @return mixed
     */
    public static function getUseRechargeRecord($userId)
    {
        return UserOrder::find([
            "user_id = $userId AND status = 1",
            "order" => "create_date DESC"
        ]);
    }

    /**
     * 获取钱包流水
     * @param $userId
     * @return Model\ResultsetInterface
     */
    public static function getUserBalanceFlow($userId)
    {
        return BalanceFlow::find([
            "uid = $userId",
            "order" => "create_time DESC"
        ]);
    }

    /**
     * 获取用户当日提现金额
     *
     * @param $userId
     * @param $startDate
     * @param $endDate
     * @return mixed
     */
    public static function getUserWithdrawalsAmount($userId, $startDate, $endDate)
    {
        $data = UserOrder::sum([
            "conditions" => "user_id = $userId AND create_date >= '$startDate' AND create_date <= '$endDate' AND status = 1",
            "column" => "amount",
            "group" => "user_id"
        ])->toArray();
        if (count($data)) {
            return $data[0]['sumatory'];
        } else {
            return 0;
        }
    }
    /**
     * 删除用户订单
     *
     * @param $userId
     * @param $orderId
     * @return bool
     */
    public static function delOrder($userId, $orderId)
    {
        $order = UserOrder::findFirst("user_id = $userId AND id = $orderId");
        if ($order) {
            $order->delete();
        }
        return true;
    }

    /**
     * 查询用户订单信息
     *
     * @param $userId
     * @param $orderId
     * @return mixed
     */
    public static function getOrderInfo($userId, $orderId)
    {
        return UserOrder::findFirst("user_id = $userId AND order_num = $orderId");

    }

    /**
     * 更改订单状态,判断支付宝是否回调完成,更改用户余额
     *
     * @param $userId
     * @param $orderId
     * @return bool
     */
    public static function changeOrderStatus($userId, $orderId)
    {
        $userOrder = UserOrder::findFirst("user_id = $userId AND order_num = $orderId");
        if ($userOrder->status == 0) {
            $userOrder->status = 1;
            if (!$userOrder->save()) {
                throw new \RuntimeException(__METHOD__.$userOrder->getMessages()[0]);
            }
            // 判断支付宝回调完成,更改用户余额
            if ($userOrder->callback_data != NULL) {
                $user = self::getUserById($userId);
                $user->balance += $userOrder->amount;
                if (!$user->save()) {
                    throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);
                }
                // 更改订单表余额
                $userOrder->balance = $user->balance;
                if (!$userOrder->save()) {
                    throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);

                }
            }
        }



        return true;
    }

    /**
     * 判断体现金额是否不大于可提现金额
     *
     * @param $user
     * @param $amount
     * @return bool
     */
    public static function checkBalance($user, $amount)
    {
        $withdrawalAmount = $user->balance - ($user->balance * 0.02);
        if ($withdrawalAmount >= $amount) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 根据订单号,交易类型获取订单信息
     *
     * @param $orderId
     * @return mixed
     */
    public static function getOrderByOrderId($orderId)
    {
        return UserOrder::findFirst("order_num = $orderId");
    }

    /**
     * 用户订单添加回调信息,更改用户余额
     *
     * @param $orderId
     * @param $callbackData
     * @return bool
     */
    public static function setCallbackData($userOrder, $callbackData)
    {

        // 添加回调信息
        if ($userOrder->callback_data == NULL) {
            $userOrder->callback_data = json_encode($callbackData);
            $userOrder->status = 1;
            if (!$userOrder->save()) {
                throw new \RuntimeException(__METHOD__.$userOrder->getMessages()[0]);
            }

            // 获取用户信息,更改余额
            $user = self::getUserById($userOrder->user_id);
            $newBalance = $user->balance + $userOrder->amount;
            $user->balance = $newBalance;
            if (!$user->save()) {

                throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);
            }

            // 修改订单表中余额
            $userOrder->balance = $newBalance;
            if (!$userOrder->save()) {
                throw new \RuntimeException(__METHOD__.$userOrder->getMessages()[0]);
            }
        }
        return true;
    }

    /**
     * 提现更改订单信息
     *
     * @param $orderId
     * @param $callbackData
     * @return bool
     */
    public static function changeOrderStatusByOrderId($orderId)
    {
        $order = self::getOrderByOrderId($orderId);
        $order->status = 1;
        if (!$order->save()) {
            throw new \RuntimeException(__METHOD__.$order->getMessages()[0]);
        }
        return $order;
    }

    /**
     * 添加订单回调信息
     *
     * @param $order
     * @param $callbackData
     * @return bool
     */
    public static function insertOrderCallBackInfo($orderId, $callbackData = NULL)
    {
        $order = self::getOrderByOrderId($orderId);
        $order->callback_data = json_encode($callbackData);
        if (!$order->save()) {
            throw new \RuntimeException(__METHOD__.$order->getMessages()[0]);
        }
        return true;
    }

    /**
     * 更改用户余额
     *
     * @param $user
     * @param $amount
     * @param $type 1,减少;2,增加
     * @return bool
     */
    public static function changeUserBalance($user, $amount, $type)
    {
        if ($type == 1) {
            $user->balance -= $amount;
        } else if ($type == 2) {
            $user->balance += $amount;
        }
        if (!$user->save()) {
            throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);
        }
        return true;
    }

    /**
     * 发红包
     *
     * @param $user
     * @param $amount
     * @param null $number
     * @param null $password
     * @param null $startTime
     * @param $describe
     * @return RedPacket
     */
    public static function sendUserRedPacket($di, $user, $groupId, $visible, $amount, $number = 0, $password = '', $startTime = null, $describe, $type, $payResult)
    {
    	$redis = RedisClient::create($di->get('config')['redis']);
        $data = array();
        $data['user_id'] = $user->id;
        $data['group_id'] = $groupId;
        $data['amount'] = $amount ;
        $data['number'] = $number ? (int)$number : 1;
        $data['type'] = $type;
        $data['balance'] = $amount;
        $data['password'] = $password;
        $data['status'] = 0;
        $data['des'] = $describe;
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['start_time'] = $startTime ? $startTime : null;
        $redPacket = new RedPacket();
        $redPacket->assign($data);
        if (!$redPacket->save()) {
            throw new \RuntimeException(__METHOD__.$redPacket->getMessages()[0]);
        }
        // 分配红包金额
	    $distRs = RedpackDist::makeRedpackDist($redis, $redPacket->id, $type, $amount, $number);
        // 如果结果是数组, 就是有错误, 如果结果是bool型,则正确
        if (is_array($distRs)) {
        	return $distRs;
        }
	    // 更新订单,发送消息
	    $orderId = $payResult['orderId'];
        // 更改用户余额
	    if ($payResult['payBy'] == PAY_BY_BL) {
        	//  更新用户余额
		    KakaPay::updateUserBalance($user, BALOP_TYPE_REDUCE, $amount, $payResult['consumType'], $redPacket->id, 0);
		    $orderData = [
			    'user_id' => $user->id,
			    'order_num' => $orderId,
			    'amount' => $amount,
			    'balance' => $user->balance,
			    'status' => 1,
			    'consum_type' => $payResult['consumType'],
			    'create_date' => time(),
			    'pay_channel' => PAY_BY_BL,
			    'pay_account' => '',
			    'remark' => ''
		    ];
		    KakaPay::createUserOrder($orderData);
	    }
	    // 增加用户经验
//	    self::changeUserLevel($di, $redis, $user, $amount * 100);
        // 移除描述信息
        unset($data['des']);
        RedisManager::createCacheRedPack($redis, $user->id, $redPacket->id, $data);
        // 将红包数据推入周红包中
	    RedisManager::pushWeek($redis, RedisClient::weekRedPacketKey(), $user->id, 1, $amount);
	    // 发送消息红包支付成功
	    $userOrder = new UserOrder();
	    $userOrder->create_date = $payResult['payTime'];
	    $userOrder->order_num = $orderId;
	    MessageSender::sendPayRedpacket($di, $user, $redPacket, $payResult['payBy'], $userOrder);
	    // 检查是否进行推送, 只有当发送到关注圈的时候才会发送消息
	    if ($visible[1]) {
		    // 获取最低推送金额, 比较是否达到了最低推送金额
		    $lineCount = $di->get('config')['application']['redpackAmountLine'];
		    // 将红包推送到大红包中
		    if ($redPacket->amount >= $lineCount) {
			    if ($redPacket->amount >= $lineCount) {
				    // 保存数据到系统热门表中
				    $hotEffectNum = SystemHot::calHotEffectNum($amount);
				    SystemHot::saveNewHot($redPacket->id, 2, $hotEffectNum['expo_num'], $hotEffectNum['hot_num']);
			    }
			    RedisManager::pushDayBig($redis, RedisClient::dayBigRedpack(), $redPacket->id, $redPacket->amount);
		    }
	    }
	    $redis->close();
        return $redPacket;
    }


    /**
     * 获取红包信息
     *
     * @param $redPacketId
     * @return mixed
     *
     */
    public static function getRedPacketInfo($redPacketId)
    {
        return RedPacket::findFirst("id = $redPacketId");
    }


    /**
     * 获取该红包已抢个数
     *
     * @param $redPacketId
     * @return mixed
     */
    public static function getRedPacketRecordNo($redPacketId)
    {
        return RedPacketRecord::count("red_packet_id = $redPacketId");
    }

    /**
     * 抢红包
     *
     * @params $userId
     * @params $redPacket
     * @return bool
     */
    public static function doGrabRedPacket($di, $user, $redPacket)
    {
    	$uid = $user->id;
        $redis = RedisClient::create($di->get('config')['redis']);
        // 获取抢红包的权限
	    $last_count = RedisManager::getGrabRedpackPerm($redis, $redPacket->id, $redPacket->number, $uid);

	    // 检查是否有抢红包的权限
	    if ($last_count === false) {
	    	return false;
	    }
	    // 获取金额
	    $grabAmount = RedisManager::grabRedpack($redis, $redPacket->id, $redPacket->number);

	    // 更新红包数据
	    $updateResult = RedisManager::updateRedpack($redis, $redPacket->id, $grabAmount);
	    // 更新数据
	    $redPacket->balance = $updateResult['balance'];
	    $redPacket->status = $updateResult['status'];
        // 修改红包剩余金额
//        $redPacket->balance = $redPacket->balance - $grabAmount;
        // 更新红包的余额
        if (!$redPacket->save()) {
            throw new \RuntimeException(__METHOD__.$redPacket->getMessages()[0]);
        }
        // 添加红包记录
        $data = array();
        $data['red_packet_id'] = $redPacket->id;
        $data['user_id'] = $redPacket->user_id;
        $data['grab_user_id'] = $uid ;
        $data['amount'] = number_format(floatval($grabAmount), 2, '.', '');
        $data['surplus_amount'] = $redPacket->balance;
        $data['create_time'] = date('Y-m-d H:i:s');
        $redPacketRecord = new RedPacketRecord();
        $redPacketRecord->assign($data);
        if (!$redPacketRecord->save()) {
            throw new \RuntimeException(__METHOD__.$redPacketRecord->getMessages()[0]);
        }

        // 更新用户经验
        if($redPacket->number >= 20) {
            $givenExp = $grabAmount * 100;
            //更新抢者经验
            self::changeUserLevel($di, $redis, $user, $givenExp);
            //更新发者经验
            $sendUser = self::getUserById($redPacket->user_id);
            self::changeUserLevel($di, $redis, $sendUser, $givenExp);
            if($redPacket->group_id != 0) {
                //更新家族经验
                $association = self::getAssociationById($redPacket->group_id);
                // self::changeAssocLevel($redis, $association, $givenExp * 2);
                //更新家族成员经验
                $member = self::getAssociationMemberByUserId($user->id, $association->id);
                $sendMember = self::getAssociationMemberByUserId($sendUser->id, $association->id);
                self::changeAssociationMemberLevel($member, $givenExp);
                self::changeAssociationMemberLevel($sendMember, $givenExp);
            }
        }

        // 生成抢红包订单
        $orderId = Utils::generateOrderId($uid);
        $order = self::generateOrder($uid, $orderId, $grabAmount, 0, null, 7, null, 1, $user->balance);
        // 更新用户余额
        KakaPay::updateUserBalance($user, BALOP_TYPE_ADD, $grabAmount, PAYOP_TYPE_GRAB_REDPACKET, 0, $order['orderInfo']->id);
	    // 将发红包的人推入周活跃
	    RedisManager::pushWeekActive($redis, $redPacket->user_id, $grabAmount);
	    // 增加热度
	    SystemHot::addHotNum($redPacket->id, 2, 10);
        // 关闭redis套接字
	    $redis->close();
        return $redPacketRecord;
    }

    /**
     * 根据userId获取红包记录
     *
     * @param $userId
     * @param $redPacketId
     * @return mixed
     */
    public static function getUserGrabRedPacketRecord($userId, $redPacketId)
    {
        return RedPacketRecord::findFirst("grab_user_id = $userId AND red_packet_id = $redPacketId");
    }

    /**
     * 获取抢红包记录
     *
     * @params $redPackedId
     * @return mixed
     */
    public static function getRedPacketRecord($redPacketId)
    {
        return RedPacketRecord::find("red_packet_id = $redPacketId");
    }

    /**
     * 提升用户等级
     *
     * @param $user
     * @param $amount
     * @return bool
     */
    public static function changeUserLevel($di, $redis, $user, $amount)
    {
        // 计算用户总经验
        $totalExp = $amount + $user->exp;
        $oldLevel = $user->level;
        // 获取用户等级经验信息
        $userAttr = self::getUserAttr($user->level);
        $maxLevel = (int)$userAttr[count($userAttr) - 1]['level'];
        $newLevFrdNum = 0;
        $newLevAssocNum = 0;
        $newLevAttenNum = 0;
        if ($user->level < $maxLevel) {
            foreach ($userAttr as $key => $value) {
                if ($totalExp >= $value['exp']) {
                    if ($user->level < $value['level']) {
                        $user->level += 1;
	                    $newLevFrdNum = $value['friend_num'];
	                    $newLevAssocNum = $value['assoc_num'];
	                    $newLevAttenNum = $value['atten_num'];
                    }
                } else {
                	break;
                }
            }
        } else {
            $totalExp = $userAttr[count($userAttr) - 1]['exp'];
        }
        // 更新用户经验值
        $user->exp = $totalExp;
        // 检查是否升级了
        if ($user->level != $oldLevel) {
            // 更改用户称号
            self::updateUserTitle($user);
            // 发送
	        if ($newLevFrdNum && $newLevAssocNum && $newLevAttenNum) {
	        	MessageSender::sendUserUpLevel($di, $user);
	        }
        }
        
        // 推送新的等级经验到用户等级排行中
        RedisManager::pushLevExpToRank($redis, $user->id, $user->level, $user->exp);
        if (!$user->save()) {
            throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);
        }
        return true;
    }

    // 提升家族成员等级
    public static function changeAssociationMemberLevel($member, $amount) {
        $newExp = $member->exp + $amount;
        //获取成员未获取到的成员称号
        $memberTitleList = self::getMemberTitle($member->level);
        $maxLevel = $memberTitleList[count($memberTitleList) - 1]['level'];
        if($member->level < $maxLevel) {
            foreach ($memberTitleList as $key => $value) {
                if($newExp >= $value['exp']) {
                    $member->level = $value['level'];
                }
            }
            if (!$member->save()) {
                throw new \RuntimeException(__METHOD__.$member->getMessages()[0]);
            }
        }
        return true;
    }

    //获取成员未获取到的成员称号
    public static function getMemberTitle($memberLevel) {
        $memberTitleList = AssociationMemberTitle::find("level > $memberLevel");
        return $memberTitleList->toArray();
    }
    
    /**
     * 根据用户等级, 检查上线
     *
     */
    public static function checkUserLevelLimit($uid, $level, $type)
    {
    	// 获取当前等级的上线
	    $userAttr = UserAttr::findFirst("level = ".$level) ->toArray();
	    switch ($type) {
            case 1:
                // 好友数量
                $data = Friend::find([
                    "conditions" => "user_id = ".$uid,
                    "columns" => "distinct(friend_id) as id"
                ]);
                $fixKey = 'friend_num';
                break;
            case 2:
                // 家族数量
                $data = AssociationMember::find([
                    "conditions" => "type = 1 and member_id = ".$uid ,
                    "columns" => "distinct(association_id) as id"
                ]);
                $fixKey = 'assoc_num';
                break;
            case 3:
                // 关注数量
                $data = Attention::find([
                    "conditions" => "user_id = ".$uid,
                    "columns" => "distinct(target_id) as id"
                ]);
                $fixKey = 'atten_num';
                break;
        }

        if ($data) {
	        $nowCount = count($data->toArray());
        } else {
        	$nowCount = 0;
        }
        // 检查
	    if ($userAttr[$fixKey] == 0) {
	    	return true;
	    } else {
		    if ($userAttr[$fixKey] > $nowCount) {
			    return true;
		    } else {
			    return false;
		    }
	    }
    }
    


    /**
     * 获取用户等级经验信息
     *
     * @return mixed
     */
    public static function getUserAttr($level)
    {
        return UserAttr::find([
            "conditions" => "level >= $level"
        ])->toArray();
    }

    /**
     * 获取一周内的红包排行
     *
     * @return mixed
     */
    public static function getRedPacketRank()
    {
        // 获取周一时间，周天时间
        $timestamp = time();
        $startDate = date('Y-m-d H:i:s', strtotime("this week Monday", $timestamp));
        $endDate =  date('Y-m-d H:i:s', (strtotime("this week Sunday", $timestamp) + 86399));
        // 获取红包记录
        $redPacketInfo = RedPacket::sum([
            "conditions" => "create_time >= '$startDate' AND create_time <= '$endDate'",
            "column" => 'amount',
            "group" => "user_id",
            "order"  => "sumatory DESC",
            "limit" => "100"
        ]);
        return $redPacketInfo;
    }


    /**
     * 根据等级高低排序获取用户信息
     *
     * @return mixed
     */
    public static function getUserLevelRank()
    {
        return User::find([
            "columns" => "id, nickname, gender, user_avatar, level, exp",
            "order" => "level DESC, exp DESC",
            "limit" => "100"
        ]);
    }

    /**
     * 一周内送礼排行
     *
     * @return mixed
     */
    public static function getUserGiveGiftRank()
    {
        // 获取周一时间，周天时间
        $timestamp = time();
        $startDate = date('Y-m-d H:i:s', strtotime("this week Monday", $timestamp));
        $endDate =  date('Y-m-d H:i:s', (strtotime("this week Sunday", $timestamp) + 86399));
        // 获取送礼物记录
        $giftRank = GiftRecord::sum([
            "conditions" => "create_time >= '$startDate' AND create_time <= '$endDate'",
            "column" => 'amount',
            "group" => "user_id",
            "order"  => "sumatory DESC",
            "limit" => "100"
        ]);
        return $giftRank;
    }

    /**
     * 根据礼物id获取礼物信息
     *
     * @param $giftId
     * @return mixed
     */
    public static function getGiftById($giftId)
    {
        return Gift::findFirst("id = $giftId");
    }

    /**
     * 赠送礼物
     *
     * @param $user
     * @param $target
     * @param $gift
     * @return GiftRecord
     */
    public static function userGiveGift($redis, $user, $target, $gift, $number, $momentId ,$notBalance = null)
    {
        // 生成送礼记录
        $data = array();
        $data['user_id'] = $user->id;
        $data['target_id'] = $target->id;
        $data['moment_id'] = $momentId ? $momentId : null;
        $data['gift_id'] = $gift->id;
        $data['number'] = $number;
        $data['amount']  = $gift->price * $number;
        $data['create_time'] = date('Y-m-d');
        $giftRecord = new GiftRecord();
        $giftRecord->assign($data);
        if (!$giftRecord->save()) {
            throw new \RuntimeException(__METHOD__.$giftRecord->getMessages()[0]);
        }
        if (!isset($notBalance)) {
            // 减少用户余额
            self::changeUserBalance($user, $giftRecord->amount, 1);
            //创建送礼物订单
            $orderId = Utils::generateOrderId($user->account_id);
            self::generateOrder($user->account_id, $orderId, $data['amount'], 0, null, 4, $gift->name, 1, $user->balance);
        }
        // 增加用户余额
        self::changeUserBalance($target, $giftRecord->amount, 2);
        // 增加用户经验
//        self::changeUserLevel($di, $redis, $user, $data['amount']);
        // 收到礼物订单
        $orderId = Utils::generateOrderId($target->account_id);
        self::generateOrder($target->account_id, $orderId, $data['amount'], 0, null, 6, $gift->name, 1, $target->balance);

        return $giftRecord;
    }

    /**
     * 获取礼物列表
     *
     * @return mixed
     */
    public static function getGiftInfo()
    {
        return Gift::find();
    }

    /**
     * 获取送礼物记录
     *
     * @param $userId
     * @return mixed
     */
    public static function getGiveGiftRecord($userId)
    {
        return GiftRecord::find([
            "conditions" => "user_id = '$userId'",
            "order" => 'create_time DESC'
        ]);
    }

    /**
     * 获取收礼物记录
     *
     * @param $userId
     * @return mixed
     */
    public static function getReceiveGiftRecord($userId)
    {
        return GiftRecord::find([
            "conditions" => "target_id = '$userId'",
            "order" => 'create_time DESC'
        ]);
    }

    /**
     * 上传头像
     *
     * @param $headImg
     * @param $type
     * @return bool|string
     */
    public static function updateFamilyHeadImg($headImg ,$type)
    {
        if ($type == 1) {
            // Android上传头像
            $ext = pathinfo($_FILES["user_avatar"]["name"], PATHINFO_EXTENSION);
            $fileName = mt_rand(10000, 99999) . time() . '.' . $ext;
            $imgUrl = '/images/headimg/' . $fileName;
            $filePath = APP_DIR . '/images/headimg/' . $fileName;
            if (move_uploaded_file($_FILES["user_avatar"]["tmp_name"], $filePath)) {
                return $imgUrl;
            } else {
                return false;
            }
        } else {
            // IOS上传头像
            $img = base64_decode($headImg);
            $fileName = mt_rand(10000, 99999) . time() . '.jpg';
            $imgUrl = '/images/headimg/' . $fileName;
            $filePath = APP_DIR . '/images/headimg/' . $fileName;
            file_put_contents($filePath, $img);
            return $imgUrl;
        }
    }

    /**
     * 获取一天前,两天内 发到聊天中的红包
     *
     * @params $app
     * @params $startDate
     * @params $endDate
     */
    public static function getRedPacketList($app, $startDate, $endDate)
    {
        $phql = "SELECT * FROM Fichat\Models\RedPacket AS redPacket WHERE redPacket.create_time >= '$startDate' AND redPacket.create_time <= '$endDate' AND invalid = 0
		AND redPacket.id NOT IN (SELECT red_packet_id FROM Fichat\Models\Moments AS moments WHERE moments.red_packet_id != 0)
		ORDER BY redPacket.create_time";
        $redPacketList = $app->modelsManager->executeQuery($phql);

        return $redPacketList;
    }
    
    /**
     * 退还红包
     *
     * @params $redPacketList
     */
    public static function returnRedPacketList($di, $redPacketList)
    {
        foreach ($redPacketList as $key => $redPacket) {
	        // 红包未抢完,余额大于0
	        if ($redPacket->balance > 0) {
	            // 获取用户信息,退还金额
	            $user = self::getUserById($redPacket->user_id);
	            $balance = $redPacket->balance;
	            self::updateUserBalance($user, $balance, 1);
	            // 修改红包状态
	            $redPacket->invalid = 1;
	            $redPacket->balance = 0;
	            $redPacket->save();
	            // 生成红包退还订单
	            $orderId = Utils::generateOrderId($redPacket->user_id);
	            $orderData = [
	                'user_id' => $user->id,
	                'order_num' => $orderId,
	                'amount' => $balance,
	                'balance' => $user->balance,
	                'status' => 0,
	                'consum_type' => PAYOP_TYPE_RETURN_REDPACKET,
	                'create_date' => time(),
	                'pay_channel' => PAY_CHANNEL_BL,
	                'pay_account' => '',
	                'remark' => '红包退还'
	            ];
	            $orderInfo = KakaPay::createUserOrder($orderData);
                // 发送环信信息
                MessageSender::sendReturnRedpacket($di, $user, $redPacket->id, $balance, $orderInfo['order_id']);
	            // 生成余额订单
	            KakaPay::createBalanceRecord($user->id, PAYOP_TYPE_RETURN_REDPACKET, $balance, $redPacket->id, $orderInfo['id']);
	        }
        }
        return true;
    }

    /**
     * 获取用户粉丝和关注数量
     *
     * @param $userId
     * @return array
     */
    public static function getUserFansAndFollowNum($userId)
    {
        //获取用户粉丝数量
        $fansNum = Attention::count("target_id = $userId");

        // 获取用户关注数量
        $followsNum = Attention::count("user_id = $userId");
        return ["fansNum" => $fansNum, "followsNum" => $followsNum];
    }

    /**
     * 获取用户说说总数
     *
     * @param $userId
     * @return mixed
     */
    public static function getUserMomentsNum($userId)
    {
        return Moments::count("user_id = $userId");
    }

    /**
     * 获取用户收到礼物总数
     *
     * @param $userId
     * @return mixed
     */
    public static function getUserGiftNum($userId)
    {
        return GiftRecord::sum([
            "conditions" => "target_id = $userId",
            "column" => "number",
            "group" => "target_id"
        ]);
    }

    /**
     * 添加用户抢红包记录
     *
     * @param $userId
     * @param $redPacketId
     */
    public static function createUserGrabRedPacketRecord($userId, $redPacketId)
    {
        $data = array();
        $data['user_id'] = $userId;
        $data['red_packet_id'] = $redPacketId;
        $userGrabRedPacket = new UserGrabRedPacket();
        $userGrabRedPacket->assign($data);
        if (!$userGrabRedPacket->save()) {
            throw new \RuntimeException(__METHOD__.$userGrabRedPacket->getMessages()[0]);
        }
    }

    /**
     * 获取用户是否抢过红包记录
     *
     * @param $userId
     * @param $redPacketId
     * @return mixed
     */
    public static function getUserGrabRedPacket($userId, $redPacketId)
    {
        // 判断是否有红包id
        if (!$redPacketId) {
            return false;
        }
        return UserGrabRedPacket::findFirst("user_id = $userId AND red_packet_id = $redPacketId");
    }

    /**
     * 更改用户称号
     *
     * @param $user
     * @return bool
     */
    public static function updateUserTitle($user)
    {
        // 获取未获取称号信息
        $titleList = self::getUserNotOwnTitleList($user->title_id);
        foreach ($titleList as $key => $title) {
            if (($user->level >= $title->demand)) {
                $user->title_id = $title->id;
            }
        }
        if (!$user->save()) {
            throw new \RuntimeException(__METHOD__.$user->getMessages()[0]);
        }
        return true;
    }

    /**
     * 获取用户未获取称号
     *
     * @param $userTitleId
     * @return mixed
     */
    public static function getUserNotOwnTitleList($userTitleId)
    {
        return Title::find("id > $userTitleId");
    }

    /**
     * 设置用户支付密码
     *
     * @param $account
     * @param $payPassword
     * @return bool
     */
    public static function setUserPayPassword($user, $account, $payPassword, $phone)
    {
        $user->phone = $phone;
        if(!$user->save()){ throw new \RuntimeException(__METHOD__.$user->getMessages()[0]); }
        $account->phone = $phone;
        $account->pay_password = $payPassword;
        if (!$account->save()) {throw new \RuntimeException(__METHOD__.$account->getMessages()[0]);}
        return true;
    }

    /**
     * 获取用户新的好友申请,家族申请,家族邀请数量总和
     *
     * @param $userId
     * @return array
     */
    public static function getUserFriendNum($userId, $returnType = 0)
    {
        // 获取新的好友申请数量
        $newFriendRequestNum = FriendRequest::count([
            "conditions" => "friend_id = $userId AND is_new = 1",
        ]);

        // 获取用户为管理层家族
        $familyList = AssociationMember::find("member_id = $userId AND user_type != 3 AND type = 1");
        // 获取每个家族申请数量
        $newFamilyRequestNum = 0;
        foreach ($familyList as $key => $family) {
            $newFamilyRequestNum += self::getFamilyRequestNum($family->association_id);
        }
        // 获取新的家族邀请数量
        $newFamilyInviteNum = AssociationRequest::count("user_id = $userId AND is_new =1 AND inviter_id != ' '");
        if ($returnType) {
            return ['new_friends' => $newFriendRequestNum, 'new_families' => $newFamilyInviteNum + $newFamilyInviteNum];
        } else {
            return $newFriendRequestNum + $newFamilyRequestNum + $newFamilyInviteNum;
        }
    }

    /**
     * 获取家族新申请数量
     *
     * @param $familyId
     * @return mixed
     */
    public static function getFamilyRequestNum($familyId)
    {
        return AssociationRequest::count("association_id = $familyId AND is_new = 1");
    }

    /**
     * 获取用户新的粉丝数量
     *
     * @param $userId
     * @return mixed
     */
    public static function getUserNewFansNum($userId)
    {
        return Attention::count("target_id = $userId AND is_new = 1");
    }

    /**
     * 清除用户新粉丝标记
     *
     * @param $userId
     * @return bool
     */
    public static function clearUserFansBadge($userId)
    {
        $fansList = Attention::find("target_id = $userId AND is_new = 1");
        foreach ($fansList as $fans){
            $fans->is_new = 0;
            if (!$fans->save()) {
                throw new \RuntimeException(__METHOD__.$fans->getMessages()[0]);
            }
        }
        return true;
    }

    /**
     * 清除好友新请求标记
     *
     * @param $friendRequestList
     * @return bool
     */
    public static function clearUserNewFriendRequestBadge($friendRequestList)
    {
        foreach ($friendRequestList as $friendRequest) {
            if ($friendRequest->is_new == 1) {
                $friendRequest->is_new = 0;
                if (!$friendRequest->save()) {
                    throw new \RuntimeException(__METHOD__.$friendRequest->getMessages()[0]);
                }
            }
        }
        return true;
    }

    /**
     * 清除家族邀请标记
     *
     * @param $familyList
     * @return bool
     */
    public static function clearUserFamilyInviteBadge($familyList)
    {
        foreach ($familyList as $family) {
            if ($family->is_new == 1) {
                $family->is_new = 0;
                if (!$family->save()) {
                    throw new \RuntimeException(__METHOD__.$family->getMessages()[0]);
                }
            }
        }
        return true;
    }

    /**
     * 清除家族申请标记
     *
     * @param $AssociationList
     * @return bool
     */
    public static function clearUserFamilyRequestBadge($AssociationList)
    {
        foreach ($AssociationList as $memberList) {
            foreach ($memberList as $memberRequest) {
                if ($memberRequest->is_new == 1) {
                    $memberRequest->is_new = 0;
                    if (!$memberRequest->save()) {
                        throw new \RuntimeException(__METHOD__.$memberRequest->getMessages()[0]);
                    }
                }
            }
        }
        return true;
    }

    /**
     * 获取一周之前发到朋友圈的红包
     *
     * @param $app
     * @param $date
     */
    public static function getOneWeekAgoRedPacketList($app, $date)
    {
        $phql = "SELECT * FROM Fichat\Models\RedPacket AS redPacket WHERE  redPacket.create_time <= '$date' AND invalid = 0
		AND redPacket.id IN (SELECT red_packet_id FROM Fichat\Models\Moments AS moments WHERE moments.red_packet_id != 0)
		ORDER BY redPacket.create_time";
        $redPacketList = $app->modelsManager->executeQuery($phql);
        return $redPacketList;
    }

    /**
     * 创建微信订单
     *
     * @param $userId
     * @param $ipAddress
     * @param $amount
     * @return array
     */
    public static function buildWxPayOrder($userId, $ipAddress, $amount, $consumptionType, $remark = '')
    {
        // 创建本地订单
        $orderId = Utils::generateOrderId($userId);
        $orderInfo = self::generateOrder($userId, $orderId, $amount, 2, null, $consumptionType, $remark);

        // 微信生成订单
        $payUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder'; //接口url地址
        $orderId = $orderInfo['order_id'];

        // 微信下单
        $amount = $amount * 100;
        if ($consumptionType == 3) {
            $rechargeDes = '发红包';
        } else if ($consumptionType == 4){
            $rechargeDes = '发礼物';
        }
        $data = WxPayProxy::buildXML($orderId, $ipAddress, $amount, $rechargeDes);
        $result = Utils::curl_post($payUrl, $data);
        $order = Utils::xmlToArray($result);
        $order['timestamp'] = $orderInfo['timestamp'];

        return ['orderId' => $orderId, 'orderInfo' => $order];
    }

    /**
     * 创建支付宝订单
     *
     * @param $userId
     * @param $amount
     * @return array|bool
     */
    public static function buildAliPayOrder($userId, $amount, $consumptionType, $remark = '')
    {
        $orderId = Utils::generateOrderId($userId);
        $orderInfo = self::generateOrder($userId, $orderId, $amount, 1, null, $consumptionType, $remark);
        if ($orderInfo) {
            if ($consumptionType == 3) {
                $rechargeDes = '发红包';
            } else if ($consumptionType == 4){
                $rechargeDes = '发礼物';
            }
            //   支付宝订单信息
            $request = AlipayProxy::request($orderId, $amount, $rechargeDes);
            $data = ReturnMessageManager::buildAliPayOrder($orderId, $request);

            return $data;
        } else {
            return false;
        }

    }

    /**
     * 说说数据存到redis中
     *
     * @param $data
     * @param $orderInfo
     */
//    public static function saveMomentInRedis($data, $orderInfo, $priList = null, $type)
//    {
//        // 安卓图片
//        if ($priList) {
//            $data['pri_type'] = $priList['type'][0];
//            $data['pri_name'] = $priList['tmp_name'][0];
//        } else {
//            $data['pri_type'] = 'image/png';
//            $data['pri_name'] = '';
//        }
//        $data['type'] = $type;
//        $redis = Utils::redis();
//        $momentKey = Utils::moment_key($orderInfo['orderId']);
//        // 数据存储到redis中,键moment:'order_id'
//        $redis->hMset($momentKey, $data);
//        // 设定时间
//        $redis->expire($momentKey, 601);
//        // 关闭连接
//        $redis->close();
//
//        return true;
//    }

    public static function saveMomentInRedis($data, $orderInfo, $originUrl, $compressUrl, $type)
    {
        // 保存图片
        $data['pri_url'] = $originUrl;          // 原图
        $data['pri_thumb'] = $compressUrl;      // 缩略图样式
        $data['type'] = $type;                  // 说说类型
        $redis = Utils::redis();
        $momentKey = Utils::moment_key($orderInfo['orderId']);
        // 数据存储到redis中,键moment:'order_id'
        $redis->hMset($momentKey, $data);
        // 设定时间
        $redis->expire($momentKey, 601);
        // 关闭连接
        $redis->close();
        return true;
    }

    /**
     * 修改订单状态
     *
     * @param $user
     * @param $order
     * @param $redPacketOrGiftId // 红包或礼物id
     * @return mixed
     */
    public static function updateUserOderStatus($user, $order, $redPacketOrGiftId)
    {
        $order->status = 1;
        $order->balance = $user->balance;
        $order->red_packet_gift_id = $redPacketOrGiftId;
        if (!$order->save()) {
            throw new \RuntimeException(__METHOD__.$order->getMessages()[0]);
        }
        return $order;
    }

    /**
     * 微信,支付宝支付红包,创建缓存
     *
     * @param $data
     * @param $orderInfo
     * @param $type 红包类型,1:普通红包,2:手气红包
     * @return bool
     */
    public static function buildRedPacketInfoInRedis($data, $orderInfo, $type)
    {
        $data['type'] = $type;
        $redis = Utils::redis();
        $momentKey = Utils::redPacketTemporaryKey($orderInfo['orderId']);
        // 数据存储到redis中,键redPacketTemporaryKey:'order_id'
        $redis->hMset($momentKey, $data);
        // 设定时间
        $redis->expire($momentKey, 601);
        // 关闭连接
        $redis->close();
        return true;
    }

    /**
     * 微信,支付宝支付礼物,保存数据到缓存
     *
     * @param $data
     * @param $orderInfo
     * @return bool
     */
    public static function buildGiveGiftInRedis($data, $orderInfo)
    {
        unset($data['_url']);
        $redis = Utils::redis();
        $giveGiftKey = Utils::giveGiftTemporaryKey($orderInfo['orderId']);
        // 数据存储到redis中,键giveGiftTemporaryKey:'order_id'
        $redis->hMset($giveGiftKey, $data);
        // 设定时间
        $redis->expire($giveGiftKey, 601);
        // 关闭连接
        $redis->close();
        return true;
    }

    /**
     * 获取发礼物记录
     *
     * @param $id
     * @return mixed
     */
    public static function getGiftRecordById($id)
    {
        return GiftRecord::findFirst("id = $id");
    }

    /**
     * 根据支付方式 选择存储说说
     *
     */
    public static function saveMomentByPayType($payBy)
    {
        switch ($payBy) {
            case PAY_BY_WX:         // 微信支付

                break;
            case PAY_BY_ALI:        // 支付宝支付

                break;
            default:                // 余额支付

                break;
        }
    }
    
    /**
     * 获取所有的非移除悬赏任务
     *
     */
    public static function getUserRewardTasks($uid, $groupId = 0)
    {
    	$cond = "status != -1 AND ";
    	if ($groupId) {
    	    $cond .= "group_id = ". $groupId;
	    } else {
    	    $cond .= "owner_id = ". $uid;
	    }
	    
    	// 获取所有的数据
    	return RewardTask::find([
    		$cond,
		    "order" => "id desc"
	    ]) ->toArray();
    }
	
	
	/**
	 * 获取所有的非移除悬赏任务
	 *
	 */
	public static function getAssocRewardTasks($di, $groupId, $taskStatus = 1, $pageIndex = 1)
	{
		$startPos = ($pageIndex - 1) * PAGE_SIZE;
		if (!$taskStatus) {
			$cond = 'status != -1';
		} else {
			$cond = 'status = '. $taskStatus;
		}
		// 状态
		$cond = $cond . ' AND group_id = '.$groupId;
		$sql = 'SELECT * FROM Fichat\Models\RewardTask WHERE '.$cond.' order by id desc limit '. $startPos.','.PAGE_SIZE;
		$query = new Query($sql, $di);
		$rewardTasks = $query->execute();
		if ($rewardTasks) {
			$rewardTasks = $rewardTasks->toArray();
			return Self::addParentTaskInfo($di, $rewardTasks);
        } else {
		    return [];
        }
	}

	// 分页查询评论列表（除去热门评论）
    public static function getMomentsReplyByPage($app, $momentsReplyTopThree, $momentsId, $pageIndex = 1) {
        $replyIds = '';
        if($momentsReplyTopThree && count($momentsReplyTopThree) > 0) {
            foreach ($momentsReplyTopThree as $reply) {
                if($replyIds == '') {
                    $replyIds .= $reply->id;
                } else {
                    $replyIds .= ',' . $reply->id;
                }

            }
        }

        $pageSize = 50;
        $startPos = ($pageIndex - 1) * $pageSize;

        if($replyIds != '') {
            $sql = "SELECT * FROM Fichat\Models\MomentsReply WHERE moments_id = " . $momentsId ." AND id NOT IN (". $replyIds .") ORDER BY reply_time LIMIT " . $startPos . ", " . $pageSize;
        } else {
            $sql = "SELECT * FROM Fichat\Models\MomentsReply WHERE moments_id = " . $momentsId ." ORDER BY reply_time LIMIT " . $startPos . ", " . $pageSize;
        }

        $momentsReplyList = $app->modelsManager->executeQuery($sql);
        return $momentsReplyList;
    }

    // 分页查询评论列表（包含热门评论）
    public static function getMomentsReplyByPageIncludeHot($app, $momentsId, $lastReplyId = 0) {
	    $sql = "SELECT * FROM Fichat\Models\MomentsReply WHERE status = 0 AND moments_id = " . $momentsId ." AND id > ". $lastReplyId ." ORDER BY reply_time LIMIT 50";
        $momentsReplyList = $app->modelsManager->executeQuery($sql);
        return $momentsReplyList;
    }

    /**
     * 获取所有的非移除悬赏任务
     *
     */
	public static function getSystemRewardTask($di, $systemTaskIds)
    {
    	if ($systemTaskIds) {
	        $systemIds = "";
	        // 循环系统任务ID
	        foreach ($systemTaskIds as $taskId) {
	            if ($systemIds == "") {
	                $systemIds = "".$taskId;
	            } else {
	                $systemIds .= ",".$taskId;
	            }
	        }
	        $sql = 'SELECT * FROM Fichat\Models\RewardTask WHERE id in ('.$systemIds.') order by id desc';
	        $query = new Query($sql, $di);
	        // 系统悬赏任务列表
	        $systemRewardTasks = $query->execute();
	        if ($systemRewardTasks) {
	            return $systemRewardTasks->toArray();
	        } else {
	            return [];
	        }
	    } else {
    		return [];
	    }
    }
	
	/**
	 * 根据任务ID列表获取用户所有的任务记录
	 *
	 */
    public static function getUserRewardTaskRecordList($task_ids)
    {
	    $rangeIDS = "";
	    foreach($task_ids as $id => $key) {
		    if ($rangeIDS) {
			    $rangeIDS = ",".$id;
		    } else {
			    $rangeIDS = $id;
		    }
	    }
	    $condition = "task_id in (" . $rangeIDS . ")";
        $records = RewardTaskRecord::find(array(
            'condition' => $condition,
        )) -> toArray();
        $data = array();
        foreach ($records as $record) {
        	if (!$data[$record['task_id']]) {
        		$data[$record['task_id']] = array();
	        }
	        array_push($data[$record['task_id']], $record);
        }
        return $data;
    }
	
	/**
	 * 获取目标的任务ID列表数据
	 *
	 */
	public static function getRewardTasks($ids)
	{
		$rangeIDS = '';
		foreach($ids as $id => $key) {
			if ($rangeIDS) {
				$rangeIDS = ",".$id;
			} else {
				$rangeIDS = $id;
			}
		}
		$condition = "id IN (".$rangeIDS.")";
		// 获取所有的数据
		return RewardTask::find(array(
			'condition' => $condition
		)) ->toArray();
	}

	/**
     * 保存悬赏任务
     *
     */
	public static function saveRewardTask($di, $uid, $association, $cover_pic, $cover_thumb, $tmpData = null)
    {
        // 获取参数
	    if ($tmpData == null) {
		    $title = $_POST['title'];
		    $content = $_POST['content'];
		    $rewardAmount = (float)$_POST['reward_amount'];
		    $clickReward = (float)$_POST['click_reward'];
		    $shareReward = (float)$_POST['share_reward'];
		    $link = $_POST['link'];
		    $createTime = time();
		    $endTime = (int)trim($_POST['end_time']);
	    } else {
		    $title = $tmpData['title'];
		    $content = $tmpData['content'];
		    $rewardAmount = (float)$tmpData['reward_amount'];
		    $clickReward = (float)$tmpData['click_reward'];
		    $shareReward = (float)$tmpData['share_reward'];
		    $link = $_POST['link'];
		    $createTime = time();
		    $endTime = (int)$tmpData['end_time'];
	    }
	    
        // 构建悬赏任务数据
        $rewardTask = new RewardTask();
        $rewardTask->balance = $rewardAmount;
        $rewardTask->reward_amount = $rewardAmount;
        $rewardTask->title = $title;
        $rewardTask->content = $content;
        $rewardTask->link = $link;
        $rewardTask->click_reward = $clickReward;
        $rewardTask->share_reward = $shareReward;
        $rewardTask->click_count = 0;
        $rewardTask->share_count = 0;
        $rewardTask->cover_pic = $cover_pic;
        $rewardTask->cover_thumb = $cover_thumb;
        $rewardTask->end_time = $endTime;
        $rewardTask->create_time = $createTime;
        $rewardTask->owner_id = $uid;
        $rewardTask->group_id = $association->group_id;

        $redis = RedisClient::create($di->get('config')['redis']);

        // 保存数据
        if(!$rewardTask->save()){ throw new \RuntimeException(__METHOD__.$rewardTask->getMessages()[0]); }
        // 构建缓存数据
        $cacheData = $rewardTask->toArray();
        // 奖励的家族经验 （低于20人以下的家族无法获得经验）
//        if($association->current_num >= 20) {
//            $giveAssocExp = (int)$rewardAmount * 10;
//            // 提高公会等级
//            self::changeAssocLevel($redis, $association, $giveAssocExp);
//        }

        // 同步保存到Redis中
        RedisManager::saveRewardTask($redis, $cacheData);
	    // 保存数据到系统动态表中
	    SystemDyn::saveNewDyn($rewardTask->id, 1, $rewardTask->group_id, 0);
	    // 获取最低推送金额, 比较是否达到了最低推送金额
	    $lineCount = $di->get('config')['application']['rewardAmountLine'];
	    if ($rewardTask->reward_amount >= $lineCount) {
		    // 推送到超级数据中
		    if(RedisManager::pushDayBig($redis, RedisClient::dayBigReward(), $rewardTask->id, $rewardTask->reward_amount)) {
			    // 保存数据到系统热门表中
			    $hotEffectNum = SystemHot::calHotEffectNum($rewardAmount);
			    SystemHot::saveNewHot($rewardTask->id, 1, $hotEffectNum['expo_num'], $hotEffectNum['hot_num']);
			    // 发送消息
//			    $msgTitle = "[悬赏任务公告] ".Utils::makeRewardMsg($association, $rewardTask);
//			    $msgData = Utils::makeRewardPushMsgCustData($association, $rewardTask);
//			    BaiduPushProxy::pushAll($di, $msgTitle, $msgData);
		    }
	    }
        $redis->close();
        // 返回数据
        return $rewardTask;
    }

    /**
     * 获取任务记录
     *
     */
    public static function getRewardTaskRecords($di, $taskId, $condition = '1', $pageIndex = 1)
    {
    	$startPos = ($pageIndex - 1) * PAGE_SIZE;
        $sql = 'SELECT rtr.id, rtr.task_id, rtr.op_time, rtr.op_type, rtr.uid as user_id, u.nickname, u.level, u.user_avatar, u.user_thumb '
               .'FROM Fichat\Models\RewardTaskRecord as rtr LEFT JOIN Fichat\Models\User as u ON u.id = rtr.uid '
               .'WHERE rtr.status = 1 AND task_id = '.$taskId.' AND '.$condition . ' limit '.$startPos . ', '.PAGE_SIZE;
        $query = new Query($sql, $di);
	    $taskRecords = $query->execute();
	    if (!$taskRecords) { return false; }
		$redis = RedisClient::create($di->get('config')['redis']);
	    $taskRecordList = $taskRecords->toArray();
		// 对比当前缓存中的悬赏任务记录数据
	    $keys = array();
	    // 获取所有的任务记录Key
//	    $cacheRecordKeys = $redis->keys("rtr@".$taskId."&");
	    $recordIDS = array();
	    foreach ($taskRecordList as $taskRecord) {
	    	// 构建任务记录Key
	    	$key = RedisClient::rewardTaskRecordKey($taskRecord['task_id'],
			    $taskRecord['user_id'],
			    $taskRecord['op_type'],
			    $taskRecord['id']
		    );
	    	// 存入数据
//	    	array_push($keys, $key);
//	    	array_push($recordIDS, $taskRecord['id']);
	    }
	    // 批量获取数据
//	    $cachedTaskRecords = RedisClient::mHgetAll($redis, $keys);
	    
		// 更新已存在的
//	    foreach ($taskRecordList as $idx => $taskRecord) {
//	        foreach ($cachedTaskRecords as $cachekey => $cachedTaskRecord) {
//	        	$cacheKeyInfo = RedisClient::getTaskRecordInfo($cachekey);
//		        $opType = $cacheKeyInfo['op_type'];
//		        $cacheRecordId = $cacheKeyInfo['id'];
//
//		        // 检查
//	        	if ($cacheKeyInfo['id'] == $taskRecord['id']) {
//			        $taskRecordList[$idx]['op_type'] = $opType;
//			        $taskRecordList[$idx]['op_time'] = $cachedTaskRecord['fin_time'];
//		        } else if (!in_array($recordIDS, $cacheKeyInfo['id'])) {
//	        	    array_push($taskRecordList, [
//	        	    	'id' => $cacheKeyInfo['id'],
//			            'task_id' => $cacheKeyInfo['task_id'],
//			            'op_type' => $cachedTaskRecord['op_type'],
//			            ''
//		            ]);
//		        }
//	        }
//	    }
	    
	    $redis->close();
        return $taskRecordList;
    }
	
	/**
	 * 保存悬赏任务记录
	 *
	 */
	public static function saveRewardTaskRecord($di, $data, $expire)
	{
		$record = new RewardTaskRecord();
		$record->task_id = (int)$data['task_id'];
		$record->op_type = (int)$data['op_type'];
		$record->uid = (int)$data['uid'];
		$record->status = (int)$data['status'];
		$record->op_time = (int)$data['op_time'];
		// 保存
		if($record->save()){
			RedisManager::saveRewardTaskRecord($di, $record, $expire);
		} else {
			return false;
		}
	}
	
	/**
	 * 获取用户群组任务操作间隔时间
	 */
	public static function getUserAssocTaskDuration($redis, $userId, $assocId)
	{
		$associationMember = AssociationMember::findFirst([
			"association_id = ".$assocId. " AND member_id = ".$userId
		]);
		// 不存在返回false
		if (!$associationMember) { return false;}
		
		// 获取用户群组
		$assocMemberTitles = AssociationMemberTitle::findAll($redis);
		// 检查
		if (!$assocMemberTitles) { throw new \RuntimeException(__METHOD__ . $assocMemberTitles->getMessages()[0]); }
		$assocLevel = (int)$associationMember->level;
		return $assocMemberTitles[$assocLevel]['task_limit'];
	}
    
    /**
     * 检查用户是否拥有执行本次任务操作的权限
     *
     */
//	public static function checkUserOpTaskPerm($uid, $rewardTask, $now, $opType, $duration)
    public static function checkUserOpTaskPerm($uid, $rewardTask, $opType)
    {
    	$taskId = $rewardTask['id'];
//    	$createTs = $rewardTask['create_time'];
    	// 最后的记录
	    $taskRecord = RewardTaskRecord::findFirst([
	    	"task_id = " . $taskId. " AND op_type = ".$opType . " AND uid = ".$uid,
		    "order" => "id desc"
	    ]);
	    if ($taskRecord) {
//		    $lastRecord = $lastRecord->toArray();
//	        $lt1 = floor(((int)$lastRecord['op_time'] - $createTs) / $duration);
//	        $lt2 = floor(($now - $createTs) / $duration);
//	        if ($lt1 == $lt2) {
//	        	return false;
//	        }
	        return false;
	    } else {
	    	return true;
	    }
    }
    
    /**
     * 拷贝旧任务数据组成新的任务数据
     *
     */
    public static function copyOldRewardTaskToNew($di, $groupId, $oldRewardTaskData)
    {
        $rewardTask = new RewardTask();
	    $rewardTask->type = 1;
        if ($oldRewardTaskData['type'] == 0) {
            $rewardTask->parent_id = $oldRewardTaskData['id'];
            $rewardTask->title = "";
            $rewardTask->content = "";
            $rewardTask->link = "";
	        $rewardTask->cover_pic = "";
	        $rewardTask->cover_thumb = "";
	        $rewardTask->balance = $oldRewardTaskData['balance'];
        } else {
            $rewardTask->parent_id = 0;
	        $rewardTask->title = $oldRewardTaskData['title'];
	        $rewardTask->content = $oldRewardTaskData['content'];
	        $rewardTask->link = $oldRewardTaskData['link'];
	        $rewardTask->cover_pic = $oldRewardTaskData['cover_pic'];
	        $rewardTask->cover_thumb = $oldRewardTaskData['cover_thumb'];
	        $rewardTask->balance = $oldRewardTaskData['reward_amount'];
        }
	    $rewardTask->click_count = 0;
	    $rewardTask->share_count = 0;
        $rewardTask->reward_amount = $oldRewardTaskData['reward_amount'];
        $rewardTask->click_reward = $oldRewardTaskData['click_reward'];
        $rewardTask->share_reward = $oldRewardTaskData['share_reward'];
		
	    $rewardTask->create_time = $oldRewardTaskData['create_time'];
        $rewardTask->end_time = $oldRewardTaskData['end_time'];
        
        $rewardTask->owner_id = $oldRewardTaskData['owner_id'];
        $rewardTask->group_id = $groupId;
	    $rewardTask->coms_percent = $oldRewardTaskData['coms_percent'];
	    $rewardTask->task_income = $oldRewardTaskData['task_income'];
	
	    $redis = RedisClient::create($di->get('config')['redis']);
//	    var_dump($rewardTask);
	    // 保存数据
	    if(!$rewardTask->save()){ throw new \RuntimeException(__METHOD__.$rewardTask->getMessages()[0]); }
	    // 构建缓存数据
	    $cacheData = $rewardTask->toArray();
	    // 同步保存到Redis中
	    RedisManager::saveRewardTask($redis, $cacheData);
	    $redis->close();
        
        return $rewardTask;
    }
    
    // 检查悬赏任务的请求权限
    public static function checkRewardTaskReqPerm(Di $di, $isAdmin = false, $needTaskID = false, $needGrupId = true)
    {
	    $uid = $_POST['userId'];
	    if (!$uid) { return ReturnMessageManager::buildReturnMessage('E0013', null); }
	    $uid = (int)$uid;
	    // 检查用户是否存在
	    $user = DBManager::getUserById($uid);
	    if (!$user) { return ReturnMessageManager::buildReturnMessage('E0044', null);}
	    $taskId = (int)$_POST['taskId'];
	    $groupId = (int)$_POST['familyId'];
	    if (!$taskId) {
	    	// 如果任务ID必填项, 则抛错
		    if ($needTaskID) {
			    return ReturnMessageManager::buildReturnMessage('E0259', null);
		    }
		    // 如果任务ID非必填项, 则检查是否拥有群组ID, (创建新的悬赏任务没有任务ID但必须有群组ID)
	    	if (!$groupId) {
			    return ReturnMessageManager::buildReturnMessage('E0280', null);
		    }
	    } else {
		    // 获取悬赏任务的缓存, 将数据从MySQL中加载到Redis中
		    $rewardTask = RewardTask::findOne($di, $taskId, $groupId);
		    if(!$rewardTask) { return ReturnMessageManager::buildReturnMessage('E0261'); }
		    // 系统任务, 则使用传入的familyId
		    if ($rewardTask['type'] == 0) {
			    if (!$groupId && $needGrupId) {
				    return ReturnMessageManager::buildReturnMessage('E0159', null);
			    }
		    } else {
			    $groupId = $rewardTask['group_id'];
		    }
	    }
	    // 获取群组
	    if ($needGrupId) {
		    $association = DBManager::getAssociationByGroupId($groupId);
		    if (!$association) { return ReturnMessageManager::buildReturnMessage('E0112', null); }
		    // 检查家族类型群聊
		    if ($association->type == 2) {
		        return ReturnMessageManager::buildReturnMessage('E0120');
		    }
		    // 判断是否是管理员
		    if ($isAdmin) {
			    // 验证用户是否是公会管理员
			    if (!DBManager::checkAssociationUserType($uid, $association->id)) {
				    return ReturnMessageManager::buildReturnMessage('E0175');
			    }
		    } else {
			    // 检查该用户是否该群组的成员
			    if(!DBManager::existAssociationMember($association->id, $uid)) {
				    return ReturnMessageManager::buildReturnMessage('E0266');
			    }
		    }
	    } else {
	    	$association = null;
	    }
	    // 获取用户任务总金额
        $amount = self::getUserTaskAmount($di, $uid);
        $rewardTask['user_amount'] = $amount ? $amount : 0;
	    // 返回结果
        Return [
        	'group_id' =>$groupId,
            'uid' => $uid,
	        'user_data' => $user,
	        'association' => $association,
	        'task_id' => $taskId,
	        'reward_task' => $rewardTask
        ];
    }

    // 获取用户任务总金额
    public static function getUserTaskAmount(Di $di, $userId) {
        $sql = "select sum(if(r.op_type = 1, t.click_reward, share_reward)) as amount
              from Fichat\Models\RewardTask t, Fichat\Models\RewardTaskRecord r
              where t.id = r.task_id and uid = $userId and r.status = 1";
        $query = new Query($sql, $di);
        $amount =  $query->execute();
        return $amount[0]['amount'];
    }
    
    // 获取任务历史记录
	public static function getGroupRewardTaskHistory(Di $di, $groupId, $pageindex = 1)
	{
		$startPos = ($pageindex - 1) * PAGE_SIZE;
		$sql = "SELECT * FROM Fichat\Models\RewardTask WHERE type = 1 AND group_id = ".$groupId." ORDER BY id DESC LIMIT ".$startPos.",".PAGE_SIZE;
		$query = new Query($sql, $di);
		// 历史任务列表
		$historyTaskList = $query->execute();
		if ($historyTaskList) {
			// 获取历史任务列表
			$historyTaskList = $historyTaskList->toArray();
			$historyTaskList = Self::addParentTaskInfo($di, $historyTaskList);
			return ReturnMessageManager::buildRewardTasks($historyTaskList);
		} else {
			return [];
		}
	}
	
	// 获取当前执行系统悬赏任务
	public static function getNowRunSystemRewardTask($redis)
	{
		// 获取所有的系统任务的Key
		$matchFmt = RedisClient::rewardTaskKeyMatchFmt(0);
		$keys = $redis->keys($matchFmt);
		// 批量获取数量
		$cacheTasks = RedisClient::mHgetAll($redis, $keys);
		
		// 获取已拿到了数据的ID
		$cachedIDS = array();
		$evoCacheTasks = array();
		// 循环
		foreach($cacheTasks as $key => $task) {
			// 获取ID
			$id = RedisClient::getKeyId($key);
			if ($id) {
				array_push($cachedIDS, $id);
			}
			$task['id'] = $id;
			array_push($evoCacheTasks, $task);
		}
		if ($cachedIDS) {
			$limitCond = "AND id NOT IN (". self::arrayToIds($cachedIDS) .")";
		} else {
			$limitCond = "";
		}
		// 获取正在执行的系统任务
		$sysTaskList = RewardTask::find([
			"type = 0 AND status = 1 ".$limitCond,
			"order" => "id desc"
		]);
		if ($sysTaskList) {
			$sysTaskList = $sysTaskList->toArray();
			return ReturnMessageManager::buildRewardTasks(array_merge($sysTaskList, $evoCacheTasks));
		} else {
			return [];
		}
	}
	
	public static function arrayToIds($array_ids)
	{
		$ids = '';
		foreach($array_ids as $id)
		{
			if ($ids == '') {
				$ids = $id;
			} else {
				$ids .= "," . $id;
			}
		}
		return $ids;
	}
	
	public static function addParentTaskInfo($di, $rewardTasks)
	{
		if ($rewardTasks) {
			// 查找所有的parentTaskId
			$parentTaskIds = array();
			foreach($rewardTasks as $rewardTask) {
				array_push($taskIds, $rewardTask['id']);
				$parentTaskId = (int)$rewardTask['parent_id'];
				if ($parentTaskId != 0) {
					if (!in_array($parentTaskId, $parentTaskIds)) {
						array_push($parentTaskIds, $parentTaskId);
					}
				}
			}
			// 获取系统任务ID
			$systemRewardTasks = Self::getSystemRewardTask($di, $parentTaskIds);
			// 更新数据
			foreach($rewardTasks as $idx => $rewardTask) {
				foreach($systemRewardTasks as $systemRewardTask) {
					if ($systemRewardTask['id'] == $rewardTask['parent_id']) {
						$rewardTasks[$idx]['title'] = $systemRewardTask['title'];
						$rewardTasks[$idx]['content'] = $systemRewardTask['content'];
						$rewardTasks[$idx]['link'] = $systemRewardTask['link'];
						$rewardTasks[$idx]['cover_pic'] = $systemRewardTask['cover_pic'];
						$rewardTasks[$idx]['cover_thumb'] = $systemRewardTask['cover_thumb'];
					}
				}
			}
			
		}
		return $rewardTasks;
	}
	
	/**
	 * $taskIds : [$taskId=>$groupId, $taskId=>$groupId]
	 */
	public static function dumpRewardTask($di, $taskIds)
	{
		// 任务数据
		$redis = RedisClient::create($di->get('config')['redis']);
		
		$keys = [];
		foreach ($taskIds as $taskId => $groupId)
		{
			$key = RedisClient::rewardTaskKey($groupId, $taskId);
			array_push($keys, $key);
		}
		// 批量获取数据
		$cacheTasks = RedisClient::mHgetAll($redis, $keys);
		foreach($cacheTasks as $key => $task) {
			// 获取ID
			$id = RedisClient::getKeyId($key);
			if ($id) {
				array_push($cachedIDS, $id);
			}
			$task['id'] = $id;
			if ($task['parent_id'] != 0) {
				$task['title'] = '';
				$task['content'] = '';
				$task['link'] = '';
				$task['cover_pid'] = '';
				$task['cover_thumb'] = '';
				$task['reward_amount'] = 0;
				$task['click_reward'] = 0;
				$task['share_reward'] = 0;
			}
			// 构建任务对象
			$rewardTask = new RewardTask();
			$rewardTask->assign($task);
//			$rewardTask = RewardTask::fromArray($task);
			// 保存任务
			$rewardTask->save();
		}
		$redis->close();
	}
	
	/**
	 * 退还任务金额
	 *
	 * @params $redPacketList
	 */
	public static function returnRewardTaskList($di, $rewardTasks)
	{
		foreach ($rewardTasks as $rewardTask) {
			// 红包未抢完,余额大于0
			if ($rewardTask->balance > 0) {
				// 获取用户信息,退还金额
				$user = self::getUserById($rewardTask->owner_id);
				$balance = $rewardTask->balance;
				self::updateUserBalance($user, $balance, 1);
				$rewardTask->balance = 0;
				$rewardTask->status = 2;
				// 修改红包状态
				$rewardTask->save();
				// 生成红包退还订单
				$orderId = Utils::generateOrderId($rewardTask->owner_id);
				$orderData = [
					'user_id' => $user->id,
					'order_num' => $orderId,
					'amount' => $balance,
					'balance' => $user->balance,
					'status' => 0,
					'consum_type' => PAYOP_TYPE_RETURN_TASK,
					'create_date' => time(),
					'pay_channel' => PAY_CHANNEL_BL,
					'pay_account' => '',
					'remark' => '任务余额退还'
				];
				$orderInfo = KakaPay::createUserOrder($orderData);
                // 发送环信信息
                MessageSender::sendReturnRewardTask($di, $user, $rewardTask, $orderInfo);
				// 生成余额订单
				KakaPay::createBalanceRecord($user->id, PAYOP_TYPE_RETURN_TASK, $balance, $rewardTask->id, $orderInfo['id']);
			}
		}
		return true;
	}
	
	/**
	 * 获取创建用户的参数
	 *
	 */
	public static function getCreateUserParams($di, $needsPhone = true)
	{
		// 手机号
		$phone = trim($_POST['phone']);
		$openid = trim($_POST['openid']);
        $uid = trim($_POST['uid']);
        $nickname = trim($_POST['nickname']);
		// 保柱说性别随机
		$gender = rand(1, 2);
		$wx_avatar = '';
		// 验证手机号
		if ($needsPhone) {
			if(!$phone){ return ReturnMessageManager::buildReturnMessage('E0001'); }
			if(!preg_match("/^1(3|4|5|7|8)\d{9}$/",$phone)) {
				return ReturnMessageManager::buildReturnMessage('E0004');
			}
			$password = Utils::makePassword($di, $phone);
		} else {
		    // 检查微信必填参数
            if(!$uid){ return ReturnMessageManager::buildReturnMessage('E0093'); }
            if(!$openid){ return ReturnMessageManager::buildReturnMessage('E0007'); }
            // 检查昵称
			if(!$nickname) { return ReturnMessageManager::buildReturnMessage('E0011');}
			// 检查性别
			$gender = (int)$_POST['gender'];
			if (!$gender) { return ReturnMessageManager::buildReturnMessage('E0012');}
			// 头像检查
			$wx_avatar = trim($_POST['wx_avatar']);
			if (!$wx_avatar) { return ReturnMessageManager::buildReturnMessage('E0008');}
			// 校验是否是正确的连接地址
			if (!Utils::vaildLink($wx_avatar)) {
				return ReturnMessageManager::buildReturnMessage('E0285');
			}
            $password = Utils::makePassword($di, $uid);
		}
		// 返回参数
		return [
            'uid' => $uid,
			'openid' => $openid,
			'account_id' => 0,
			'password' => $password,
			'phone' => $phone,
			'nickname' => $nickname,
			'gender' => $gender,
			'user_avatar' => '',
			'wx_avatar' => $wx_avatar,
			'birthday' => '1993-12-26'
		];
	}
	
	/** 标签 ----------------------------------------------------------------------- 开始 */
	
	/**
	 * 获取系统推荐标签
	 *
	 */
	public static function getSystemRecommandTags()
	{
		$tags = Tag::find(["sys_rcmd = 1", "order" => "id desc"]);
		if (!$tags) {
			Utils::throwDbException($tags);
		}
		Return $tags->toArray();
	}
	
	/**
	 * 根据用户标签获取系统推荐的标签
	 *
	 */
	public static function getSystemRcmdByUserTags($userTags)
	{
		$userTagIds = '';
		$userSelectTagIds = [];
		if ($userTags) {
			// 获取用户已经获得的标签
			foreach ($userTags as $tag) {
				// 推ID到用户已经选中的
				$userSelectTagIds[$tag['id']] = $tag['tag'];
				if ($userTagIds == '') {
					$userTagIds = $tag['id'];
					
				} else {
					$userTagIds .= ','.$tag['id'];
				}
			}
		}
		// 获取用户标签ID
		if ($userTagIds) {
			$tags = Tag::find([
				"id not in (".$userTagIds.") AND sys_rcmd = 1"
			]);
		} else {
			$tags =  Tag::find(["sys_rcmd = 1"]);
		}
		$tags = $tags->toArray();
		// 检查用户数据, 然后随机截取
		return ReturnMessageManager::buildUserRcmdTags($userSelectTagIds, $tags);
	}
	
	/**
	 * 获取家族所有的标签
	 *
	 */
	public static function getAssociationTags($id)
	{
		$assocTags = AssociationTag::find("group_id = ".$id);
		if ($assocTags) {
			return $assocTags;
		} else {
			return false;
		}
	}
	
	/**
	 * 根据用户的标签获取用户列表
	 *
	 */
	public static function getRcmdUserByUserTags($di, $uid, $followIdsArr)
	{
		// 将自己的ID添加进去
		array_push($followIdsArr, $uid);
		// 构建
		$notInIds = '';
		foreach($followIdsArr as $followUid){
			if ($notInIds == '') {
				$notInIds = $followUid;
			} else {
				$notInIds .= ','.$followUid;
			}
		}
		// 构建索引范围语句
		$rangeUidsSql = '';
		$userTags = Self::getUserTags($uid);
		$userTagIds = '';
		
		foreach($userTags as $userTag) {
			if ($userTagIds == '') {
				$userTagIds = $userTag->tag_id;
			} else {
				$userTagIds .= ','.$userTag->tag_id;
			}
		}
		if ($userTagIds) {
			if ($notInIds) {
				$rangeUidsSql = 'SELECT uid FROM Fichat\Models\UserTag WHERE tag_id in ('.$userTagIds.') AND uid not in ('.$notInIds.')';
			} else {
				$rangeUidsSql = 'SELECT uid FROM Fichat\Models\UserTag WHERE tag_id in ('.$userTagIds.')';
			}
//			$rangeUidsSql = 'SELECT uid FROM Fichat\Models\UserTag WHERE tag_id in ('.$userTagIds.')';
//			Utils::echo_debug($rangeUidsSql);
			$phpl = "SELECT u.*, SUM(rp.balance) as sum_balance FROM Fichat\Models\User as u, Fichat\Models\RedPacket as rp WHERE u.id = rp.user_id ".
				"AND rp.balance > 0 AND rp.status = 0 ".
				"AND u.id in (".$rangeUidsSql.") GROUP BY u.id LIMIT 0, 20";
			$query = new Query($phpl, $di);
			$users = $query->execute();
			return ReturnMessageManager::buildRcmdUserList($di, $users);
		} else {
			return [];
		}
	}
	
	/**
	 * 获取用户关注的用户ID列表
	 *
	 */
	public static function getFollowUserIds($uid)
	{
		$attentions = Attention::find([
			"user_id = ".$uid
		]);
		$followIds = [];
		if ($attentions) {
			foreach ($attentions as $attention) {
				array_push($followIds, $attention->target_id);
			}
		}
		return $followIds;
	}
	
	
	/**
	 * 获取用户所有的标签
	 *
	 */
	public static function getUserTags($uid)
	{
		$userTags = UserTag::find("uid = ".$uid);
		if ($userTags) {
			return $userTags;
		} else {
			return [];
		}
	}
	
	
	public static function getUserTagsWithTag($di, $uid)
	{
		$phpl = "SELECT ut.*, t.tag FROM Fichat\Models\UserTag as ut, Fichat\Models\Tag as t"
				." WHERE ut.tag_id = t.id AND ut.uid = ".$uid;
		$query = new Query($phpl, $di);
		$data = $query -> execute();
		if ($data) {
			return $data->toArray();
		} else {
			return [];
		}
	}

	// 获取推荐用户的可领取金额
	public static function getUserCanGrabAmount($di, $uid) {
	    $phql = "select sum(balance) amount from Fichat\Models\RedPacket r where status = 0 and invalid = 0 and r.id in (select red_packet_id from Fichat\Models\Moments m where m.user_id = $uid)";
        $query = new Query($phql, $di);
        $data = $query -> execute();
        if ($data[0]['amount']) {
            return $data[0]['amount'];
        } else {
            return 0;
        }
    }

    // 获取推荐家族的可领取金额
    public static function getFamilyCanGrabAmount($di, $groupId) {
        $phql = "select sum(balance) amount from Fichat\Models\RewardTask where status = 1 and parent_id = 0 and group_id = $groupId";
        $query = new Query($phql, $di);
        $data = $query -> execute();
        if ($data[0]['amount']) {
            return $data[0]['amount'];
        } else {
            return 0;
        }
    }
	
	/**
	 * 检查TagID是否都存在
	 *
	 */
	public static function checkExistTagIDS($tagIds)
	{
		// 用户标签
		$tags = Tag::find("id in (". $tagIds .")");
		if ($tags) {
			$returnTags = [];
			foreach($tags as $tag) {
				array_push($returnTags, $tag->id);
			}
			return $returnTags;
		} else {
			return [];
		}
	}
	
	/**
	 * 更新用户Tag
	 * @params $type, 1: user, 2: association
	 */
	public static function updateTags($id, $tags, $type = 1)
	{
		if ($tags && $tags != '') {
			// 检验有效的标签ID
			$tagIds = Self::checkExistTagIDS($tags);
			// 检查标签
			if ($type == 1) {
				$targetTags = DBManager::getUserTags($id);
			} else {
				$targetTags = DBManager::getAssociationTags($id);
			}
			$targetTagIds = [];
			foreach ($targetTags as $tag){
				array_push($targetTagIds, $tag->tag_id);
			}
			// 更新
			foreach($tagIds as $tagId) {
				// 添加已经加入的
				if (!in_array($tagId, $targetTagIds)) {
					if ($type == 1) {
						$targetTag = new UserTag();
						$targetTag->uid = $id;
					} else {
						$targetTag = new AssociationTag();
						$targetTag->group_id = $id;
					}
					// 更新
					$targetTag->tag_id = $tagId;
					$targetTag->save();
				}
			}
			// 删除
			foreach($targetTags as $targetTag) {
				if (!in_array($targetTag->tag_id, $tagIds)) {
					$targetTag->delete();
				}
			}
		}
	}
	
	/**
	 * 获取系统热门列表
	 *
	 */
	public static function getSysHotList($di, $uid, $startPos)
	{
		// 获取符合条件的总条数
		$maxCount = SystemHot::count([
			'expo_num > 0 and type = 3',
			'order' => 'hot_num DESC'
		]);

		// 根据总条数计算随机开始的记录诶之
        if($startPos == -1) {
            if ($maxCount) {
                if ($maxCount < 10) {
                    $startPos = 0;
                } else {
                    $startPos = rand(0, $maxCount - 1);
                }
            } else {
                $startPos = 0;
            }
        } else {
            $startPos = $startPos > $maxCount-1 ? $startPos : $startPos + 20;
        }

		$pageSize = 20;
		// 获取热门中的数据
		$phpl = 'SELECT * FROM Fichat\Models\SystemHot WHERE expo_num > 0 AND type = 3 ORDER BY hot_num DESC LIMIT '.$startPos.','.$pageSize;
		$query = new Query($phpl, $di);
		$data = ReturnMessageManager::buildHotList($di, $uid, $query->execute());
		return ReturnMessageManager::buildReturnMessage('E0000', ['hot_list' => $data, 'startIndex' => $startPos]);
	}

	// 根据触发id获取热门记录
	public static function getSystemHotByTriggerId($triggerId) {
        $sysHot = SystemHot::findFirst("trigger_id = ".$triggerId);
        return $sysHot;
    }
	
	
	/**
	 * 获取系统活跃的数据
	 *
	 */
//	public static function getSysDynList($di, $uid, $pageIndex)
//	{
////		$startPos = ($pageIndex - 1) * PAGE_SIZE;
//		
//		$data = [];
//		// 获取玩家加入的所有的家族group_id
//		$sql = "SELECT DISTINCT(a.group_id) as gid FROM Fichat\Models\Association as a, Fichat\Models\AssociationMember as am WHERE am.member_id = ".$uid." AND a.id = am.association_id";
//		$query = new Query($sql, $di);
//		$groups = $query->execute();
//		// 构建家族ID列表
//		$assocIds = '';
//		foreach ($groups as $group) {
//			if ($assocIds == '') {
//				$assocIds = $group->gid;
//			} else {
//				$assocIds .= ','.$group->gid;
//			}
//		}
//		if ($assocIds != '') {
//			$where = 'WHERE (type = 1 AND group_id in ('.$assocIds.'))';
//		}
//
////		if ($assocIds != '') {
////			$sql = 'SELECT r.*, a.id as familyId, a.nickname as familyName, a.assoc_avatar as familyAvatar, a.level as familyLevel '
////				.'FROM Fichat\Models\RewardTask as r, Fichat\Models\Association as a '
////				.'WHERE r.group_id = a.group_id AND a.id in ('.$assocIds.') ORDER BY r.id DESC LIMIT 0,'.PAGE_SIZE;
////			$query = new Query($sql, $di);
////			$data = ReturnMessageManager::buildDynTaskList($query->execute(), $data);
////		} else {
////			$data = [];
////		}
//		
//		// 获取玩家加入的所有的家族group_id
//		$sql = "SELECT DISTINCT(target_id) as id FROM Fichat\Models\Attention WHERE user_id = ".$uid;
//		$query = new Query($sql, $di);
//		$attentions = $query->execute();
//		$sql = "SELECT DISTINCT(friend_id) as id FROM Fichat\Models\Friend WHERE user_id = ".$uid;
//		$query = new Query($sql, $di);
//		$friends = $query->execute();
//		// 构建家族ID列表
//		$userIdList = [];
//		foreach ($attentions as $attention) {
//			array_push($userIdList, $attention->id);
//		}
//		foreach ($friends as $friend) {
//			if (!in_array($friend->id, $userIdList)){
//				array_push($userIdList, $friend->id);
//			}
//		}
//		// 构建家族ID列表
//		$userIds = ''.$uid;
//		foreach ($userIdList as $userId) {
//			$userIds .= ','.$userId;
//		}
//		// 检查是否是空的
//		if ($userIds != '') {
//			if ($where) {
//				$where .= ' OR (type = 2 AND uid in ('.$userIds.'))';
//			} else {
//				$where .= 'WHERE (type = 2 AND uid in ('.$userIds.'))';
//			}
//			$startPos = ($pageIndex - 1) * PAGE_SIZE;
//			// 获取热门中的数据
//		}
//		if ($where != '') {
//			$phpl = 'SELECT * FROM Fichat\Models\SystemDyn '.$where.' ORDER BY id DESC LIMIT '.$startPos.','.PAGE_SIZE;
////			$sql = 'SELECT m.*, u.id as userId, u.nickname as nickname, u.user_avatar as userAvatar '
////				. 'FROM Fichat\Models\Moments as m, Fichat\Models\User as u '
////				. 'WHERE m.user_id = u.id AND m.user_id in (' . $userIds . ')';
//			$query = new Query($phpl, $di);
//			$data = ReturnMessageManager::buildDynList($di, $uid, $query->execute());
//		} else {
//			$data = [];
//		}
//		return ReturnMessageManager::buildReturnMessage('E0000', ['dyn_list' => $data]);
//	}
	
	/**
	 * 获取系统活跃的数据
	 *
	 */
	public static function getSysDynList($di, $uid, $type, $pageIndex)
	{
		$startPos = ($pageIndex - 1) * PAGE_SIZE;
		
		$data = [];
		switch ($type) {
			case 1:
			    $targets = UserRelationPerm::find([
                    "conditions" => "user_id = ".$uid." AND rtype in (1, 3)",
                    "columns" => "target_id, user_id"
                ]);
//				$targets = Friend::find([
////					"conditions" => "(user_id = ".$uid." AND is_look = 1) OR (friend_id = ".$uid." AND forbid_look = 1)",
//					"conditions" => "user_id = ".$uid." AND is_look = 1",
////					"columns" => "friend_id as target_id, user_id, is_look, forbid_look"
//					"columns" => "friend_id as target_id, user_id"
//				]);
				$momCondtion = "friend = 1";
				break;
			case 2:
				// 获取所有的关注圈用户
                $targets = UserRelationPerm::find([
                    "conditions" => "user_id = ".$uid." AND rtype in (2, 3)",
                    "columns" => "target_id, user_id"
                ]);
//				$targets = Attention::find([
////					"conditions" => "(user_id = ".$uid." AND is_look = 1) OR (target_id =".$uid." AND forbid_look = 1)",
//					"conditions" => "user_id = ".$uid." AND is_look = 1",
////					"columns" => "target_id as target_id, user_id, is_look, forbid_look"
//					"columns" => "target_id as target_id, user_id"
//				]);
				$momCondtion = "attention = 1";
				break;
			default:
				$targets = [];
		}
		// 如果没有拉取数据, 则返回空
		if (!$targets) {
			return $data;
		}
		$targetIds = '';
		if (!is_array($targets) && $targets) {
			$targets = $targets->toArray();
			$targetIdsArr = [];
			foreach($targets as $target) {
				if (!in_array($target['target_id'], $targetIdsArr)) {
					$pushId = $target['target_id'];
                    array_push($targetIdsArr, $pushId);
				}
				if($type == 1 && !in_array($target['user_id'], $targetIdsArr)) {
                    $pushId = $target['user_id'];
                    array_push($targetIdsArr, $pushId);
                }
			}
			// 构建说说的用户范围
			foreach($targetIdsArr as $targetId) {
				if ($targetIds) {
					$targetIds .= "," . $targetId;
				} else {
					$targetIds .= $targetId;
				}
			}
		}
		if (!$targetIds) {
			$targetIds = '0';
		}
		$momCondtion .= ' AND m.user_id in ('.$targetIds.') GROUP BY m.id';
		// 获取说说/红包
		$momSql = 'SELECT m.*, r.*, COUNT(DISTINCT ml.id) as likeCount, COUNT(DISTINCT mr.id) as replyCount'
				  .' ,u.user_avatar as userAvatar, u.level as userLevel, u.nickname as userNickname FROM'
				  .' Fichat\Models\Moments as m LEFT JOIN Fichat\Models\MomentsLike as ml'
				  .' ON m.id = ml.moments_id LEFT JOIN Fichat\Models\MomentsReply as mr ON m.id = mr.moments_id and mr.status = 0'
				  .' LEFT JOIN Fichat\Models\RedPacket as r ON m.red_packet_id = r.id'
				  .' LEFT JOIN Fichat\Models\User as u ON m.user_id = u.id'
				  .' WHERE '.$momCondtion . ' ORDER BY m.id DESC LIMIT '.$startPos.', '.PAGE_SIZE;
		$query = new Query($momSql, $di);
		$moments = $query->execute();
		if ($moments && count($moments)) {
			$momentsIdList = [];
			$likeConditions = '';
			// 构建所有的说说ID
			foreach($moments as $moment)
			{
				$mid = $moment->m->id;
				array_push($momentsIdList, $mid);
				if ($likeConditions) {
					$likeConditions .= ','.$mid;
				} else {
					$likeConditions = $mid;
				}
			}
			$likeConditions = 'moments_id in ('.$likeConditions.') AND user_id = '.$uid;
			$likeMoments = MomentsLike::find([
				"conditions" => $likeConditions,
				"columns" => "moments_id as like_mid"
			]);
			// 循环
			$likeMids = [];
			if ($likeMoments) {
				foreach($likeMoments as $likeMoment) {
					array_push($likeMids, $likeMoment->like_mid);
				}
			}
			$data = ReturnMessageManager::buildDynList($uid, $moments, $likeMids);
		}
		return ReturnMessageManager::buildReturnMessage('E0000', ['dyn_list' => $data]);
	}
	
	
	// 保存用户消息
	public static function sendUserMessage($di, $uid, $fromUid, $type, $extParams = [])
	{
		try {
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			$extParamsJson = serialize($extParams);
			$where = "user_id = ".$uid." AND from_id = ".$fromUid. " AND type=".$type. " AND ext_params ='".$extParamsJson."'";
			$userMsg = UserMsg::findFirst($where);
			if ($userMsg) {
				$userMsg->setTransaction($transaction);
				$userMsg->status = 1;
				$userMsg->update_time = date('Y-m-d H:i:s');
			} else {
				$userMsg = new UserMsg();
				$userMsg->setTransaction($transaction);
				$userMsg->type = $type;
				$userMsg->ext_params = $extParamsJson;
				$userMsg->from_id = $fromUid;
				$userMsg->user_id = $uid;
				$userMsg->status = 1;
				$userMsg->update_time = date('Y-m-d H:i:s');
			}
			if (!$userMsg->save()) {
				$transaction->rollback();
			}
            // 发送透传消息
            // $hxConfig = $di->get('config')['hxConfig'];
			// HxChatProxy::sendSilenceMessage([$uid], 'uMessage', $hxConfig);
			return true;
		} catch (TcFailed $e) {
			return false;
		}
	}
	
	// 保存用户消息
	public static function sendUserMessages($di, $uid, $fromUids, $type, $extParams = [])
	{
		try {
			$transaction = $di->getShared(SERVICE_TRANSACTION);
			$extParamsJson = json_encode($extParams);
			// 循环
			foreach ($fromUids as $fromUid) {
				$where = "user_id = ".$uid." AND from_id = ".$fromUid. " AND type=".$type. " AND ext_params ='".$extParamsJson."'";
				$userMsg = UserMsg::findFirst($where);
				if ($userMsg) {
					$userMsg->setTransaction($transaction);
					$userMsg->status = 1;
					$userMsg->update_time = time();
				} else {
					$userMsg = new UserMsg();
					$userMsg->setTransaction($transaction);
					$userMsg->type = $type;
					$userMsg->ext_params = $extParamsJson;
					$userMsg->from_id = $fromUid;
					$userMsg->user_id = $uid;
					$userMsg->status = 1;
					$userMsg->update_time = time();
				}
				if (!$userMsg->save()) {
					$transaction->rollback();
				}
			}
			return true;
		} catch (TcFailed $e) {
			return false;
		}
	}

    /**
     * 获取系统配置
     * @return Model
     */
	public static function getSystemConfig() {
	    return SystemConfig::findFirst();
    }

    /**
     * 兑换咖米
     * @param $di
     * @param $user
     * @param $diamond
     * @return bool
     */
    public static function exchangeKaMi($di, $user, $diamond) {
	    try {
            $transaction = Utils::getDiTransaction($di);
            $user->setTransaction($transaction);

            $user->diamond -= $diamond;
            $user->balance += $diamond;

            if(!$user->save()) {
                $transaction->rollback();
            }

            return true;
        } catch (TcFailed $e) {
            return false;
        }
    }

    /**
     * 更新订单状态
     * @param $di
     * @param $order
     * @return bool
     */
    public static function updateOrderStatus($di, $order) {
        try {
            $transaction = Utils::getDiTransaction($di);
            $order->setTransaction($transaction);
            $order->status  = 1;
            if(!$order->save()) {
                $transaction->rollback();
            }
            return true;
        } catch (TcFailed $e) {
            return false;
        }
    }

    /**
     * 更新用户的钻石数
     * @param $di
     * @param $user
     * @param $amount
     * @return bool
     */
    public static function updateDiamond($di, $user, $amount) {
        try {
            $transaction = Utils::getDiTransaction($di);
            $user->setTransaction($transaction);
            $user->diamond += $amount;
            if(!$user->save()) {
                $transaction->rollback();
            }
            return true;
        } catch (TcFailed $e) {
            return false;
        }
    }

    /**
     * 保存兑换记录
     * @param $di
     * @param $uid
     * @param $amount
     * @return bool
     */
    public static function saveExchangeKaMiRecord($di, $uid, $amount, $code) {
        try {
            $transaction = Utils::getDiTransaction($di);
            $record = new ExchangeKaMiRecord();
            $record->setTransaction($transaction);
            $record->code = $code;
            $record->uid = $uid;
            $record->amount = $amount;
            $record->create_time = date('Y-m-d H:i:s');
            if(!$record->save()) {
                $transaction->rollback();
            }
            return $record;
        } catch (TcFailed $e) {
            return false;
        }
    }
    
    public static function getTaskStatus(RewardTask $task) {
        if ($task->click_count == $task->total_click_count && $task->share_count == $task->total_share_count && time() >= $task->end_time)
        {
            return TASK_STATUS_DONE;
        } else {
        	return $task->status;
        }
    }
	
	// 检查查看动态的权限
//	public static function checkLookDynRights($uid, $targetId, $dynFriends)
//	{
//		$ret = true;
//		foreach($dynFriends as $friend) {
//			if (($friend['user_id'] == $uid && $friend['target_id']== $targetId) || ($friend['target_id'] == $uid && $friend['user_id']== $targetId)) {
//				// $type=1: 检查有没有
//				// $type=2:
//				if ($friend['forbid_look'] == 2 || $friend['is_look'] == 2) {
//					$ret = false;
//					break;
//				}
//			}
//		}
//		return $ret;
//	}
	
    
 
}
