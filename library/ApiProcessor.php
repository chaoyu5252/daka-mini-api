<?php
namespace Fichat\Library;

use Fichat\Common\RedisManager;
use Fichat\Common\ReturnMessageManager;
use Fichat\Common\DBManager;
use Fichat\Models\AssociationMember;
use Fichat\Models\Feedback;
use Fichat\Models\Files;
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

define('OSS_BUCKET_UAVATAR', 'uavatar');
define('OSS_BUCKET_MOMENTS', 'moments');
define('OSS_BUCKET_RTCOVER', 'rtcover');
define('OSS_BUCKET_GAVATAR', 'gavatar');
define('OSS_BUCKET_BG', 'background');
define('OSS_BUCKET_PUBLIC', 'public');

/** 上传业务类型 ======================================= */

define('UPLOAD_BUSS_AVATAR', 1);
define('UPLOAD_BUSS_MOMENT', 2);
define('UPLOAD_BUSS_GROUP', 3);
define('UPLOAD_BUSS_BG', 4);
define('UPLOAD_BUSS_COMMON', 5);
define('UPLOAD_BUSS_RTCOVER', 6);

/** 支付类型 ========================================== */

define('PAY_BY_ALI', 1);        // 支付宝
define('PAY_BY_WX', 2);         // 微信
define('PAY_BY_BL', 3);         // 余额
define('PAY_BY_APPLE', 4);


/** 家族成员操作权限 */
define('FMPERM_UP_FAMILYAVATAR', 0);
define('FMPERM_UP_FAMILYNAME', 1);
define('FMPERM_UP_FAMILYINFO', 2);
define('FMPERM_UP_FAMILYBULLTIN', 3);
define('FMPERM_MG_FAMILYMEMBERS', 4);
define('FMPERM_UP_FAMILYSHUTUP', 5);
define('FMPERM_PUB_FAMILYTASK', 6);
define('FMPERM_CONFIRM', 7);

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
			$user = User::findFirst("openid = '".$openid."' OR unionid= '".$unionid."'");
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
			
			$tasks = RewardTask::find([
				'offset' => $startIdx,
				'limit' => PAGE_SIZE,
				'order' => 'id desc'
			]);
			
			var_dump($tasks);
			$taskList = [];
			
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['task_list' => $taskList]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 拉取我参与的任务
	public static function loadMyPubTaskList($di)
	{
	
	}
	
	// 拉取我发布的任务
	public static function loadMyJoinTaskList($di)
	{
	
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
			// 结束时间为两天后
			$task->end_time = time() + 172800;
			var_dump($task->toArray());
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
	
	}
	
	// 分享任务
	public static function shareTask($di)
	{
	
	}
	
	// 增加任务用户分享人数
	public static function addTaskShareCount($di)
	{
		// 分享人数
		
	}
	
 

}


