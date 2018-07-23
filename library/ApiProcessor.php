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
			echo '1';
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$page = $_POST['page'] ? intval($_POST['page']) : 1;
			
			$startIdx = ($page - 1) * PAGE_SIZE;
			
			$tasks = RewardTask::find([
				'offset' => $startIdx,
				'limit' => PAGE_SIZE,
				'order' => 'id desc'
			]);
			$taskList = [];
			if ($tasks) {
				$taskList = $tasks->toArray();
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
			
			$tasks = RewardTask::find([
				"conditions" => "owner_id = ".$uid,
				'offset' => $startIdx,
				'limit' => PAGE_SIZE,
				'order' => 'id desc'
			]);
			$taskList = [];
			if ($tasks) {
				$taskList = $tasks->toArray();
			}
			
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['task_list' => $taskList]);
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
			$records = RewardTaskRecord::find([
				"conditions" => "uid = ".$uid,
				"columns" => "task_id",
				"group" => "task_id"
			]);
			
			$taskIds = '';
			foreach ($records as $record) {
				$taskIds .= ','.$record->task_id;
			}
			$taskIds = substr($taskIds, 1);
			$where = '1';
			$taskList = [];
			if ($taskIds) {
				$where .= ' AND id in ('.$taskIds .')';
				$tasks = RewardTask::find([
					'conditions' => $where,
					'offset' => $startIdx,
					'limit' => PAGE_SIZE,
					'order' => 'id desc'
				]);
				if ($tasks) {
					$taskList = $tasks;
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
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			
			$taskId = $_POST['task_id'] ? intval($_POST['task_id']) : 0;
			$task = RewardTask::findFirst([
				"conditions" => "id = ".$taskId,
				"for_update" => true
			]);
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			// 获取用户数据
			$user = User::findFirst("id = ".$uid);
			$user->setTransaction($transaction);
			if (!$task) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_NO_EXIST);
			}
			// 设置事物
			$task->setTransaction($transaction);
			
			$data = [];
			if ($task->status == TASK_STATUS_DONE) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_FINISHED);
			}
			$getMoney = floatval($task->click_price);
			$balance = floatval($task->balance);
			$taskBalance = $balance - $getMoney;
			$taskStatus = $task->status;
			if ($taskBalance == 0) {
				$taskStatus = TASK_STATUS_DONE;
			}
			$task->balance = $taskBalance;
			$task->status = $taskStatus;
			$task->total_click_count += 1;
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
			if (!$user ->save()) {
				$transaction->rollback();
			}
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
			$task = RewardTask::find([
				"conditions" => "id = ".$taskId
			]);
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			// 检查任务是否存在
			if (!$task) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TASK_NO_EXIST);
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
	
	// 增加任务用户分享人数
	public static function addTaskShareCount($di)
	{
		// 分享人数
		try {
			
			$recordId = $_POST['record_id'] ? intval($_POST['record_id']) : 0;
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			
			// 查询分享记录
			$record = RewardTaskRecord::findFirst([
				"conditions" => "id = ".$recordId,
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
			
			if ($record->count < $task->share_join_count) {
				$record -> count += 1;
				if ($record->count == $task->share_join_count) {
					$task->total_share_count += 1;
					$newTaskBalance = $task->balance - $task->share_price;
					if ($newTaskBalance <= 0) {
						$task->balance = 0;
						$task->status = TASK_STATUS_DONE;
					}
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
					if (!$user->save()) {
						$transaction->rollback();
					}
				}
			}
			
			// 保存
			if(!$record->save()) {
				$transaction->rollback();
			}
			
			$data = [
				'record_id' => $record->id,
				'join_count' => $record->count
			];
			
			// 事务提交
			return Utils::commitTcReturn($di, $data, 'E0000');
		} catch (\Exception $e) {
			var_dump($e);
			return Utils::processExceptionError($di, $e);
		}
	}

}


