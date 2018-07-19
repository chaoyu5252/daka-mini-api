<?php
namespace Fichat\Library;

use Fichat\Common\RedisManager;
use Fichat\Common\ReturnMessageManager;
use Fichat\Common\DBManager;
use Fichat\Models\AssociationMember;
use Fichat\Models\Feedback;
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
			return Common::procException($e);
		}
	}
    
 

}


