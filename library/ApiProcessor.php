<?php
namespace Fichat\Library;

use Fichat\Common\RedisManager;
use Fichat\Common\ReturnMessageManager;
use Fichat\Common\DBManager;
use Fichat\Models\AssociationMember;
use Fichat\Models\BalanceFlow;
use Fichat\Models\Feedback;
use Fichat\Models\Files;
use Fichat\Models\Friend;
use Fichat\Models\OssFdelQueue;
use Fichat\Models\RedPacket;
use Fichat\Models\Report;
use Fichat\Models\RewardTask;
use Fichat\Models\RewardTaskRecord;
use Fichat\Models\SystemHot;
use Fichat\Models\User;
use Fichat\Models\UserRelationPerm;
use Fichat\Models\UserOrder;
use Fichat\Models\UserTag;
use Fichat\Proxy\AlipayProxy;
use Fichat\Proxy\OssProxy;
use Fichat\Proxy\SmsProxy;
use Fichat\Proxy\SmsHuaWeiProxy;
use Fichat\Proxy\WXBizDataCrypt;
use Fichat\Proxy\WxLoginProxy;
use Fichat\Proxy\WxPayProxy;

use Fichat\Utils\EmptyObj;
use Fichat\Utils\KaException;
use Fichat\Utils\MessageSender;
use Fichat\Utils\KakaPay;
use Fichat\Utils\Utils;
use Phalcon\Config;
use Phalcon\Db;
use Phalcon\Debug;
use Phalcon\Exception;
use Phalcon\Mvc\Model\Query;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;

use Fichat\Utils\RedisClient;
use Fichat\Utils\OssApi;
use Fichat\Utils\Emoji;

class ApiProcessor {

    /*
     *  TODO 微信API接口
     */
	/** 微信登录 */
	public static function wxLogin($di)
	{
		try {
			$code = $_POST['code'];
			error_log('wx post code:'.$code);
			$verfiyRs = WxLoginProxy::loginVerify($di, $code);
			if (!$verfiyRs) {
				return ReturnMessageManager::buildReturnMessage(ERROR_LOGIN_VERIFY);
			}
			// 获取事务
			$transaction = $di->get(SERVICE_TRANSACTION);
			$openid = $verfiyRs['openid'];
			$sessionKey = $verfiyRs['session_key'];
			$unionid= '';
			if ($verfiyRs['unionid']) {
				$unionid = $verfiyRs['unionid'];
			}
			// 检查用户是否存在, 不存在创建新用户
			$user = User::findFirst("openid = '".$openid."'");
			$now = time();
			$token = Utils::makeToken($openid);
			$tokenSignTime = $now + TOKEN_KEEP;
			if ($user) {
				$loginStatus = LOGIN_STATUS_LOGIN;
			} else {
				$loginStatus = LOGIN_STATUS_REG;
				// TODO 执行用户注册的操作
				$user = new User();
				$user->create_time = $now;
			}
			
//			var_dump($verfiyRs);
			$user->setTransaction($transaction);
			$user->session_key = $sessionKey;
			$user->openid = $openid;
			$user->unionid = $unionid;
			$user->token = $token;
			$user->token_sign_time = $tokenSignTime;
			$user->update_time = $now;
			// 保存数据
			if(!$user->save()) {
				$transaction->rollback();
			}
			$data = ['token'=>$token, 'uid'=>$user->id, 'login_status' => $loginStatus];
			// 事务提交
			return Utils::commitTcReturn($di, $data, ERROR_SUCCESS);
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}
	
	/** 更新微信用户资料 */
	public static function wxInitUserInfo($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$iv = $_POST['iv'];
			$encryptedData = $_POST['encryptedData'];
			// 获取事务
//			$gd = Utils::getService($di, SERVICE_GD);
//			$uid = $gd->uid;
			
			$user = User::findFirst("id = ".$uid);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage(ERROR_NO_USER);
			}
			$transaction = $di->get(SERVICE_TRANSACTION);
			$wxAppConfig = $di->getShared('config')['wxminiapp'];
			
			// 解密
			$wxCrypto = new WXBizDataCrypt($wxAppConfig['app_id'], $gd->sessionKey);
			if ($wxCrypto->decryptData($encryptedData, $iv, $data) != 0) {
				return ReturnMessageManager::buildReturnMessage(ERROR_WX_DECRYPT);
			}
			
//			var_dump($data);
			// 捷豹数据
			$data = json_decode($data, true);
//			var_dump($data['nickName']);
			// 保存数据
			$user->setTransaction($transaction);
			$user->nickname = $data['nickName'];
			$user->gender = $data['gender'];
			$user->wx_avatar = $data['avatarUrl'];
			if (!$user->save()) {
				$transaction->rollback();
			}
			// 删除敏感信息
			unset($data['openid']);
			unset($data['watermark']);
//			echo "execute success !!!";
			// 事务提交
			return Utils::commitTcReturn($di, $data, 'E0000');
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	/** 推送微信支付客服消息 */
	public static function pushWxClientPayMsg($di)
	{
		try {
			$config = Utils::getService($di, SERVICE_CONFIG)->toArray();
			$wxConfig = $config[CONFIG_KEY_WXMINI];
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			// 用户数据
			$user = User::findFirst("id = ".$uid);
			
			$authUrl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$wxConfig['app_id'].'&secret='.$wxConfig['app_key'];
			$authRs = json_decode(Utils::http_get($authUrl), true);
			// 检查是否抛错
			if (array_key_exists('errcode', $authRs))
			{
				return ReturnMessageManager::buildReturnMessage(ERROR_WX_AUTH);
			}
			
			$accessToken = $authRs['access_token'];
			// 发送消息
			$msgUrl = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$accessToken;
			$msgData = [
				'touser' => $user->openid,
				'msgtype' => 'text',
				'text' => [
					'content' => 'www.baidu.com'
				]
			];
			echo $msgUrl;
			$msgRs =  json_decode(Utils::http_post($msgUrl, $msgData), true);
			var_dump($msgRs);
//			json_decode(Utils::)
			
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	/** 获取用户的 */
	public static function getUserInfoByUnionId($di)
	{
		try {
			$unionId = $_POST['union_id'] ? trim($_POST['union_id']) : false;
			if (!$unionId) {
				return ReturnMessageManager::buildReturnMessage(ERROR_NO_USER);
			}
			$user = User::findFirst([
				"conditions" => " unionid='".$unionId."' "
			]);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage(ERROR_NO_USER);
			}
			$data = [
				'uid' => $user->id,
				'nickname' => $user->nickname,
				'wx_avatar' => $user->wx_avatar
			];
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['info'=>$data]);
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}
	
	/** 获取用户信息 */
	public static function getUserInfo($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			
			$user = User::findFirst($uid);
			
			$data = [
				'id' => $user->id,
				'nickname' => $user->nickname,
				'gender' => $user->gender,
				'avatar' => $user->wx_avatar,
				'balance' => $user->balance
			];
			return Utils::commitTcReturn($di, $data, 'E0000');
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 检查余额是否足够
	public static function checkBalance($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$user = User::findFirst("id = ".$uid);
			
			$needMoney = $_POST['needMoney'] ? intval($_POST['needMoney']) : 0;
			if ($user->balance < $needMoney) {
				return ReturnMessageManager::buildReturnMessage(ERROR_MONEY);
			}
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['balance' => $user->balance]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 拉取悬赏任务列表
	public static function loadTaskList($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$page = $_POST['page'] ? intval($_POST['page']) : 1;
			
			$startIdx = ($page - 1) * PAGE_SIZE;
			
			$phpl = 'SELECT r.*, f.url FROM Fichat\Models\RewardTask as r '
				.'LEFT JOIN Fichat\Models\Files as f ON r.cover_pic = f.id '
				.'WHERE 1 ORDER BY r.id DESC LIMIT '.$startIdx.','.PAGE_SIZE;
			$query = new Query($phpl, $di);
			$tasks = $query->execute();
			$taskList = [];
			if ($tasks) {
				$taskIds = '';
				foreach ($tasks as $task) {
					$taskIds .= ','.$task->r->id;
				}
				$records = [];
				if ($taskIds) {
					$taskIds = substr($taskIds, 1);
					$records = RewardTaskRecord::find([
						'conditions' => 'task_id in('.$taskIds.') AND uid='.$uid
					]);
				}
				$now = time();
				foreach ($tasks as $task) {
					$item = $task->r->toArray();
					$item['cover_pic'] = Utils::getFullUrl(OSS_BUCKET_RTCOVER, $task->url);
					
					$status = $item['status'];
					if ($now >= $item['end_time']) {
						$status = TASK_STATUS_END;
					}
					$item['status'] = $status;
					
					$isCliked = 0;
					$shareCount = -1;
					$isShared = 0;
					foreach ($records as $record) {
						if ($record->op_type == TASK_OP_TYPE_CLICK) {
							$isCliked = 1;
						} else if ($record->op_type == TASK_OP_TYPE_SHARE) {
							$isShared = 1;
							$shareCount = count(json_decode($record->join_members));
						}
					}
					$item['shared'] = $isShared;
					$item['clicked'] = $isCliked;
					$item['my_share_join_count'] = $shareCount;
					
					
					
					
					array_push($taskList, $item);
				}
			}
			
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['task_list' => $taskList]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 拉取我参与的任务
	public static function loadMyPubTaskList($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$page = $_POST['page'] ? intval($_POST['page']) : 1;
			
			$startIdx = ($page - 1) * PAGE_SIZE;
			
			$phpl = 'SELECT r.*, f.url FROM Fichat\Models\RewardTask as r '
					.'LEFT JOIN Fichat\Models\Files as f ON r.cover_pic = f.id '
					.'WHERE r.owner_id ='.$uid. ' ORDER BY r.id DESC LIMIT '.$startIdx.','.PAGE_SIZE;
			$query = new Query($phpl, $di);
			$tasks = $query->execute();
			$taskList = [];
			if ($tasks) {
				foreach ($tasks as $task) {
					$item = $task->r->toArray();
					$item['cover_pic'] = Utils::getFullUrl(OSS_BUCKET_RTCOVER, $task->url);
					array_push($taskList, $item);
				}
			}
			
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['task_list' => $taskList]);
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 获取任务服务费
	public static function getTaskFee($di)
	{
		try {
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['fee'=>2]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 拉取我发布的任务
	public static function loadMyJoinTaskList($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$page = $_POST['page'] ? intval($_POST['page']) : 1;
			
			$startIdx = ($page - 1) * PAGE_SIZE;
			// 查找我我任务记录
			$phpl = 'SELECT DISTINCT (r.id), r.*, f.url FROM Fichat\Models\RewardTaskRecord as rr '
					.'LEFT JOIN Fichat\Models\RewardTask as r ON rr.task_id = r.id '
				    .'LEFT JOIN Fichat\Models\Files as f ON r.cover_pic = f.id '
				.'WHERE rr.uid ='.$uid. ' ORDER BY r.id DESC LIMIT '.$startIdx.','.PAGE_SIZE;
			$query = new Query($phpl, $di);
			$tasks = $query->execute();
			
			$taskList = [];
			$now = time();
			
			if ($tasks) {
				$taskIds = '';
				foreach ($tasks as $task) {
					$taskIds .= ','.$task->r->id;
				}
				$records = [];
				if ($taskIds) {
					$taskIds = substr($taskIds, 1);
					$records = RewardTaskRecord::find([
						'conditions' => 'uid='.$uid.' AND task_id in ('.$taskIds.')'
					]);
				}
				foreach ($tasks as $task) {
					$item = $task->r->toArray();
					$item['cover_pic'] = Utils::getFullUrl(OSS_BUCKET_RTCOVER, $task->url);
					$status = $item['status'];
					if ($now >= $item['end_time']) {
						$status = TASK_STATUS_END;
					}
					$item['status'] = $status;
					
					$isCliked = 0;
					$isShared = 0;
					$shareCount = -1;
					foreach ($records as $record) {
						if ($record->op_type == TASK_OP_TYPE_CLICK) {
							$isCliked = 1;
						} else if ($record->op_type == TASK_OP_TYPE_SHARE) {
							$isShared = 1;
							$shareCount = count(json_decode($record->join_members));
							
						}
					}
					$item['clicked'] = $isCliked;
					$item['my_share_join_count'] = $shareCount;
					$item['shared'] = $isShared;
					array_push($taskList, $item);
				}
			}
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['task_list' => $taskList]);
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 发布悬赏任务
	public static function publishTask($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$user = User::findFirst("id = ".$uid);
			$transaction = $di->get(SERVICE_TRANSACTION);
			$user->setTransaction($transaction);
			
			
			// 检查任务的各项数据
			$checkParamRs = DBManager::checkTaskParams($user);
			if (array_key_exists('error_code', $checkParamRs)) {
				return $checkParamRs;
			}
			
			// 悬赏任务
			$clickPrice = $checkParamRs['click_price'];
			$clickCount = $checkParamRs['click_count'];
			$shareCount = $checkParamRs['share_count'];
			$shareJoinCount = $checkParamRs['share_join_count'];
			$sharePrice = $checkParamRs['share_price'];
			$taskAmount = $checkParamRs['task_money'];
			$taskDesp = $checkParamRs['task_desp'];
			$taskCoverFid = $checkParamRs['task_cover'];

//			var_dump($_POST);
			
			// TODO: 保存一条消费记录
			$now = time();
			// 保存任务信息
			$task = new RewardTask();
			$task->setTransaction($transaction);
			$task->owner_id = $uid;
			$task->content = $taskDesp;
			$task->cover_pic = $taskCoverFid;
			$task->task_amount = $taskAmount;
			$task->click_price = $clickPrice;
			$task->click_count = $clickCount;
			$task->share_count = $shareCount;
			$task->share_price = $sharePrice;
			$task->share_join_count = $shareJoinCount;
			$task->balance = $taskAmount;
			$task->create_time = $now;
			// 结束时间为两天后
			$task->end_time = $now + TASK_DURATION;
			// 保存数据
			if (!$task->save())
			{
				$transaction->rollback();
			}
			
			// 更新用户的balance
			$leftbalance = $user->balance - $task->task_amount;
			$user->balance = $leftbalance;
			if (!$user->save()) {
				$transaction->rollback();
			}
			
			// 余额流水
			$bf = new BalanceFlow();
			$bf->op_type = BALANCE_FLOW_PUBTASK;
			$bf->target_id = $task->id;
			$bf->op_amount = $task->task_amount;
			$bf->user_order_id = 0;
			$bf->uid = $uid;
			$bf->create_time = time();
			$bf->setTransaction($transaction);
			if (!$bf->save()) {
				$transaction->rollback();
			}
			
			$data = [
				'task_id' => $task->id
			];
			// 事务提交
			return Utils::commitTcReturn($di, $data, 'E0000');
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}
	
	public static function getShareJoinCount($di)
	{
		try {
			$data = [
				'share_needs_count' => [
					'10' => 2.5,
					'20' => 6,
					'30' => 9,
					'40' => 15,
					'50' => 25,
				]
			];
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, $data);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	public static function isJoinedTask($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			
			$shareUid = $_POST['share_uid'] ? intval($_POST['share_uid']) : 0;
			$taskId = $_POST['task_id'] ? intval($_POST['task_id']) : 0;
			$joined = 0;
			// 查询分享记录
			$record = RewardTaskRecord::findFirst([
				"conditions" => "task_id = ".$taskId . ' AND op_type= 2 AND uid ='.$shareUid,
				"for_update" => true
			]);
			if (!$record) {
				return Utils::commitTcReturn($di, ['joined_task' => $joined]);
			}
			
			// 加入用户数
			$joinMembers = json_decode($record->join_members);
			if (in_array($uid, $joinMembers)) {
				$joined = 1;
			}
			
			$now = time();
			
			$user = User::findFirst([
				"conditions" => "id = ".$uid,
				"for_update" => true
			]);
			$user->setTransaction($transaction);
			
			$todayShareJoinSignTIme = $now - ($now % 86400);
			// 获取今天开始的时间
			if ($todayShareJoinSignTIme != $user->share_join_sign_time) {
				$user->share_join_sign_time = $todayShareJoinSignTIme;
				$user->share_join_count = 0;
			}
			
			if (!$user->save()) {
				$transaction->rollback();
			}
			
			if ($user->share_join_count == 5) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_DAY_HELP_LIMIT);
			}
			
			// 返回数据
			return Utils::commitTcReturn($di, ['joined_task' => $joined]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	/** 上传文件 */
	public static function uploadFile($di)
	{
		try {
			$id = Utils::getMicoTs();
			$ossRs = OssProxy::ossUploadFile($di, OSS_BUCKET_RTCOVER, $id, UPLOAD_BUSS_RTCOVER, 'file', "");
			if ($ossRs) {
				$transaction = Utils::getService($di, SERVICE_TRANSACTION);
				$files = new Files();
				$files->setTransaction($transaction);
				$files->assign([
					'url' => $ossRs['file_name'],
					'type' => $ossRs['file_type'],
					'file_index' => 0
				]);
				if(!$files->save()) {
					var_dump($files->getMessage());
					$transaction->rollback();
				}
				// 提交事务
				$data = [
					'fid' => intval($files->id)
				];
				// 事务提交
				return Utils::commitTcReturn($di, $data, 'E0000');
			} else {
				return ReturnMessageManager::buildReturnMessage(ERROR_UPLOAD);
			}
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}
 
	// 点击任务
	public static function clickTask($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$redis = Utils::getService($di, SERVICE_REDIS);
			$uid = $gd->uid;
			
			$taskId = $_POST['task_id'] ? intval($_POST['task_id']) : 0;
			$task = RewardTask::findFirst([
				"conditions" => "id = ".$taskId,
				"for_update" => true
			]);
			
			// 检查用户是否已经点击过
			if (RewardTaskRecord::findFirst([ "conditions" => "task_id=".$taskId." AND op_type = ".TASK_OP_TYPE_CLICK." AND uid = ".$uid])) {
				return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS);
			}
			
			// 检查用户是否已经超过了每日参与任务的最大数量
			if (!DBManager::checkDayTaskTimes($uid)) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_DAY_LIMIT);
			}
			
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			// 获取用户数据
			$user = User::findFirst("id = ".$uid);
			$user->setTransaction($transaction);
			if (!$task) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_NO_EXIST);
			}
			// 设置事物
			$task->setTransaction($transaction);
			
			// 自己点击自己不做任何处理
			if ($task->owner_id == $uid) {
				$task->total_click_count += 1;
				if (!$task->save()) {
					$transaction->rollback();
				}
				// 返回结果
				Return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, []);
			}
			
			$data = [];
			if ($task->status == TASK_STATUS_DONE) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_FINISHED);
			}
			$getMoney = floatval($task->click_price);
			$balance = floatval($task->balance);
			
			$task->total_click_count += 1;
			$taskBalance = $balance - $getMoney;
			$taskStatus = $task->status;
			if ($taskBalance == 0) {
				$taskStatus = TASK_STATUS_DONE;
			}
			
			// 获取任务的状态
			$taskStatus = DBManager::getTaskStatus($task);
			$task->balance = $taskBalance;
			$task->status = $taskStatus;
			if (!$task->save()) {
				$transaction -> rollback();
			}
			// 增加一条记录
			$taskRecord = new RewardTaskRecord();
			$taskRecord ->setTransaction($transaction);
			$taskRecord -> op_type = TASK_OP_TYPE_CLICK;
			$taskRecord -> task_id = $taskId;
			$taskRecord -> uid = $uid;
//			echo '5';
			// 保存操作记录
			if (!$taskRecord->save()) {
				$transaction->rollback();
			}
			
			// TODO 增加一条收入记录
			$user -> balance += $getMoney;
			$user -> task_income += $getMoney;
			
			// 推入收入到世界排行中
			RedisManager::pushRank($redis, RedisClient::worldRankKey(), $user->id, $user->task_income * 100);
			
			if (!$user ->save()) {
				$transaction->rollback();
			}
			
			// 余额流水
			$bf = new BalanceFlow();
			$bf->op_type = BALANCE_FLOW_CLICKTASK;
			$bf->target_id = $task->id;
			$bf->op_amount = $task->click_price;
			$bf->user_order_id = 0;
			$bf->uid = $uid;
			$bf->create_time = time();
			$bf->setTransaction($transaction);
			if (!$bf->save()) {
				$transaction->rollback();
			}
			
			$redis->close();
			// 事务提交
			return Utils::commitTcReturn($di, $data, 'E0000');
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 分享任务
	public static function shareTask($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			
			$taskId = $_POST['task_id'] ? intval($_POST['task_id']) : 0;
			$task = RewardTask::findFirst([
				"conditions" => "id = ".$taskId
			]);
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			// 检查任务是否存在
			if (!$task) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_NO_EXIST);
			}
			
			// 自己点击自己不做任何处理
			if ($task->owner_id == $uid) {
				$task->total_share_count += 1;
				if (!$task->save()) {
					$transaction->rollback();
				}
				// 返回结果
				Return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, []);
			}
			
			// 检查用户是否已经超过了每日参与任务的最大数量
			if (!DBManager::checkDayTaskTimes($uid)) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_DAY_LIMIT);
			}
			
			// 检查用户是否已经点击过
			if ($taskRecord = RewardTaskRecord::findFirst([ "conditions" => "task_id=".$taskId." AND op_type = ".TASK_OP_TYPE_SHARE." AND uid = ".$uid])) {
				return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['record_id' => $taskRecord->id]);
			}
			// 增加一条记录
			$taskRecord = new RewardTaskRecord();
			$taskRecord ->setTransaction($transaction);
			$taskRecord -> op_type = TASK_OP_TYPE_SHARE;
			$taskRecord -> task_id = $taskId;
			$taskRecord -> count = 0;
			$taskRecord -> uid = $uid;
			
			// 保存操作记录
			if (!$taskRecord->save()) {
				$transaction->rollback();
			}
			
			// 事务提交
			return Utils::commitTcReturn($di, ['record_id' => $taskRecord->id], 'E0000');
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	public static function getTaskDeail($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			
			$taskId = $_POST['task_id'] ? intval($_POST['task_id']) : 0;
			$task = RewardTask::findFirst([
				"conditions" => "id = ".$taskId
			]);
			
			// 检查任务是否存在
			if (!$task) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_NO_EXIST);
			}
			
			// 获取任务记录
			$phpl = 'SELECT rr.*, u.wx_avatar, u.nickname, u.gender FROM Fichat\Models\RewardTaskRecord as rr '
					.'LEFT JOIN Fichat\Models\User as u ON rr.uid = u.id '
					.'WHERE rr.task_id = '.$taskId;
			$query = new Query($phpl, $di);
			$records = $query->execute();
			$rList = [];
			if ($records) {
				// 检查
				foreach ($records as $record) {
					$item = $record->rr->toArray();
					$item['avatar'] = $record->wx_avatar;
					$item['nickname'] = $record->nickname;
					$item['gender'] = $record->gender;
					array_push($rList, $item);
				}
			}
			// 返回任务记录
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['task_records' => $rList]);
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 增加任务用户分享人数
	public static function addTaskShareCount($di)
	{
		// 分享人数
		try {
			$redis = Utils::getService($di, SERVICE_REDIS);
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			$shareUid = $_POST['share_uid'] ? intval($_POST['share_uid']) : 0;
			$taskId = $_POST['task_id'] ? intval($_POST['task_id']) : 0;
			
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			
			// 查询分享记录
			$record = RewardTaskRecord::findFirst([
				"conditions" => "task_id = ".$taskId . ' AND op_type= 2 AND uid ='.$shareUid,
				"for_update" => true
			]);
			if (!$record) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_RECORD_NO_EXIST);
			}
			
			$record->setTransaction($transaction);
			
			// 查询任务
			$task = RewardTask::findFirst([
				"conditions" => "id = ".$record->task_id,
				"for_update" => true
			]);
			
			if ($task->status == TASK_STATUS_DONE) {
				return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS);
			}
			if ($record->join_members) {
				$joinMembers = json_decode($record->join_members);
			} else {
				$joinMembers = array();
			}
			
			$recordJoinCount = count($joinMembers);
			if ($recordJoinCount < $task->share_join_count) {
				if (!in_array($uid, $joinMembers)) {
					array_push($joinMembers, $uid);
				}
				$record -> join_members = json_encode($joinMembers);
				if (count($joinMembers) == $task->share_join_count) {
					$task->total_share_count += 1;
					$newTaskBalance = $task->balance - $task->share_price;
					if ($newTaskBalance <= 0) {
						$task->balance = 0;
						$task->status = TASK_STATUS_DONE;
					}
					
					// 获取任务的状态
					$taskStatus = DBManager::getTaskStatus($task);
					$task->status = $taskStatus;
					// 更新任务数据
					if (!$task->save()) {
						$transaction->rollback();
					}
					// 给用户钱
					// TODO 增加一条余额操作记录
					
					$user = User::findFirst([
						"conditions" => "id = ".$record->uid,
						"for_update" => true
					]);
					$user->setTransaction($transaction);
					$user->balance += $task->share_price;
					$user -> task_income += $getMoney;
					
					$now = time();
					$todayShareJoinSignTIme = $now - ($now / 86400);
					
					if ($todayShareJoinSignTIme != $user->share_join_sign_time) {
						$user->share_join_sign_time = $todayShareJoinSignTIme;
						$user->share_join_count = 0;
					}
					$user->share_join_count += 1;
					
					// 推入收入到世界排行中
					RedisManager::pushRank($redis, RedisClient::worldRankKey(), $user->id, $user->task_income * 100);
					
					if (!$user->save()) {
						$transaction->rollback();
					}
					
					// 余额流水
					$bf = new BalanceFlow();
					$bf->op_type = BALANCE_FLOW_SHARETASK;
					$bf->target_id = $task->id;
					$bf->op_amount = $task->share_price;
					$bf->user_order_id = 0;
					$bf->uid = $record->uid;
					$bf->create_time = time();
					$bf->setTransaction($transaction);
					if (!$bf->save()) {
						$transaction->rollback();
					}
				}
			}
			
			// 保存好友关系, 我和分享人
			if (!Friend::findFirst([
				"conditions" => "user_id = ".$uid." AND friend_id = ".$shareUid
			])) {
				$friend = new Friend();
				$friend->setTransaction($transaction);
				$friend->user_id = $uid;
				$friend->friend_id = $shareUid;
				if (!$friend->save()) {
					$transaction->rollback();
				}
			}
			
			// 保存好友关系, 分享人和我
			if (!Friend::findFirst([
				"conditions" => "user_id = ".$shareUid." AND friend_id = ".$uid
			])) {
				$friend = new Friend();
				$friend->setTransaction($transaction);
				$friend->user_id = $shareUid;
				$friend->friend_id = $uid;
				if (!$friend->save()) {
					$transaction->rollback();
				}
			}
			// 保存
			if(!$record->save()) {
				$transaction->rollback();
			}
			
			// 获取参与的用户
			$joinMemberIds = '';
			foreach ($joinMembers as $joinMember) {
				$joinMemberIds .= ',' .$joinMember;
			}
			$joinShareMembers = [];
			if ($joinMemberIds) {
				$joinMemberIds = substr($joinMemberIds, 1);
				// 查询所有用户
				$joinUsers = User::find([
					"conditions" => "id in (".$joinMemberIds.")"
				]);
				
				foreach ($joinUsers as $joinUser) {
					array_push($joinShareMembers, [
						'id' => $joinUser->id,
						'avatar' => $joinUser->wx_avatar,
						'nickname' => $joinUser->nickname
					]);
				}
			}
			
			// 返回数据
			$data = [
				'record_id' => $record->id,
				'join_count' => count($joinMembers),
				'join_Members' => $joinShareMembers
			];
			$redis->close();
			// 事务提交
			return Utils::commitTcReturn($di, $data, 'E0000');
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	

}


