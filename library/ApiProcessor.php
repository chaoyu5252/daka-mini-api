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
use Fichat\Proxy\WxPayProxy;
use Fichat\Proxy\HxChatProxy;

use Fichat\Utils\EmptyObj;
use Fichat\Utils\KaException;
use Fichat\Utils\MessageSender;
use Fichat\Utils\KakaPay;
use Fichat\Utils\Utils;
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
     *  TODO 帐号相关
     */

    /**
     * 短信验证码
     *
     * @param $di
     * @return mixed
     */
    public static function processSMS($di){
        try {
            $phone = $_POST['phone'];

            if(!$phone){  return ReturnMessageManager::buildReturnMessage('E0001',null); }
            if(!preg_match("/^1(3|4|5|7|8)\d{9}$/",$phone)){
                return ReturnMessageManager::buildReturnMessage('E0004',null);
            }
            $type = (int)trim($_POST['type']);
            /** 类型
             * 0: 注册
             * 1: 登录
             * 2: 修改支付密码
             * 3: 修改手机号
             * 4: 提现
             */
            if (!in_array($type, [SMSBUSS_TYPE_LOGIN,SMSBUSS_TYPE_PAYPASSWORD,SMSBUSS_TYPE_BINDPHONE,SMSBUSS_TYPE_WITHDRAW])) {
                return ReturnMessageManager::buildReturnMessage('E0301');
            }
//            if ($type == 1) {
//            	// 检查手机号是否存在
//	            if (!User::findFirst("phone=".$phone)) {
//	                $type = 0;
//	            }
//            }
            // 发送验证码
            $vCode = SmsHuaWeiProxy::sendSms($di, $phone);
            if (!$vCode) {
                return ReturnMessageManager::buildReturnMessage('E0308');
            }
            $re = DBManager::saveSignToken($di, $vCode, $phone, $type);
            if(!$re){return ReturnMessageManager::buildReturnMessage('E9999',null); }
            // 发送成功
            $sendSuccess = 1;
            // 返回信息
	        return ReturnMessageManager::buildReturnMessage('E0000',array(
	        	'sendSuccess' => $sendSuccess,
                'vCode' => $vCode
	        ));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 更新用户信息
     *
     * @param $hxConfig
     * @param $di
     * @return mixed
     */
    public static function processUpUserInfo($hxConfig, $di) {
        try {
            $userId = $_POST['userId'];
            $nickname = $_POST['nickname'];
            $gender = $_POST['gender'];
            $birthDay = $_POST['birthday'];
            $tags =array_key_exists('tags', $_POST) ? $_POST['tags'] : "";
            $inviteCode = $_POST['inviteCode'];

            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            if(!$nickname){ return ReturnMessageManager::buildReturnMessage('E0011', null); }
            if(!$gender || ($gender != 1 && $gender != 2)){ return ReturnMessageManager::buildReturnMessage('E0015',null); }

            // 是否注册
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044', null); }
            // 环信修改用户昵称
            $hxupdateNickname = HxChatProxy::updateNickname($hxConfig, $userId, $nickname);
            if(!$hxupdateNickname){ return ReturnMessageManager::buildReturnMessage('E0046', null); }

            // 更新用户的标签
            DBManager::updateTags($userId, $tags);
            // 检查生日
	        if (!$birthDay) { return ReturnMessageManager::buildReturnMessage('E0287'); }
	        if (!Utils::validBirthDayFormat($birthDay, "Y-m-d")) { return ReturnMessageManager::buildReturnMessage('E0288'); }

	        // 更新用户项目
	        $updateUserItems = [
		        'birthday' => $birthDay,
		        'nickname' => $nickname,
		        'gender' => $gender
	        ];
	        // 检查是否有邀请码
	        if ($inviteCode) {
		        // 邀请用户
		        $inviteUser = DBManager::getUserById($inviteCode);
		        if ($inviteUser && ($user->invite_code == 0)) {
			        $redis = RedisClient::create($di->get('config')['redis']);
			        // 给邀请者和当前用户都增加500经验
			        DBManager::changeUserLevel($di, $redis, $inviteUser, 500);
			        DBManager::changeUserLevel($di, $redis, $user, 500);
					$updateUserItems['invite_code'] = $inviteCode;
			        $redis->close();
		        }
	        }
            // 更新用户信息
            DBManager::updateUserDATA($user, $updateUserItems);

            return ReturnMessageManager::buildReturnMessage('E0000', array('updateStatus' => 1));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 登陆
     *
     * @param $di
     * @return mixed
     */
    public static function processLogin($di) {
        try{
            $phone = $_POST['phone'];
            if (!$phone) {
                return ReturnMessageManager::buildReturnMessage('E0001', null);
            }
            // 获取短信校验码
            $vCode = (int)$_POST['vCode'];
            if(!$vCode){ return ReturnMessageManager::buildReturnMessage('E0002', null); }

            $systemConfig = DBManager::getSystemConfig();
            if($systemConfig->is_verify_phone_code == 1) {
                // 获取用户的校验Token
                if (!DBManager::vaildSignToken($phone, $vCode, SMSBUSS_TYPE_LOGIN)) {
                    return ReturnMessageManager::buildReturnMessage('E0006');
                }
            }

	        // 测试版最高用户量为100
	        if(DBManager::getUserCount() >= 100) {
                return ReturnMessageManager::buildReturnMessage('E0329');
            }
	        // 未注册
	        $loginStatus = 1;   // 登录
	        // 帐号是否存在
	        $account = DBManager::checkAccountExistByPhone($phone);
	        // 获取事务
	        $transaction = $di->getShared(SERVICE_TRANSACTION);
	        if (!$account) {
		        $errCode = 'E0317';
		        $hxConfig = $di->get('config')['hxConfig'];
		        // 已注册
		        $loginStatus = 2;   // 注册
		        // 获取|验证参数
		        $params = DBManager::getCreateUserParams($di);
		        if ($params['error_code']) {
			        // 如果是有error_code, 证明发生了错误
			        return $params;
		        }
		        $password = $params['password'];
		        // 创建账户
		        $uid = Utils::guid();
		        $openid = Utils::guid();

		        // 创建账户
		        $account = DBManager::createAccount($di, $uid, $openid, $phone, $params['password']);
		        // 设置账号ID
		        $params['account_id'] = $account->id;
                $params['nickname'] = '大咖_'.$params['account_id'];
		        // 创建用户
		        $user = DBManager::createUser($di, $params);
		        // 环信创建用户
		        $hxSignup = HxChatProxy::registerIM($user->id, $params['password'], $hxConfig);
		        if(!$hxSignup){
		        	$errCode = 'E0046';
			        // 删除账号和用户
			        $transaction->rollback();
			        return ReturnMessageManager::buildReturnMessage('E0046', null);
		        }
		        // 发送注册欢迎系统消息
                MessageSender::sendRegisterWelcome($di, $user);
	        } else {
	        	$errCode = 'E0316';
	            // 检查账号状态
		        if($account->status != 1){ return ReturnMessageManager::buildReturnMessage('E0151', null); }
		        // 获取用户数据
		        $user = DBManager::getUserByAccountId($account->id);
		        $password = $account->password;
		        // 发送登录欢迎系统消息
                MessageSender::sendLoginWelcome($di, $user);
	        }
            // 更新保存登录token
            $token = Utils::guid();
            DBManager::saveToken($di, $token, $user);
	        // 构建返回数据
            $returnData = [
            	'loginStatus' => $loginStatus,
	            'userId' => $user->id,
                'displayId' => $user->account_id,
	            'userToken' => $token,
	            'userPassword' => $password
			];
            $redis = Utils::getDiRedis($di);
            // 推送到排行榜中
            RedisManager::pushLevExpToRank($redis, $user->id, 1, 0);
	        // 事务提交
	        return Utils::commitTcReturn($di, $returnData, $errCode);
        } catch ( \Exception $e ) {
            //插入日志
	        return KaException::error_handler($di, $e, $errCode);
        }
    }

    /**
     * 微信登陆
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return array|mixed
     */
    public static function processWxLogin($app, $di, $hxConfig){
        try{
	        // 获取|验证参数
	        $params = DBManager::getCreateUserParams($di, false);
	        if ($params['error_code']) {
		        // 如果是有error_code, 证明发生了错误
		        return $params;
	        }
            // 判断用户是否存在
            $account = DBManager::checkAccountExixtByUid($params['uid']);
            // 获取事务
            $transaction = $di->getShared(SERVICE_TRANSACTION);
            // 如果账号存在
            if ($account) {
	            $errCode = 'E0316';
	            $loginStatus = 1;
                $user = DBManager::getUserByAccountId($account->id);
                $password = $account->password;

                // 更新保存登录token
                $token = Utils::guid();
                DBManager::saveToken($di, $token, $user);

                // 更新用户信息
                $user = DBManager::updateUser($di, $user, $params['nickname'], $params['gender'], null, null, '', '', $params['wx_avatar']);
                MessageSender::sendLoginWelcome($di, $user);
            } else {
	            $errCode = 'E0317';
            	$loginStatus = 2;
	            // 创建帐号
	            $account = DBManager::createAccount($di, $params['uid'], $params['openid'], $params['phone'], $params['password']);
	            $params['account_id'] = $account->id;
                $password = $params['password'];
                // 将微信头像上传至OSS
                $file_id = $params['account_id'];
                $oss_buss_type = UPLOAD_BUSS_AVATAR;
                $oss_bucket = OSS_BUCKET_UAVATAR;
                $uploadRS = OssProxy::uploadRemoteImage($di, $oss_bucket, $file_id, $params['wx_avatar']);
                // 检查是否成功
                if ($uploadRS) {
                    // 构建保存的图片资源信息
                    $user_oss_avatar = $uploadRS['oss-request-url'];
                    $user_thumb = $uploadRS['thumb'];
                    $params['user_oss_avatar'] = $user_oss_avatar;
                } else {
                    return ReturnMessageManager::buildReturnMessage('E0084',null);
                }
	            // 创建用户
	            $user = DBManager::createUser($di, $params);
	            // 环信创建用户
	            $hxSignup = HxChatProxy::registerIM($user->id, $params['password'], $hxConfig);
	            if(!$hxSignup){
                    $transaction->rollback();
	                return ReturnMessageManager::buildReturnMessage('E0046', null);
	            }

	            // 发送注册欢迎系统消息
                MessageSender::sendRegisterWelcome($di, $user);
            }

            // 更新保存登录token
            $token = Utils::guid();
            DBManager::saveToken($di, $token, $user);

            // 返回数据
	        $returnData = [
		        'loginStatus' => $loginStatus,
		        'userId' => $user->id,
                'displayId' => $user->account_id,
		        'userToken' => $token,
		        'userPassword' => $password
	        ];
            $redis = Utils::getDiRedis($di);
            // 推送到排行榜中
            RedisManager::pushLevExpToRank($redis, $user->id, 1, 0);
	        // 事务提交
	        return Utils::commitTcReturn($di, $returnData, $errCode);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }


    /**
     * 找回密码
     *
     * @param $app
     * @param $hxConfig
     * @param $di
     * @return mixed
     */
    public static function processUpdatePassword($app, $hxConfig ,$di){
        try{
            $phone = $_POST['phone'];
            $newPassword = $_POST['password'];
            //验空
            if(!$phone){  return ReturnMessageManager::buildReturnMessage('E0001',null);  }
            if(!$newPassword){  return ReturnMessageManager::buildReturnMessage('E0002',null);  }
            if(!preg_match("/^1(3|4|5|7|8)\d{9}$/",$phone)){
                return ReturnMessageManager::buildReturnMessage('E0004',null);
            }

            //验证通过后 验证用户是否存在
            $account=DBManager::checkAccountExistByPhone($phone);
            //用户不存在
            if(!$account){ return ReturnMessageManager::buildReturnMessage('E0005',null); }

            //全部验证通过，修改用户密码
            $account=DBManager::updateAccountPassword($account, $newPassword);

            // 查询用户信息
            $user = DBManager::getUserByAccountId($account->id);
            if( $account && HxChatProxy::resetUserPassword($account->id,$account->password,$hxConfig)){
                return ReturnMessageManager::buildReturnMessage('E0000', array('userInfo' => $user->toArray()));
            }else{//修改失败
                return ReturnMessageManager::buildReturnMessage('E0056',null);
            }
        }catch ( \Exception $e ) {
            //插入日志
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 退出登陆
     *
     * @return mixed
     */
    public static function processLogout() {
        // 参数检查
        $userId = $_POST['userId'];
        if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
        // 检查用户是否存在
        $user = DBManager::getUserById($userId);
        if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

        // 删除用户token
        DBManager::delUserToken($userId);
        return ReturnMessageManager::buildReturnMessage('E0142',null);
    }

    /**
     * 绑定手机号
     *
     * @param $di
     * @return mixed
     */
    public static function processBindPhone($di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $phone = $_POST['phone'];
            if(!$phone){ return ReturnMessageManager::buildReturnMessage('E0001',null); }
            if(!preg_match("/^1(3|4|5|7|8)\d{9}$/",$phone)){
                return ReturnMessageManager::buildReturnMessage('E0004', null);
            }
	        $vCode = $_POST['vCode'];
	        if(!$vCode){ return ReturnMessageManager::buildReturnMessage('E0006'); }

            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044', null); }

	        // 获取用户的校验Token
	        if (!DBManager::vaildSignToken($phone, $vCode, SMSBUSS_TYPE_BINDPHONE)) {
		        return ReturnMessageManager::buildReturnMessage('E0006');
	        }

            $account = DBManager::checkAccountExistByPhone($phone);
            if($account){
                return ReturnMessageManager::buildReturnMessage('E0003', null);
            }else{
                $account = DBManager::getAccountById($user->account_id);
            }
            // 添加、更新绑定手机号
            DBManager::updateUserPhone($account, $user, $phone);
            // 返回
            return ReturnMessageManager::buildReturnMessage('E0000', array('bindSuccess' => 1));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 设置登录密码
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processSetPassword($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $oldPassword = $_POST['oldPassword'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0002',null); }
            $password = $_POST['password'];
            if(!$password){ return ReturnMessageManager::buildReturnMessage('E0002',null); }

            // 检测用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044', null); }
            if($user->phone == ''){ return ReturnMessageManager::buildReturnMessage('E0213', null); }

            // 获取帐号信息
            $account = DBManager::getAccountById($user->account_id);

            // 判断旧密码是否正确
            if($oldPassword != $account->password){return ReturnMessageManager::buildReturnMessage('E0006', null);}

            // 更新环信密码
            $hxSetPassword = HxChatProxy::resetUserPassword($userId, $password, $hxConfig);
            if(!$hxSetPassword){ return ReturnMessageManager::buildReturnMessage('E0056', null); }

            // 设置密码
            DBManager::updateAccountPassword($account, $password);

            return ReturnMessageManager::buildReturnMessage('E0000', null);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 设置用户支付密码
     *
     * @param $di
     * @return string
     */
    public static function processSetPayPassword($di)
    {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $phone = $_POST['phone'];
            if(!$phone){ return ReturnMessageManager::buildReturnMessage('E0001',null); }
            if(!preg_match("/^1(3|4|5|7|8)\d{9}$/",$phone)){
                return ReturnMessageManager::buildReturnMessage('E0004', null);
            }
			$vCode = $_POST['vCode'];
            if(!$vCode){ return ReturnMessageManager::buildReturnMessage('E0006'); }
            $payPassword = $_POST['payPassword'];
            if(!$payPassword){ return ReturnMessageManager::buildReturnMessage('E0249',null); }

	        // 获取用户的校验Token
	        if (!DBManager::vaildSignToken($phone, $vCode, SMSBUSS_TYPE_PAYPASSWORD)) {
		        return ReturnMessageManager::buildReturnMessage('E0006');
	        }

            // 检测用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044', null); }

            // 获取帐号信息
            $account = DBManager::checkAccountExistByPhone($phone);
            if($account && $user->account_id != $account->id) {return ReturnMessageManager::buildReturnMessage('E0020', null);}
            if(!$account) {
                $account = $user->account;
            }
            // 设置用户支付密码
            $result = DBManager::setUserPayPassword($user, $account, $payPassword, $phone);
            if ($result) {
                return ReturnMessageManager::buildReturnMessage('E0000', ['setPayPasswordSuccess' => 1]);
            } else {
                return ReturnMessageManager::buildReturnMessage('E0250', null);
            }
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 实名认证
     *
     * @param $di
     * @return mixed
     */
    public static function processRealName($di) {
        try {
	        $uid = $_POST['userId'];
	        if(!$uid){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

	        // 检测用户是否存在
	        $user = DBManager::getUserById($uid);
	        if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044', null); }

	        $name = $_POST['name'];
	        $idCode = $_POST['idCode'];
	        // 认证状态
	        $validStatus = 0;
	        if ($user->id_code) {
	        	return ReturnMessageManager::buildReturnMessage('E0292');
	        }
	        // 实名认证
	        if (Utils::realNameValid($name, $idCode)) {
		        $user->name = $name;
		        $user->id_code = $idCode;
		        // 保存
		        if($user->save()){
			        $validStatus = 1;
		        }
	        }
	        // 如果实名认证失败抛错
	        if ($validStatus == 0) {
		        return ReturnMessageManager::buildReturnMessage('E0289');
	        }
	        // 返回数据
	        return ReturnMessageManager::buildReturnMessage('E0000', ['validStatus' => $validStatus]);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取用户消息
     *
     * @param $di
     * @return mixed
     */
    public static function processGetUserMsg($di) {
        try {
	        $uid = $_POST['userId'];
	        if(!$uid){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

	        // 检测用户是否存在
	        $user = DBManager::getUserById($uid);
	        if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044', null); }

	        $lastMsgId = isset($_POST['lastMsgId']) ? $_POST['lastMsgId'] : 0;

	        // 获取用户的消息列表
	        $userMsgs = DBManager::getUserMsgList($uid, $lastMsgId);
	        // 组装返回的结果
	        $data = [];
	        if ($userMsgs) {
	        	$data = ReturnMessageManager::buildUserMsgs($userMsgs);
	        }
	        // 更新所有消息的状态为已读
	        if ($data) {
		        DBManager::updateUserMsgStatus($di, $userMsgs);
	        }
	        // 返回信息
	        return ReturnMessageManager::buildReturnMessage('E0000', ['userMessages' => $data]);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
	}


    /*
     *  TODO 人物相关
     */

    /**
     * 获取用户数据
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetUser($app, $di) {
        try{
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $targetId = $_POST['targetId'];
            if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0055',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $target = DBManager::getUserById($targetId);
            if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            $userAssociation = DBManager::getUserAssociation($targetId);
            $token = $userId == $targetId ? $user->token->token : null;
            // 获取用户粉丝数量和关注数量
            $fansAndFollows = DBManager::getUserFansAndFollowNum($targetId);
            // 获取说说总数,礼物总数
            $momentsNum = DBManager::getUserMomentsNum($targetId);
            $giftNum = DBManager::getUserGiftNum($targetId);
            // 处理数据,判断是否为自己
            if($userId == $targetId){
                $userData = ReturnMessageManager::buildUserInfo($target, $token, $userAssociation, $fansAndFollows, $momentsNum, $giftNum);
                $userData['is_attention'] = 0;
                $userData['relationship'] = 1;
            }else{
                $userData = ReturnMessageManager::buildTargetInfo($target);
                $userData['is_look'] = (string)0;
	            $userData['fansNum'] = $fansAndFollows['fansNum'];
	            $userData['followsNum'] = $fansAndFollows['followsNum'];

                // 判断是否为关注、粉丝
                $attention = DBManager::checkAttention($userId, $targetId);
                $friend = DBManager::isFriend($userId, $targetId);

                // 获取查看朋友圈关系
	            $userRelationPerm = DBManager::getUserRelationPerm($userId, $targetId);
	            $is_look = $userRelationPerm ? $userRelationPerm->is_look : LOOK_UMOMENTS_YES;

                // 是否关注
                if(!$attention){
                    $userData['is_attention'] = 0;
                }else {
	                $userData['is_attention'] = 1;
                }
	            $userData['is_look'] = $is_look;
	            // 与用户关系,1:自己,2:仇人,3:好友,4：粉丝或陌生人
                if($friend){
//                    $myFriend = DBManager::isFriend($targetId, $userId);
                    $userData['relationship'] = 3;
                    $userData['disturb'] = $friend->disturb;
//                    if($myFriend){
//                        $userData['forbid_look'] = $myFriend->forbid_look;
//                    }
                }else{
                    $userData['relationship'] = 4;
                }
            }

            // 查询用户最近4条说说
//            $momentsList = DBManager::getUserNewMoments($targetId);
//            $momentsData = ReturnMessageManager::buildUserMomentsFourPri($momentsList);
//            $userData['moments'] = $momentsData;

            // 检查是否获取自己的数据
            if ($userId == $targetId) {
                // 获取用户好友申请,家族申请数量,家族邀请数量总和
                $newFriendNum = DBManager::getUserFriendNum($userId);
                // 获取用户新的粉丝数量
                $newFansNum = DBManager::getUserNewFansNum($userId);
                // 在返回值中添加这亮个参数
                $userData['new_fans'] = $newFansNum;
                $userData['new_apply'] = $newFriendNum;
                $userData['isPayPassword'] = $user->account->pay_password == '' ? 0 : 1;
            }
            // 检查是否查看用户的

            return ReturnMessageManager::buildReturnMessage('E0000', array('userInfo' => $userData));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }


    /**
     * 上传头像
     * 类型 1:用户 2:家族
     *
     * @param $di
     * @return mixed
     */
    public static function processProfileUpload($di){
        try{
            // 参数检查
            $uid = $_POST['userId'];
            if(!$uid){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $type = $_POST['type'];
            if(!$type || ($type != 1 && $type != 2)){ return ReturnMessageManager::buildReturnMessage('E0176',null); }
            $groupId = isset($_POST['groupId']) ? $_POST['groupId'] : null;
            //获取用户
            $user = DBManager::getUserById($uid);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
			// 用户头像
            if ($type == 1) {
                $target = $user;
            }
            // 家族头像
            if ($type == 2) {
            	if (!$groupId) {
		            return ReturnMessageManager::buildReturnMessage('E0112');
	            }
	            $association = DBManager::getAssociationByGroupId($groupId);
	            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }
//	            $userAssociations = DBManager::getUserAssociations($uid);
	            // 是否在群组中
//	            $familyMember = DBManager::getAssociationMemberByUserId($uid, $association->id);
//	            if (!$familyMember) {
//	                return ReturnMessageManager::buildReturnMessage('E0118');
//	            }
//	            $inAssociation = false;
//	            foreach($userAssociations as $userAssociation) {
//		            if ($userAssociation->association_id == $association->id) {
//			            $inAssociation = true;
//			            break;
//		            }
//	            }
//	            $isAdmin = DBManager::checkAssociationUserType($uid, $association->id);
	            // 检查用户是否是管理员
	            if(!Utils::verifyFamilyOpPerm($uid, $association->id, FMPERM_UP_FAMILYAVATAR)){
	            	return ReturnMessageManager::buildReturnMessage('E0120',null);
	            }
	            // 目标数据设为家族数据
	            $target = $association;
            }
            // 上传头像
            if ($_FILES["user_avatar"]["error"] > 0){
                return ReturnMessageManager::buildReturnMessage('E0061',null);
            } else {
//            	$target->setTransaction(SERVICE_TRANSACTION);
	            $uploadRs = OssProxy::uploadAvatar($di, $target, $type);
	            if ($uploadRs) {
	                $avatar = $uploadRs['avatar'];
		            $thumb = $uploadRs['thumb'];
	                $avatarKey = $uploadRs['avatarKey'];
	                $thumbKey = $uploadRs['thumbKey'];
		            // 构建返回数据
	                $data = [$avatarKey => $avatar, $thumbKey => $thumb];
	                return ReturnMessageManager::buildReturnMessage('E0000', $data);
	            } else {
		            return ReturnMessageManager::buildReturnMessage('E0084',null);
	            }
            }
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }


    /**
     * 搜索
     * 类型 1：用户 2：家族
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processSearch($app, $di) {
        try{
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $condition = $_POST['condition'];
            if(!$condition){ return ReturnMessageManager::buildReturnMessage('E0152',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            $pageIndex = (int)$_POST['pageIndex'];
            $pageIndex = $pageIndex ? $pageIndex : 1;

            // 搜索
            if (!preg_match("/^1(3|4|5|7|8)\d{9}$/", $condition)) {
                $search = DBManager::getUserByAccountId($condition);
            } else {
                $search = DBManager::getUserByPhone($condition);
            }
            if ($search) {
                if ($userId == $search->id) {
                    $returnData['userInfo'] = ReturnMessageManager::buildUser($search);
                    $returnData['userInfo']['is_attention'] = 0;
                    $returnData['userInfo']['relationship'] = 1;
                } else {
                    $returnData['userInfo'] = ReturnMessageManager::buildUser($search);

                    // 判断是否为关注、粉丝
                    $attention = DBManager::checkAttention($userId, $search->id);
                    $friend = DBManager::isFriend($userId, $search->id);

                    // 是否关注
	                $returnData['userInfo']['is_attention'] = $attention ? USER_ATTENSION_YES : USER_ATTENSION_NO;

                    // 检查是否看用户的朋友圈
//	                $userRelationPerm = DBManager::getUserRelationPerm($userId, $condition);
//	                $is_look = $userRelationPerm ? $userRelationPerm->is_look : LOOK_UMOMENTS_YES;
//	                $returnData['userInfo']['is_look'] = $is_look;

                    // 与用户关系,1:自己,2:仇人,3:好友,4：粉丝或陌生人
                    if($friend){
//                        $myFriend = DBManager::isFriend($condition, $userId);
                        $returnData['userInfo']['relationship'] = 3;
//                        $returnData['userInfo']['is_look'] = $friend->is_look;
//                        $returnData['userInfo']['disturb'] = $friend->disturb;
//                        if($myFriend){
//                            $returnData['userInfo']['forbid_look'] = $myFriend->forbid_look;
//                        }
                    }else{
                        $returnData['userInfo']['relationship'] = 4;
                    }
                }
//                $returnData['userInfo']['moments'] = $momentsData;
            }
            // 搜索家族
            $search = DBManager::searchAssociation($condition);

            if ($search) {
                // 获取家族/群组全部成员
                $associationMember = DBManager::getAssociationMember($di, $search->id, $pageIndex);
                $returnData = ReturnMessageManager::buildFamilyDetailInfo($userId, $search, $associationMember);
//                $returnData['familyInfo'] = ReturnMessageManager::buildAssociation($search);
            }

            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 搜索
     * 类型 1：用户 2：家族 3：家族成员
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processSearchByType($app, $di) {
        try {
            // 检查用户是否存在
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 搜索条件
            $condition = trim($_POST['condition']);
            if(!$condition){ return ReturnMessageManager::buildReturnMessage('E0152',null); }

            // 搜索类型
            $type = (int)$_POST['type'];
            switch ($type) {
                case 1:
                    $userList = DBManager::searchUser($condition);
                    $errorCode = 'E0100';
                    break;
                case 2:
                    $familyList = DBManager::searchAssociation($condition);
                    $errorCode = 'E0101';
                    break;
                case 3:
                    $groupId = $_POST['groupId'];
                    $association = DBManager::getAssociationByGroupId($groupId);
                    if (!$association) { return ReturnMessageManager::buildReturnMessage('E0113', null);}
                    // 用户是否为家族成员
                    $userAssociation = DBManager::checkUserAssociation($userId, $association->id);
                    if(!$userAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }
                    $familyMembers = DBManager::searchAssociationMember($di, $condition, $association->id);
                    $errorCode = 'E0102';
                    break;
            }

            // 返回结果
            $returnData = ReturnMessageManager::buildSearchResult($userList, $familyList, $familyMembers);
            if(!$returnData || count($returnData) == 0) {
                return ReturnMessageManager::buildReturnMessage($errorCode, null);
            }
            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取用户称号
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetUserTitle($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取称号列表
            $titleList = DBManager::getTitleList();

            // 处理返回数据
            $returnData = ReturnMessageManager::buildUserTitle($titleList, $user);

            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * username获取用户数据
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetUserByUsername($app, $di) {
        try{
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $targetName = $_POST['targetName'];
            if(!$targetName){ return ReturnMessageManager::buildReturnMessage('E0143',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            // 获取群组信息
            $association = DBManager::getClusterByGroupId($targetName);
            if($association){
                // 获取群成员
                $member = DBManager::getAssociationMemberByUserId($userId, $association->id);
                $association = $association->toArray();
                $association['familyAvatar'] = OssApi::procOssPic($association['assoc_avatar']);
                $association['familyThumb'] = OssApi::procOssThumb($association['assoc_thumb']);
                $association['familyLevel'] = $association['level'];
                $association['user_type'] = $member ? $member->user_type : 4;
                $association['perm'] = $member ? $member->perm : '00000000';
                $association['shutUp'] = $member ? $member->shut_up : 0;
                unset($association['confirm']);
                return ReturnMessageManager::buildReturnMessage('E0000',$association);
            }else{
                $target = DBManager::getUserById($targetName);
                if(!$target){ return ReturnMessageManager::buildReturnMessage('E0005',null); }

                $userAssociation = DBManager::getUserAssociation($targetName);
                // 获取用户粉丝数量和关注数量
                $fansAndFollows = DBManager::getUserFansAndFollowNum($targetName);
                // 获取说说总数,礼物总数
                $momentsNum = DBManager::getUserMomentsNum($user->id);
                $giftNum = DBManager::getUserGiftNum($user->id);
                $userData = ReturnMessageManager::buildUserInfo($target, null, $userAssociation,$fansAndFollows, $momentsNum, $giftNum);

                // 处理数据
                if($userId == $targetName){
                    $userData['is_attention'] = 0;
                    $userData['relationship'] = 1;
                }else{
                    // 判断是否为关注、粉丝
                    $attention = DBManager::checkAttention($userId, $targetName);
                    $friend = DBManager::isFriend($userId, $targetName);

                    // 是否关注
                    if(!$attention){
                        $userData['is_attention'] = 0;
                    }else{
                        $userData['is_attention'] = 1;
                    }

	                // 检查是否看用户的朋友圈
	                $userRelationPerm = DBManager::getUserRelationPerm($userId, $target->id);
	                $is_look = $userRelationPerm ? $userRelationPerm->is_look : LOOK_UMOMENTS_YES;
	                $userData['is_look'] = $is_look;

	                // 是否是朋友
                    if($friend){
//                        $myFriend = DBManager::isFriend($targetName, $userId);
                        $userData['relationship'] = 3;
                        $userData['disturb'] = $friend->disturb;
//                        if($myFriend){
//                            $userData['forbid_look'] = $myFriend->forbid_look;
//                        }
                    } else {
                        $userData['relationship'] = 4;
                    }
                }
                $userData['type'] = 3;
                return ReturnMessageManager::buildReturnMessage('E0000', array('userInfo' => $userData));
            }
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取新消息数量
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processBadge($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取用户好友申请,家族申请数量,家族邀请数量总和
            $newApplyData = DBManager::getUserFriendNum($userId, 1);

            // 获取用户新的粉丝数量
            $newFansNum = DBManager::getUserNewFansNum($userId);

            $returnData = ReturnMessageManager::buildNewMessageNum($userId, $newApplyData, $newFansNum);

            return ReturnMessageManager::buildReturnMessage('E0000', array('newMessage' => $returnData));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 好友验证开关
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processFriendVerify($app, $di)
    {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 更改好友验证数据
            $result = DBManager::changeFriendVerify($user);
            return ReturnMessageManager::buildReturnMessage('E0000', null);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 设置人物封面图片(Android)
     *
     * @param $di
     * @return string
     */
    public static function processSetBackgroundPicture($di)
    {
        try {
            // 参数检查
            $userId = trim($_POST['userId']);
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            //获取用户
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }
            // 获取更新类别, 获取OSS存储空间
            $oss_bucket = OSS_BUCKET_BG;
            // OSS上传
            $uploadRS = OssProxy::ossUploadFile($di, $oss_bucket, $userId, UPLOAD_BUSS_BG, 'pri_url');
            // 获取旧背景地址
            $old_uri = $user->background;
            // 检查是否成功
            if ($uploadRS) {
                // 构建保存的图片资源信息
                $imgUrl = $oss_bucket.';'.$uploadRS['oss-request-url'];
                if ($old_uri) {
	                $ossConfig = $di->get('config')->ossConfig;
                    // 删除旧的头像
                    $ossDelRs =  OssApi::deleteFile($ossConfig, $old_uri);
                    // 如果删除失败了, 存入OSS失败队列, 留待以后处理
                    if (!$ossDelRs) {
                        // 删除失败的头像, 应该存入一个列表中, 后期维护该列表执行删除任务
                        $ossFdelQueue = new OssFdelQueue();
                        // 存储该条记录
                        $ossFdelQueue->save(array('resource' => $old_uri));
                    }
                }
                // 修改用户的背景图片
                $user = DBManager::updateUserBackgroundPic($user, $imgUrl, $uploadRS['thumb']);
                // 检查用户
                if ($user) {
                    return ReturnMessageManager::buildReturnMessage('E0000', [
                        'background' => OssApi::procOssPic($user->background),
                        'background_thumb' => $user->background_thumb
                    ]);
                } else {
                    return ReturnMessageManager::buildReturnMessage('E0247', null);
                }
            } else {
                return ReturnMessageManager::buildReturnMessage('E0084',null);
            }
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取用户标签
     *
     * @param $di
     * @return mixed
     */
    public static function processTagList($di)
    {
    	try {
		    // 参数检查
		    $uid = trim($_POST['userId']);
		    if(!$uid){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

		    //获取用户
		    $user = DBManager::getUserById($uid);
		    if (!$user) {
			    return ReturnMessageManager::buildReturnMessage('E0044', null);
		    }
		    $tmpUserTags = DBManager::getUserTagsWithTag($di, $uid);
		    $userTags = [];
		    foreach($tmpUserTags as $tmpUserTag) {
		    	array_push($userTags, [
		    		'id' => $tmpUserTag->ut->tag_id,
				    'tag' => $tmpUserTag->tag
			    ]);
		    }
//		    // 获取系统推荐标签
		    $data = DBManager::getSystemRcmdByUserTags($userTags);

    	    return ReturnMessageManager::buildReturnMessage('E0000', ['tags' => $data]);
	    } catch (Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
    }

    /**
     * 获取用户实名
     *
     * @param $di
     * @return mixed
     */
    public static function processGetRealName($di)
    {
        try {
	        $uid = $_POST['userId'];
	        //获取用户
	        $user = DBManager::getUserById($uid);
	        if (!$user) {
		        return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        if (!$user->id_code){
		        return ReturnMessageManager::buildReturnMessage('E0291');
	        }
	        // 构建返回
	        return ReturnMessageManager::buildReturnMessage('E0000', [
		        'userId' => $uid,
		        'userRealName' => Utils::hiddenRealName($user->name),
		        'idCode' => Utils::hiddenIdCode($user->id_code)
	        ]);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }


    /*
     *  TODO 好友相关
     */

    /**
     * 申请添加好友
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processApplyAddFriend($app ,$di, $hxConfig) {
        try{
        	$errCode = 'E0310';
            // 参数检查
            $userId = $_POST['userId'];
            $targetId = $_POST['targetId'];
            $message = isset($_POST['message']) ? $_POST['message'] : ' ';
            unset($_POST['message']);
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0018',null); }

            // 如果添加的是自己
            if($userId == $targetId){ return ReturnMessageManager::buildReturnMessage('E0066',null); }

            // 检查用户是否存在
            $target = DBManager::getUserById($targetId);
            if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $requestUser = DBManager::getUserById($userId);
            if(!$requestUser){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检测是否已是好友
            $friend = DBManager::isFriend($userId, $targetId);
            if($friend){ return ReturnMessageManager::buildReturnMessage('E0043',null); }

	        // 检查用户当前等级是否可以加入新的好友
	        if(!DBManager::checkUserLevelLimit($userId, $requestUser->level, 1)) {
		        return ReturnMessageManager::buildReturnMessage('E0302');
	        }

            // 判断是否需要验证
            if($target->verify == 1){
                $oldFriendRequest = DBManager::getFriendRequest($userId, $targetId);
                if($oldFriendRequest){
                    if (!DBManager::updateOldFriendRequest($di, $oldFriendRequest, $requestUser, $target, $message)) {
                        return ReturnMessageManager::buildReturnMessage('E0309');
                    }
                } else {
                    if (!DBManager::applyFriendRequest($di, $requestUser, $target, $message)) {
	                    return ReturnMessageManager::buildReturnMessage('E0309');
                    }
                }
            } else {
            	// 为双方添加好友关系
	            if (!DBManager::addFriend($di, $userId, $targetId)) {
	                return ReturnMessageManager::buildReturnMessage('E0310');
	            }
            }
	        // 事务提交
	        if (!Utils::commitTc($di)) {
            	return ReturnMessageManager::buildReturnMessage($errCode);
	        }
	        // 发送透传消息
	        $action = "haveNewFriend";
	        HxChatProxy::sendSilenceMessage(array($targetId), $action, $hxConfig);
	        // 返回
            return ReturnMessageManager::buildReturnMessage('E0000');
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 删除好友
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processDelFriend($app, $di) {
        try {
	        //参数检查
	        $userId = $_POST['userId'];
	        $friendId = $_POST['friendId'];
//	        $userId = $_GET['userId'];
//	        $friendId = $_GET['friendId'];
	        if (!$userId) {
		        return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }
	        if (!$friendId) {
		        return ReturnMessageManager::buildReturnMessage('E0018', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
		        return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }
	        $friend = DBManager::getUserById($friendId);
	        if (!$friend) {
		        return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        // 检查是否存在好友关系
	        $isFriend = DBManager::checkFriend($userId, $friendId);
	        if (!$isFriend) {
		        return ReturnMessageManager::buildReturnMessage('E0059', null);
	        }

	        // 删除好友
	        $delResult = DBManager::delFriend($di, $userId, $friendId);
	        if (!$delResult) {
		        return ReturnMessageManager::buildReturnMessage('E0241');
	        }
	        // 发送透传消息
	        MessageSender::sendUserRemFriend($di, $user ,$friend);
	        $redis = RedisClient::create($di->get('config')['redis']);
	        RedisManager::pushWeek($redis, RedisClient::weekFriendKey(), $userId, 0, 1);
	        // 还行回滚
	        if (!Utils::commitTc($di)) {
	        	return ReturnMessageManager::buildReturnMessage('E0241');
	        }
	        return ReturnMessageManager::buildReturnMessage('E0000', ['deleteSuccess' => '1']);
        } catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 添加/取消关注
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processFollowOrNot($app, $di, $hxConfig) {
        try {
            // 参数检查
            $userId = $_POST['userId'];
            $targetId = $_POST['targetId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0019',null); }

            // 如果关注的是自己
            if($userId == $targetId){ return ReturnMessageManager::buildReturnMessage('E0066',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $target = DBManager::getUserById($targetId);
            if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

	        $redis = $di->getShared(SERVICE_REDIS);

            // 查询关注人数是否达到等级对应的上限
            $attentionList = DBManager::getAttentionList($userId, 1);
            $userAttr = DBManager::getUserCurLevel($user->level);
            if(count($attentionList) >= $userAttr['atten_num']){ return ReturnMessageManager::buildReturnMessage('E0107',null); }

            // 检查是否已经关注
            $attention = DBManager::checkAttention($userId, $targetId);

            if($attention){
                // 关注时间低于两小时，不能取消关注
                $now = date('Y-m-d H:i:s',time());
                if(strtotime($now) - strtotime($attention->create_time) < 120 * 60) {
                    return ReturnMessageManager::buildReturnMessage('E0323',null);
                }
	            $isFollow = 0;
	            $errCode = 'E0096';//取消关注失败
            	// 获取目标用户的关注状态
	            $targetAttention = DBManager::checkAttention($targetId, $userId);
	            // 取消关注
                if(DBManager::delAttention($di, $attention, $targetAttention)){
                    // 是否互粉,取消互粉
                    if($targetAttention){
                        if (!DBManager::updateAttentionData($di, $attention, $targetAttention, 2)) {
                            return ReturnMessageManager::buildReturnMessage('E0096');
                        }
                    }
                    // 取消关注, 从周排行中减去一个
                    RedisManager::pushWeek($redis, RedisClient::weekFansKey(), $userId, 0, 1);
                } else {
                    return ReturnMessageManager::buildReturnMessage('E0096');
                }
            } else {
            	$isFollow = 1;
	            $errCode = 'E0312';
	            // 检查目标用户是否关注你
	            $targetAttention = DBManager::checkAttention($targetId, $userId);
                // 插入关注信息
	            $commit = true;
	            if ($targetAttention) {
		           $commit = false;
	            }
	            // 插入关注数据
	            $attention = DBManager::insertAttentionData($di, $user, $target, $commit);
                if($targetAttention){
                    if(!DBManager::updateAttentionData($di, $attention, $targetAttention, 1) ) {
                        return ReturnMessageManager::buildReturnMessage('E0312');
                    }
                }
	            // 发送关注信息
	            if (!MessageSender::sendUserNewFans($di, $user, $target)) {
		            return ReturnMessageManager::buildReturnMessage('E0312');
	            }
	            // 关注, 从周排行中添加一个
	            RedisManager::pushWeek($redis, RedisClient::weekFansKey(), $userId, 1, 1);
                // 发送透传消息
                $action = "fightChat";
                HxChatProxy::sendSilenceMessage(array($targetId), $action, $hxConfig);
            }
            // 提交
	        if (!Utils::commitTc($di)) {
                return ReturnMessageManager::buildReturnMessage($errCode);
            }
	        return ReturnMessageManager::buildReturnMessage('E0000', ['isFollowed' => $isFollow]);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 批量关注
     *
     * @param $di
     * @return mixed
     */
	public static function processFollowManyUsers($di) {
        try {
        	$errCode = 'E0311';
        	$redis = $di->getShared(SERVICE_REDIS);
	        // 参数检查
	        $userId = $_POST['userId'];
	        if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); };

	        // 查询关注人数上限1000
	        $attentionList = DBManager::getAttentionList($userId, 1);
	        if ($attentionList) {
		        $attentionCount = count($attentionList);
	        } else {
	        	$attentionCount = 0;
	        }
	        $targetIdsArr = json_decode($_POST['targetIds'], true);
	        if ($targetIdsArr) {
		        $targetIds = '';
		        foreach($targetIdsArr as $targetId) {
			        if ($targetIds == '') {
				        $targetIds = $targetId;
			        } else {
				        $targetIds .= ','.$targetId;
			        }
		        }
		        $targetUsers = User::find("id in (".$targetIds.")");
		        // 目标用户的数量
		        $targetCount = count($targetUsers);
		        // 比较此次是否可以添加这么多的用户
		        if ($attentionCount + $targetCount < 1000) {
			        // 新粉丝数量
			        $newFans = DBManager::insertMultAttentions($di, $user, $targetUsers);
		        	if ($newFans === false) {
		        	    return ReturnMessageManager::buildReturnMessage($errCode);
			        }
			        $attentionCount += count($newFans);
			        // 关注, 从周排行中添加一个
			        RedisManager::pushWeek($redis, RedisClient::weekFansKey(), $userId, 1, count($newFans));
		        	// 发送用户消息
			        if (!MessageSender::sendUserNewMutiFans($di, $user, $newFans)) {
			            return ReturnMessageManager::buildReturnMessage($errCode);
			        }
		        }
	        }
            $followStatus = 1;
	        $returnData = [
	        	'followStatus' => $followStatus,
		        'followNumber' => $attentionCount
	        ];
	        // 提交
	        if (!Utils::commitTc($di)) {
		        return ReturnMessageManager::buildReturnMessage($errCode);
	        }
		    return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
	}

    /**
     * 获取用户好友列表
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetFriends($app, $di) {
        try{
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }


            // 获取用户好友列表
            $friendsObj = DBManager::getFriends($userId);
            $friendsData = ReturnMessageManager::builGetFriendsData($friendsObj, 3);

            return ReturnMessageManager::buildReturnMessage('E0000', array('friends' => $friendsData));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取用户粉丝列表
     *
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processGetFans($di, $hxConfig) {
        try{
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            $friendsObj = DBManager::getFans($userId);
            $friendsData = ReturnMessageManager::builGetFriendsData($friendsObj, 4);
            // 清楚用户新粉丝标记
            DBManager::clearUserFansBadge($userId);

            // 发送透传消息
            $action = "daKa";
            HxChatProxy::sendSilenceMessage(array($userId), $action, $hxConfig);

            return ReturnMessageManager::buildReturnMessage('E0000', array('fans' => $friendsData));
        }catch (\Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取用户关注列表
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetAttentions($app, $di) {
        try{
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取用户关注列表
            $friendsObj = DBManager::getFocus($userId);
            $friendsData = ReturnMessageManager::builGetFriendsData($friendsObj, 5);

            return ReturnMessageManager::buildReturnMessage('E0000', array('attentions' => $friendsData));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 随机分配陌生人列表
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processRandomBattleList($app, $di) {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 随机分配people
            $randomPeopleData = DBManager::randomPeople($app, $user, $userId);

            // 处理返回数据
            $randomData = ReturnMessageManager::buildRandomPlayerData($randomPeopleData);
            if(!$randomData){ return ReturnMessageManager::buildReturnMessage('E0098',null); }

            return ReturnMessageManager::buildReturnMessage('E0000',array('strangers' => $randomData));
        }catch(\Exception $e){
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 消息免打扰
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processMessageDoNotDisturb($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $targetId = isset($_POST['targetId']) ? $_POST['targetId'] : '';
            $groupId = isset($_POST['groupId']) ? $_POST['groupId'] : '';

            if (!$targetId && !$groupId) {
                return ReturnMessageManager::buildReturnMessage('E0242',null);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            if ($targetId) {
                $target = DBManager::getUserById($targetId);
                if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

                // 确定是否好友关系
                $friend = DBManager::isFriendByNotApply($userId, $targetId);
                if(!$friend){ return ReturnMessageManager::buildReturnMessage('E0059',null); }

                // 消息免打扰
                $result = DBManager::messageDoNotDisturb($friend);
                if ($result->disturb == 1) {
                    $isAvoid = 1;
                } else {
                    $isAvoid = 0;
                }

            } else if ($groupId) {
                // 查询群组是否存在
                $groupChat = DBManager::getAssociationByGroupId($groupId);
                if (!$groupChat) {
                    return ReturnMessageManager::buildReturnMessage('E0160',null);
                }
                // 判断是否属于群组成员
                $belongGroupChat = DBManager::checkUserAssociation($userId, $groupChat->id);
                if (!$belongGroupChat) {
                    return ReturnMessageManager::buildReturnMessage('E0240',null);
                }
                // 设定群组消息免打扰
                $messageFree = DBManager::GroupChatMessageFree($belongGroupChat);
                $isAvoid = $messageFree->confirm;

            }
            // 获取免打扰用户列表
            $userList = DBManager::getFriends($userId);
            $returnData = ReturnMessageManager::builGetFriendsData($userList, 3);

            // 获取免打扰群组列表
            $groupChatList = DBManager::getGroupChatMessageFreeList($userId);
            $returnGroupList = ReturnMessageManager::buildGetGroupChatMessageFreeList($groupChatList);

            return ReturnMessageManager::buildReturnMessage('E0000', array('isAvoid' => $isAvoid, 'groupList' => $returnGroupList));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取消息免打扰的好友列表
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processMessageDoNotDisturbUsers($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取免打扰用户列表
            $userList = DBManager::getDoNotDisturbUserList($userId);
            $returnData = ReturnMessageManager::builGetFriendsData($userList, 3);

            // 获取免打扰群组列表
            $groupChatList = DBManager::getGroupChatMessageFreeList($userId);
            $returnGroupList = ReturnMessageManager::buildGetGroupChatMessageFreeList($groupChatList);

            return ReturnMessageManager::buildReturnMessage('E0000', array('userList' => $returnData, 'groupList' => $returnGroupList));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取请求列表
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetApplyList($app, $di) {
        try {
	        $hxConfig = $di->get('config')['hxConfig'];
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013'); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044'); }

            // 类型
	        $type = $_POST['type'];
            // 1: 好友, 2: 家族
            if (!in_array($type, [1, 2])) {
                return ReturnMessageManager::buildReturnMessage('E0089');
            }

            $pageIndex = (int)$_POST['pageIndex'];

            if ($type == 1) {
                // 获取请求好友列表
                $requestList = DBManager::getRequestFriend($userId, $pageIndex);
            } else {
                // 获取关于家族的请求列表
                $requestList = DBManager::getRequestFamily($app, $userId, $pageIndex);
            }
            // 清除新请求标记
            if($requestList) {
                DBManager::clearUserNewFriendRequestBadge($requestList);
            }

            // 发送透传消息
            $action = "daKa";
            HxChatProxy::sendSilenceMessage(array($userId), $action, $hxConfig);

            // 处理返回数据
            $requestData = ReturnMessageManager::buildApplyListInfo($type, $requestList);
            if(!$requestData){
                $errorCode = $type == 1 ? 'E0137' : 'E0138';
                return ReturnMessageManager::buildReturnMessage($errorCode, $requestData);
            }

            return ReturnMessageManager::buildReturnMessage('E0000', $requestData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 检查是否有新消息通知
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processCheckNewNotice($app, $di) {
        try {
            // 检查用户是否存在
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013'); }
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044'); }
            // 检查说说是否存在
            $friendMomentId = $_POST['friendMomentId'];
            if($friendMomentId != 0) {
                $friendMoment = DBManager::getMomentsByMomentsId($friendMomentId);
                if(!$friendMoment){ return ReturnMessageManager::buildReturnMessage('E0135',null); }
            }
            $attentionMomentId = $_POST['attentionMomentId'];
            if($attentionMomentId != 0) {
                $attentionMoment = DBManager::getMomentsByMomentsId($attentionMomentId);
                if(!$attentionMoment){ return ReturnMessageManager::buildReturnMessage('E0135',null); }
            }
            // 检查好友申请
            $isNewFriendRequest = DBManager::checkNewRequestFriend($userId);
            // 检查家族申请
            $isNewFamilyRequest = DBManager::checkNewRequestFamily($app, $userId);
            // 检查好友是否有新动态
            $isNewFriendMoment = DBManager::checkNewFriendMoment($app, $userId, $friendMomentId);
            // 检查关注用户是否有新动态
            $isNewAttentionMoment = DBManager::checkNewAttentionMoment($app, $userId, $attentionMomentId);
            // 检查是否有新的左侧栏用户消息
            $isNewUserMsg = DBManager::checkNewUserMsg($userId);
            // 返回结果
            $returnData = array(
                'isNewFriendRequest' => $isNewFriendRequest,
                'isNewFamilyRequest' => $isNewFamilyRequest,
                'isNewFriendMoment' => $isNewFriendMoment,
                'isNewAttentionMoment' => $isNewAttentionMoment,
                'isNewUserMsg' => $isNewUserMsg
            );
            return ReturnMessageManager::buildReturnMessage('E0000', ['newNotice' => $returnData]);
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 确认添加好友
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return string
     */
    public static function processAllowAddFriend($di, $hxConfig) {
        try {
	        $errCode = 'E0310';
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $requestId = $_POST['requestId'];
            if(!$requestId){ return ReturnMessageManager::buildReturnMessage('E0236',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取申请好友信息
	        $friendRequest = DBManager::getFriendRequestById($requestId);
            if (!$friendRequest) {
            	return ReturnMessageManager::buildReturnMessage('E0117');
            }
            $friendId = $friendRequest->user_id;
            if ($friendRequest->status == 1) {
            	return ReturnMessageManager::buildReturnMessage('E0000', ['friend_id' => $friendId]);
            }

            // 检查用户等级限制
            $checkResult = DBManager::checkUserLevelLimit($userId, $user->level, 1);
            if(!$checkResult) {
                return ReturnMessageManager::buildReturnMessage('E0302',null);
            }

            // 检查是否申请添加好友
//            $applyFriend = DBManager::getApplyFriend($friendId, $userId);
//            if(!$applyFriend){ return ReturnMessageManager::buildReturnMessage('E0117',null); }

//            $friendRequest = DBManager::getFriendRequest($friendId, $userId);
            DBManager::updateFriendRequest($di, $friendRequest);

            // 检测是否以前是好友
//            $oldFriend = DBManager::getOldFriend($userId, $friendId);



			if (!DBManager::addFriend($di, $userId, $friendId)) {
				return ReturnMessageManager::buildReturnMessage('E0310');
			}

            $redis = RedisClient::create($di->get('config')['redis']);
            RedisManager::pushWeek($redis, RedisClient::weekFriendKey(), $userId, 1, 1);
            $redis->close();

            // 发送用户消息
            MessageSender::sendUserPassFriend($di, $user, $friendId);
	        return Utils::commitTcReturn($di, ['friend_id' => $friendId], $errCode);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 删除好友申请
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processDeleteAddFriend($app, $di)
    {
        try {
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $requestId = $_POST['requestId'];
            if(!$requestId){ return ReturnMessageManager::buildReturnMessage('E0236',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取申请好友信息
            $applyInfo = DBManager::getFriendRequestById($requestId);
            if (!$applyInfo) {
                return ReturnMessageManager::buildReturnMessage('E0117', null);
            }
            // 删除申请记录
            $result = DBManager::delFriendRequest($applyInfo);
            if ($result) {
                return ReturnMessageManager::buildReturnMessage('E0000', null);
            } else {
                return ReturnMessageManager::buildReturnMessage('E0243', null);
            }
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 确认添加家族
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return string
     */
    public static function processAllowAddFamily($app ,$di, $hxConfig) {
        try {
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $requestId = $_POST['requestId'];
            if(!$requestId){ return ReturnMessageManager::buildReturnMessage('E0236',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取申请信息
            $applyInfo = DBManager::getAssociationRequestById($requestId);
            if (!$applyInfo) {
                return ReturnMessageManager::buildReturnMessage('E0117',null);
            }
            $friendId = $applyInfo->user_id;
            // 获取friend信息
            $friend = DBManager::getUserById($friendId);

            // 获取家族信息
            $association = $applyInfo->association;

            // 检查用户是否有权限操作
            if($applyInfo->inviter_id == 0) {
                if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_MG_FAMILYMEMBERS)) {
                    return ReturnMessageManager::buildReturnMessage('E0120');
                }
            }

            // 检查家族是否达到成员上限
            if($association->current_number >= $association->associationLevel->member_limit) {
	            return ReturnMessageManager::buildReturnMessage('E0125',null);
	        }

            // 用户是否有家族
            // 邀请加入家族
            if ($applyInfo->inviter_id != 0) {
                $username = $userId;
                $associationMember = DBManager::getAssociationMemberByUserId($username, $association->id);
                if($associationMember) {
                    return ReturnMessageManager::buildReturnMessage('E0173',null);
                }
                $checkResult = DBManager::checkUserLevelLimit($userId, $user->level, 2);
                if(!$checkResult) {
                    return ReturnMessageManager::buildReturnMessage('E0302',null);
                }
                $isAdmin = DBManager::checkAssociationUserType($applyInfo->inviter_id, $association->id);
                if(!$isAdmin) {
                    $applyInfo->inviter_id = 0;
                    $applyInfo->save();
                    // 批量添加角标
                    $admins = DBManager::getAssociationAdminList($association->id);
                    // 获取具有权限的管理员ID列表
                    $adminIds = DBManager::getAdminsByPerm($admins, FMPERM_MG_FAMILYMEMBERS);
                    $action = "haveNewFamily";
                    // 如果有则发送消息
                    HxChatProxy::sendSilenceMessage($adminIds, $action, $hxConfig);
                    return ReturnMessageManager::buildReturnMessage('E0000', array('family_id' => $association->group_id));
                }
                // 申请加入家族
            } else {
                $username = $friendId;
                $associationMember = DBManager::getAssociationMemberByUserId($username, $association->id);
                if($associationMember) {
                    return ReturnMessageManager::buildReturnMessage('E0173',null);
                }
                $checkResult = DBManager::checkUserLevelLimit($friendId, $friend->level, 2);
                if(!$checkResult) {
                    return ReturnMessageManager::buildReturnMessage('E0302',null);
                }
            }

            // 环信加入家族
            $hxAddAssociation = HxChatProxy::addGroupMember($association->group_id, $username, $hxConfig);
            if(!$hxAddAssociation){ return ReturnMessageManager::buildReturnMessage('E0164',null); }

            // 更新请求记录的状态
            DBManager::confirmAddAssociation($applyInfo);
            // 添加成员
            if($applyInfo->inviter_id != 0){
                DBManager::addAssociationMember($userId, $user->nickname, $association->id,2);
            }else {
                DBManager::addAssociationMember($friendId, $friend->nickname, $association->id,2);
            }
            $association = DBManager::updateAssociation($association, 1);
            // 发送用户消息
            MessageSender::sendUserPassFamily($di, $username, $association);
            $returnData = array('family_id' => $association->group_id);
            return Utils::commitTcReturn($di, $returnData, 'E0000');
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 删除家族申请
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processDeleteAddFamily($app, $di)
    {
        try {
            // 参数检查
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $requestId = $_POST['requestId'];
            if(!$requestId){ return ReturnMessageManager::buildReturnMessage('E0236',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取申请家族信息
            $applyInfo = DBManager::getAssociationRequestById($requestId);
            if (!$applyInfo) {
                return ReturnMessageManager::buildReturnMessage('E0117', null);
            }

            // 删除申请记录
            $result = DBManager::delFriendRequest($applyInfo);
            if ($result) {
                return ReturnMessageManager::buildReturnMessage('E0000', null);
            } else {
                return ReturnMessageManager::buildReturnMessage('E0244', null);
            }

        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取推荐用户
     *
     * @param $di
     * @return mixed
     */
    public static function processGetRecommandUser($di)
    {
        try {
	        // 参数检查
	        $uid = $_POST['userId'];
	        // 检查用户是否存在
	        $user = DBManager::getUserById($uid);
	        if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $redis = RedisClient::create($di->get('config')['redis']);
            $key = RedisClient::weekActiveKey();
            $data = array();
            if($key) {
                $result = $redis->zRevRange($key, 0, 49, true);
                $result = Utils::sortByKeyAndSameValue($result);
                if($result) {
                    $randNum = count($result) > 10 ? 10 : count($result);
                    $randResult = array_rand($result, $randNum);
                    $idx = 0;
                    $rankInfo = array();
                    if(is_array($randResult)) {
                        foreach ($result as $rankId => $value) {
                            foreach ($randResult as $index => $res) {
                                if($rankId == $res) {
                                    $rankInfo[$rankId] = $value;
                                }
                            }
                        }
                    } else {
                        foreach ($result as $rankId => $value) {
                            if($rankId == $randResult) {
                                $rankInfo[$rankId] = $value;
                            }
                        }
                    }
                    foreach ($rankInfo as $rankId => $value) {
                        $user = DBManager::getUserById($rankId);
                        $data[$idx]['user_id'] = $rankId;
                        $data[$idx]['nickname'] = $user->nickname;
                        $data[$idx]['gender'] = $user->gender;
                        $data[$idx]['userAvatar'] =  OssProxy::procOssPic($user->user_avatar);
                        $data[$idx]['userLevel'] = $user->level;
                        $data[$idx]['activeNum'] = round($value, 2) * 100;
                        // 获取推荐用户可领取的金额
                        $amount = DBManager::getUserCanGrabAmount($di, $rankId);
                        $data[$idx]['amount'] = $amount;
                        $idx ++;
                    }
                }
            }
	        // 返回
            return ReturnMessageManager::buildReturnMessage('E0000', [ 'recommendUsers' => $data]);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }


    /*
     *  TODO 家族、群聊相关
     */

    /**
     * 获取家族列表
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetFamilies($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取用户所有家族
            $associationList = DBManager::getUserAllAssociations($userId);

            // 获取推荐的家族
            $recommandAssocList = DBManager::randomAssociationList($associationList);

            // 处理数据
            $associationListData = ReturnMessageManager::buildMyAssociationList($associationList, $recommandAssocList);
            return ReturnMessageManager::buildReturnMessage('E0000', $associationListData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 申请加入家族
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processApplyAssociation($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }
            $message = isset($_POST['message']) ? $_POST['message'] : '';

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查用户等级限制
            $checkResult = DBManager::checkUserLevelLimit($userId, $user->level, 2);
            if(!$checkResult) {
                return ReturnMessageManager::buildReturnMessage('E0302',null);
            }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 检查家族人员是否达到上限
            if($association->current_number >= $association->associationLevel->member_limit) {
                return ReturnMessageManager::buildReturnMessage('E0125',null);
            }

            // 家族是否开启验证
            if ($association->confirm == 0) {
                // 环信加入家族
                $hxAddAssociation = HxChatProxy::addGroupMember($association->group_id, $userId, $hxConfig);
                if(!$hxAddAssociation){ return ReturnMessageManager::buildReturnMessage('E0164',null); }
                // 确认添加成员
                DBManager::addAssociationMember($userId, $user->nickname, $association->id, 2);
                // 更新大咖群的当前人数
                DBManager::updateAssociation($association, 1);
                return ReturnMessageManager::buildReturnMessage('E0000', array('family_id' => $association->group_id));
            }
            // 检查用户是否申请过该家族
            $applyRecord = DBManager::isApplyAssociation($userId, $association->id);
            if(count($applyRecord) > 0){
                // 更新申请记录
                $result = DBManager::updateApplyAssociation($applyRecord[0], $message);
                return ReturnMessageManager::buildReturnMessage('E0000');
            }

	        // 检查用户当前等级是否可以加入新的家族
	        if(!DBManager::checkUserLevelLimit($userId, $user->level, 2)) {
		        return ReturnMessageManager::buildReturnMessage('E0302');
	        }
			// 批量添加角标
	        $admins = DBManager::getAssociationAdminList($association->id);
	        // 获取具有权限的管理员ID列表
	        $adminIds = DBManager::getAdminsByPerm($admins, FMPERM_MG_FAMILYMEMBERS);
            // 申请加入家族
            DBManager::applyAddAssociation($di, $user, $association, $adminIds, $message);

            $action = "haveNewFamily";
            // 如果有则发送消息
            HxChatProxy::sendSilenceMessage($adminIds, $action, $hxConfig);
            return ReturnMessageManager::buildReturnMessage('E0000');
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 批量申请家族
     *
     * @param $di
     * @return mixed
     */
	public static function processJoinManyAssociation($di) {
    	try {
		    // 环信配置
		    $hxConfig = $di->get('config')['hxConfig'];
		    // 用户ID
		    $userId = $_POST['userId'];
		    if (!$userId) {
			    return ReturnMessageManager::buildReturnMessage('E0013');
		    }
		    $familyCount = count(DBManager::getUserAllAssociations($userId));
		    // 群组ID
		    $groupIdsArr = json_decode($_POST['familyIds']);
		    if ($groupIdsArr) {
			    // 检查用户是否存在
			    $user = DBManager::getUserById($userId);
			    if (!$user) {
				    return ReturnMessageManager::buildReturnMessage('E0044');
			    }

			    // 获取所有的群组
			    $groupIds = '';
			    foreach ($groupIdsArr as $groupId) {
				    if ($groupIds) {
					    $groupIds .= ',' . $groupId;
				    } else {
					    $groupIds = $groupId;
				    }
			    }

			    $sql = "SELECT * FROM Fichat\Models\Association WHERE group_id in (" . $groupIds . ")";
			    $query = new Query($sql, $di);
			    // 获取所有的群组数据
			    $associations = $query->execute();
			    $message = $user->nickname . "根据系统推荐申请加入";
			    // 遍历家族
			    foreach ($associations as $association) {
			        // 检查家族是否达到成员上限
                    if($association->current_number >= $association->associationLevel->member_limit) continue;
                    // 检查该用户是否已加入家族
                    if (DBManager::checkUserAssociation($userId, $association->id)) continue;

                    // 家族是否开启验证
                    if ($association->confirm == 0) {
                        // 环信加入家族
                        $hxAddAssociation = HxChatProxy::addGroupMember($association->group_id, $userId, $hxConfig);
                        if(!$hxAddAssociation){ return ReturnMessageManager::buildReturnMessage('E0164',null); }
                        // 确认添加成员
                        DBManager::addAssociationMember($userId, $user->nickname, $association->id, 2);
                        // 更新大咖群的成员人数
                        DBManager::updateAssociation($association, 1);
                    } else {
                        // 检查用户是否申请过该家族
                        $applyRecord = DBManager::isApplyAssociation($userId, $association->id);
                        if (count($applyRecord) > 0) {
                            // 更新申请记录
                            DBManager::updateApplyAssociation($applyRecord[0], $message);
                        } else {
                            // 批量添加角标
                            $admins = DBManager::getAssociationAdminList($association->id);
                            // 批量获取管理员id
                            $usernameList = DBManager::getMemberUsernameList($admins);
                            // 申请加入家族
                            DBManager::applyAddAssociation($di, $user, $association, $usernameList, $message);
                            $action = "haveNewFamily";
                            HxChatProxy::sendSilenceMessage($usernameList, $action, $hxConfig);
                        }
                    }
                }

		    }

		    // 返回
		    $returnData = [
			    'applyStatus' => 1,
			    'familyNumber' => $familyCount
		    ];
		    return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}

    /**
     * 邀请加入家族
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processInvitAddAssociation($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $members = $_POST['members'];
            if(!$members){ return ReturnMessageManager::buildReturnMessage('E0157',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }

            $memberIdList = explode(',', $members);
            if(in_array($userId, $memberIdList)){
                $key = array_search($userId, $memberIdList);
                unset($memberIdList[$key]);
                $memberIdList = array_values($memberIdList);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 是否家族成员
            $userAssociation = DBManager::getAssociationMemberByUserId($userId, $association->id);
            if(!$userAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }

            // 是否开放邀请
            // if($association->open != 1){ return ReturnMessageManager::buildReturnMessage('E0174',null); }

            // 过滤已是家族成员用户
            $existAssociationMemberId = DBManager::getExistAssociationMemberId($association->id);
            $idList = array_diff($memberIdList, $existAssociationMemberId);

            // 过滤已申请、已被邀请成员用户
            $existApplyAssociationMemberId = DBManager::getExistApplyAssociationMemberId($association->id);
            $idList = array_diff($idList, $existApplyAssociationMemberId);
            // 过滤已经达到等级上限的用户
            $idList = DBManager::getReachAssociationLevelLimit($idList);

            // 邀请加入家族
            if(!empty($idList)){
                // 家族是否开启验证
                if ($association->confirm == 0) {
                    // 批量检测用户是否存在
                    $users = DBManager::checkUsers($idList);
                    if(!$users){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

                    // 检测用户是否已在群聊中
                    $clusterMember = DBManager::batchCheckClusterMember($association->id, $idList);
                    if(!$clusterMember){ return ReturnMessageManager::buildReturnMessage('E0161',null); }

                    // 获取所有用户数据
                    $memberList = DBManager::getMemberList($idList);

                    // 环信批量添加成员
                    $hxAddMembers = HxChatProxy::addGroupMember($groupId, $idList, $hxConfig);
                    if(!$hxAddMembers){ return ReturnMessageManager::buildReturnMessage('E0164',null); }

                    // 批量添加成员
                    DBManager::batchAddClusterMember($association, $memberList);

                    // 更新群组成员人数
                    DBManager::updateClusterNumber($association, count($idList));
                    return ReturnMessageManager::buildReturnMessage('E0000', array('family_id' => $association->group_id));
                }
                DBManager::batchInvitAddAssociation($idList, $userId, $association);
            }

//			// 批量添加角标
//			DBManager::batchAddBadgeByUserId($idList);
            // 发送透传消息
            $action = "haveNewFamily";
            HxChatProxy::sendSilenceMessage($idList, $action, $hxConfig);
            // 发送咖咖消息: 邀请加入家族
            // MessageSender::sendInvitJoinAssociation($di, $user, $association, $idList);

            return ReturnMessageManager::buildReturnMessage('E0000', ['successInviteCount' => count($idList)]);
        }catch ( \Exception $e ) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 退出家族
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processQuitAssociation($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 用户是否为家族成员
            $userAssociation = DBManager::checkUserAssociation($userId, $association->id);
            if(!$userAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }

            $pageIndex = (int)$_POST['pageIndex'];
            $pageIndex = $pageIndex ? $pageIndex : 1;
            // 头像
            $priList = !empty($_FILES['avatar']['tmp_name'][0]) ? $_FILES['avatar'] : '';

            // 判断为群聊且只有一人
            if ($association->type == 2 && $association->current_number <= 1) {
                // 解散环信
                $hxDissolveAssociation = HxChatProxy::delGroup($association->group_id, $hxConfig);
                if(!$hxDissolveAssociation){ return ReturnMessageManager::buildReturnMessage('E0181',null); }
                // 正常退出
            } else {
                // 是否家族会长
                if($association->type == 1){
                    if($userId == $association->owner_id){ return ReturnMessageManager::buildReturnMessage('E0180',null); }
                }
                // 判断是否为群聊群主
                if($association->type == 2){
                    if($association->owner_id == $userId){
                        // 指定新群主
                        $newAdmin = DBManager::getClusterMemberByAscSort($association->id, $userId);
                        $hxNewAdmin = HxChatProxy::updateGroupMaster($association->group_id, $newAdmin->member_id, $hxConfig);
                        if(!$hxNewAdmin){ return ReturnMessageManager::buildReturnMessage('E0165',null); }
                        DBManager::assignNewAdmin($association, $newAdmin);
                    }
                }

                // 退出环信群组
                $hxQuitGroup = HxChatProxy::quitGroup($association->group_id, $userId, $hxConfig);
                if(!$hxQuitGroup){ return ReturnMessageManager::buildReturnMessage('E0181',null); }

                // 更新群聊的头像
                if($priList) {
                    $uploadRs = OssProxy::uploadAvatar($di, $association, 2);
                }
            }

            // 删除全部申、邀请记录
            $applyAssociation = DBManager::getUserApplyAssociationRecord($userId, $association->id);
            DBManager::delApplyAssociationRecord($applyAssociation);

            // 退出家族
            DBManager::quitAssociation($userAssociation);
            DBManager::updateAssociation($association, 2);

            // 解散家族或群聊
            if($association->current_number <= 0){
                $members = DBManager::getAssociationMember($di, $association->id, $pageIndex);
                $applyAssociationRecord = DBManager::getAssociationApplyRecord($association->id);
                DBManager::dissolveAssociation($association, $members, $applyAssociationRecord);
            }

            return ReturnMessageManager::buildReturnMessage('E0000',null);
        }catch ( \Exception $e ) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 踢出家族
     *
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processKickAssociation($di, $hxConfig) {
        try {
	        $errCode = 'E0314';
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $targetId = $_POST['targetId'];
            if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0055',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $target = DBManager::getUserById($targetId);
            if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 检查是否拥有权限
	        if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_MG_FAMILYMEMBERS)) {
                return ReturnMessageManager::buildReturnMessage('E0120');
	        }

            // 用户是否为家族成员
            $userAssociationMember = DBManager::getAssociationMemberByUserId($userId, $association->id);
            if(!$userAssociationMember){ return ReturnMessageManager::buildReturnMessage('E0118',null); }
            $targetAssociationMember = DBManager::getAssociationMemberByUserId($targetId, $association->id);
            if(!$targetAssociationMember){ return ReturnMessageManager::buildReturnMessage('E0118',null); }

            // 是否管理层
            $isAdmin = DBManager::checkAssociationUserType($userId, $association->id);
            if(!$isAdmin){ return ReturnMessageManager::buildReturnMessage('E0120',null); }
            if($userId == $targetId){ return ReturnMessageManager::buildReturnMessage('E0182',null); }
            if($targetId == $association->owner_id){ return ReturnMessageManager::buildReturnMessage('E0183',null); }
            $targetAdmin = DBManager::checkAssociationUserType($targetId, $association->id);
            if($targetAdmin && ($userId != $association->owner_id)){ return ReturnMessageManager::buildReturnMessage('E0120',null); }

            // 环信退出家族
            $hxKickAssociation = HxChatProxy::quitGroup($association->group_id, $targetId, $hxConfig);
            if(!$hxKickAssociation){ return ReturnMessageManager::buildReturnMessage('E0181',null); }

            // 删除全部申、邀请记录
            $applyAssociation = DBManager::getUserApplyAssociationRecord($targetId, $association->id);
            DBManager::delApplyAssociationRecord($applyAssociation);

            // 退出家族
            DBManager::updateAssociation($association, 2);
            if (!DBManager::tickAssociation($di, $target, $association, $targetAssociationMember)) {
                return ReturnMessageManager::buildReturnMessage($errCode);
            }

	        return Utils::commitTcReturn($di, null, $errCode);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 增减管理员
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processAddDelAssociationAdmin($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $targetId = $_POST['targetId'];
            if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0055',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }
            $type = $_POST['type'];
            if(!$type || ($type != 1 && $type != 2)){ return ReturnMessageManager::buildReturnMessage('E0112',null); }

            if($userId == $targetId){ return ReturnMessageManager::buildReturnMessage('E0185',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $target = DBManager::getUserById($targetId);
            if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 用户是否为家族成员
            $userAssociation = DBManager::getAssociationMemberByUserId($userId, $association->id);
            if(!$userAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }
            $targetAssociation = DBManager::getAssociationMemberByUserId($targetId, $association->id);
            if(!$targetAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }

            // 是否是会长
            if($userId != $association->owner_id){
            	return ReturnMessageManager::buildReturnMessage('E0120',null);
            }

            if($type == 1){
                // 查询所有管理员
                $adminList = DBManager::getAllAssociationAdmin($association->id);
                if(count($adminList) >= 10){ return ReturnMessageManager::buildReturnMessage('E0126',null); }
                // 添加管理员
                if($targetAssociation->user_type == 2){ return ReturnMessageManager::buildReturnMessage('E0150',null); }
                $targetAssociation = DBManager::addDelAssociationAdmin($targetAssociation, 1);
                // 发送用户消息
                MessageSender::sendUserSetFamilyAdmin($di, $target, $association);
            }else if($type == 2){
                // 删除管理员
                if($targetAssociation->user_type == 3){ return ReturnMessageManager::buildReturnMessage('E0175',null); }
                $targetAssociation = DBManager::addDelAssociationAdmin($targetAssociation, 2);
            }

            return ReturnMessageManager::buildReturnMessage('E0000');
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }


    /**
     * 解散家族
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processDissolveAssociation($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 用户是否家族成员
            $userAssociation = DBManager::getAssociationMemberByUserId($userId, $association->id);
            if(!$userAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }

            // 是否会长
            if($userId != $association->owner_id){
            	return ReturnMessageManager::buildReturnMessage('E0120',null);
            }

	        $pageIndex = (int)$_POST['pageIndex'];
	        $pageIndex = $pageIndex ? $pageIndex : 1;

            // 获取家族所有成员
            $associationMember = DBManager::getAssociationMember($di, $association->id, $pageIndex);
            if(count($associationMember) > 1){ return ReturnMessageManager::buildReturnMessage('E0178',null); }

            // 解散家族
            $hxDissolveAssociation = HxChatProxy::delGroup($association->group_id, $hxConfig);
            if(!$hxDissolveAssociation){ return ReturnMessageManager::buildReturnMessage('E0179',null); }

            // 获取家族申请记录
            $applyAssociationRecord = DBManager::getAssociationApplyRecord($association->id);

            // 清除数据
            DBManager::dissolveAssociation($association, $associationMember, $applyAssociationRecord);

            return ReturnMessageManager::buildReturnMessage('E0000',null);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 转让家族
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processMakeOverAssociation($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $targetId = $_POST['targetId'];
            if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0055',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }

            if($userId == $targetId){ return ReturnMessageManager::buildReturnMessage('E0184', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $target = DBManager::getUserById($targetId);
            if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 家族是否存在
            $association = DBManager::getAssociationById($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 用户是否家族成员
            $userAssociation = DBManager::getAssociationMemberByUserId($userId, $association->id);
            if(!$userAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }
            $targetAssociation = DBManager::getAssociationMemberByUserId($targetId, $association->id);
            if(!$targetAssociation){ return ReturnMessageManager::buildReturnMessage('E0118',null); }

            // 是否会长
            if ($association->owner_id != $userId) {
                return ReturnMessageManager::buildReturnMessage('E0120', null);
            }

            // 环信更新会长
            $hxUpdateAssociationMaster = HxChatProxy::updateGroupMaster($groupId, $targetId, $hxConfig);
            if (!$hxUpdateAssociationMaster) {
                return ReturnMessageManager::buildReturnMessage('E0165', null);
            }

            // 更新会长
            DBManager::updateAssociationMaster($association, $userAssociation, $targetAssociation);

            return ReturnMessageManager::buildReturnMessage('E0000',null);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 更新家族信息
     *
     * @param $di
     * @return mixed
     */
    public static function processUpdateAssociationInfo($di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }
//            $nickname = isset($_POST['nickname']) ? $_POST['nickname'] : null;
            $bulletin = isset($_POST['bulletin']) ? $_POST['bulletin'] : null;
	        $familyName = isset($_POST['name']) ? $_POST['name'] : null;
	        $open = isset($_POST['open']) ? $_POST['open'] : null;
	        $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : null;
	        $info = isset($_POST['info']) ? $_POST['info'] : null;

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){
            	return ReturnMessageManager::buildReturnMessage('E0044',null);
            }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){
            	return ReturnMessageManager::buildReturnMessage('E0113',null);
            }

            // 获取家族全部成员
            // $associationMember = DBManager::getAssociationMember($di, $association->id, $pageIndex);

	        $nickname = isset($_POST['nickname']) ? $_POST['nickname'] : null;
//	        $bulletin = isset($_POST['bulletin']) ? $_POST['bulletin'] : $association->bulletin;
//	        $name = isset($_POST['name']) ? $_POST['name'] : $association->nickname;
//	        $open = isset($_POST['open']) ? $_POST['open'] : $association->open;
//	        $confirm = isset($_POST['confirm']) ? (int)trim($_POST['confirm']) : $association->confirm;

	        // 更新昵称
	        if($nickname!== null){
		        // 用户是否为家族成员
		        $userAssociation = DBManager::checkUserAssociation($userId, $association->id);
		        if($userAssociation->association_id != $association->id){
		        	return ReturnMessageManager::buildReturnMessage('E0118');
		        }
		        // 更新用户的家族昵称
		        DBManager::updateMemberNickname($userAssociation, $nickname);
		        return ReturnMessageManager::buildReturnMessage('E0000');
	        }

	        // 更新家族信息, 需要管理员权限
	        // $isAdmin = DBManager::checkAssociationUserType($userId, $association->id);

//	        if(!$isAdmin){
//	        	return ReturnMessageManager::buildReturnMessage('E0120');
//	        }

            // 更新家族名称
            if($familyName !== null){
            	// 检查权限
	            if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_UP_FAMILYNAME)) {
	                return ReturnMessageManager::buildReturnMessage('E0120');
	            }

                if($association->type == 1){
                    $oldAssociation = DBManager::getAssociationByName($familyName);
                    if($oldAssociation){ return ReturnMessageManager::buildReturnMessage('E0111'); }
                }
//                $association = DBManager::updateAssociationInfo($association, $name, null, null, null);
//                $returnData = ReturnMessageManager::buildAssociation($association);
//                return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
            } else {
	            $familyName = $association->nickname;
            }

            // 更新公告
            if($bulletin === null){
	            $bulletin = $association->bulletin;
//                $association = DBMaznager::updateAssociationInfo($association, null, $bulletin, null, null);
//                $returnData = ReturnMessageManager::buildAssociation($association);
//                return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
            } else {
	            // 检查是否拥有更新消息的权限
	            if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_UP_FAMILYBULLTIN)) {
		            return ReturnMessageManager::buildReturnMessage('E0120');
	            }
            }

            // 更新家族信息
            if ($info === null) {
            	$info = $association->info;
            } else {
            	// 检查是否拥有更新消息的权限
	            if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_UP_FAMILYINFO)) {
	                return ReturnMessageManager::buildReturnMessage('E0120');
	            }
            }

            // 公开邀请开关
            if($open === null){
	            $open = $association->open;
//                if($association->type == 2){
//                	return ReturnMessageManager::buildReturnMessage('E0120');
//                }
//                $isAdmin = DBManager::checkAssociationUserType($userId, $association->id);
//                if(!$isAdmin){ return ReturnMessageManager::buildReturnMessage('E0120',null); }
//                if($open == $association->open){ return ReturnMessageManager::buildReturnMessage('E0150', null); }
//                $association = DBManager::updateAssociationInfo($association, null, null, $open, null);
//                $returnData = ReturnMessageManager::buildAssociation($association);
//                return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
            }

            // 家族验证
            if ($confirm === null) {
	            $confirm = $association->confirm;
            } else {
            	if (!in_array($confirm, [0, 1])) {
		            $confirm = $association->confirm;
	            } else {
		            // (家族成员管理)
		            if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_CONFIRM)) {
			            return ReturnMessageManager::buildReturnMessage('E0120');
		            }
	            }
            }
//            	Utils::echo_debug('verify confirm >>>');
//                $isAdmin = DBManager::checkAssociationUserType($userId, $association->id);
//                if(!$isAdmin){ return ReturnMessageManager::buildReturnMessage('E0120',null); }
                // 修改验证状态
//                $association = DBManager::updateAssociationInfo($association, null, null, null, $confirm);
//                $returnData = ReturnMessageManager::buildAssociation($association);
//                return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
//            } else {
//	            $confirm = $association->confirm;
//            }
            // 保存
	        $association = DBManager::updateAssociationInfo($di, $association, $familyName, $bulletin, $open, $confirm, $info);

            // 构建返回数据
//	        $returnData = ReturnMessageManager::buildAssociation($association);
	        // 返回
	        return ReturnMessageManager::buildReturnMessage('E0000', ["updateFamilyInfoSuccess"=>1]);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取家族信息
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetAssociationInfo($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0159',null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0113',null); }

            // 用户是否为家族成员
            $userAssociation = DBManager::checkUserAssociation($userId, $association->id);
            if(!$userAssociation){
                $associationData = $association->toArray();
                $isApply = DBManager::getUserApplyAssociation($userId, $association->id);
                $associationData['is_apply'] = $isApply ? (string)1 : (string)2;
                return ReturnMessageManager::buildReturnMessage('E0000', $associationData);
            }

	        $pageIndex = (int)$_POST['pageIndex'];
	        $pageIndex = $pageIndex ? $pageIndex : 1;

            // 获取家族全部成员
            $associationMember = DBManager::getAssociationMember($di, $association->id, $pageIndex);

            // 处理返回数据
            $associationMemberData = ReturnMessageManager::buildAssociationMember($association, $associationMember, $userAssociation->nickname);

            return ReturnMessageManager::buildReturnMessage('E0000',$associationMemberData);
        }catch ( \Exception $e ) {
            //插入日志
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取家族/群聊成员信息
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetMemberList($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0159', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检测群组是否存在
            $association = DBManager::getClusterByGroupId($groupId);
            if(!$association){ return ReturnMessageManager::buildReturnMessage('E0160',null); }

	        $pageIndex = (int)$_POST['pageIndex'];
	        $pageIndex = $pageIndex ? $pageIndex : 1;

            // 用户是否在群组中
            $userGroup = DBManager::checkUserAssociation($userId, $association->id);
            if(!$userGroup){ return ReturnMessageManager::buildReturnMessage('E0118',null); }

            // 获取成员信息
            $members = DBManager::getAssociationMember($di, $association->id, $pageIndex);

            // 处理返回数据
            $associationMemberData = ReturnMessageManager::buildAssociationMember($association, $members, $userGroup->nickname);

            return ReturnMessageManager::buildReturnMessage('E0000',$associationMemberData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 创建群聊
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processCreateCluster($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $members = isset($_POST['members']) ? $_POST['members'] : null;

            // 群聊头像
            $priList = !empty($_FILES['assoc_avatar']['tmp_name'][0]) ? $_FILES['assoc_avatar'] : '';

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取所有成员id
            if($members){
                $allMemberIds = $userId . ',' .$members;
                $idList = explode(',', $allMemberIds);
            }else{
                $idList = array($userId);
            }
            $currentNumber = count($idList);
            if($currentNumber > 10){ return ReturnMessageManager::buildReturnMessage('E0158',null); }

            // 批量检测用户是否存在
            $users = DBManager::checkUsers($idList);
            if(!$users){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 获取所有成员信息
            $memberList = DBManager::getMemberList($idList);

            // 拼接群聊名称
            $name = DBManager::jointClusterName($memberList);

            //创建环信群组
            $groupId = HxChatProxy::createGroup($userId, $name, $idList, $hxConfig);
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0166',null); }

            // 上传家族头像
            if ($priList) {
                // 获取更新类别, 获取OSS存储空间
                $file_id = $groupId;
                $oss_buss_type = UPLOAD_BUSS_GROUP;
                $oss_bucket = OSS_BUCKET_GAVATAR;
                // OSS上传
                $uploadRS = OssProxy::ossUploadFile($di, $oss_bucket, $file_id, $oss_buss_type, 'assoc_avatar');
                // 检查是否成功
                if ($uploadRS) {
                    // 构建保存的图片资源信息
                    $assoc_avatar = $oss_bucket.';'.$uploadRS['oss-request-url'];
                    $assoc_thumb = $uploadRS['thumb'];
                } else {
                    return ReturnMessageManager::buildReturnMessage('E0084',null);
                }
            } else {
                $assoc_avatar = '';
                $assoc_thumb = '';
            }


            // 创建Redis实例
            $redis = RedisClient::create($di->get('config')['redis']);
            // 创建群聊
            $newAssociation = DBManager::createAssociation($redis, $userId, $name,  $groupId, $currentNumber, 2, 10, $assoc_avatar, $assoc_thumb);
            // 批量添加成员
	        DBManager::batchAddClusterMember($newAssociation, $memberList);
//            $associationMemberList = DBManager::batchAddClusterMember($newAssociation, $memberList);
            // 处理返回数据
//            $returnData = ReturnMessageManager::buildClusterMemberList($newAssociation, $associationMemberList);
			$returnData = ['groupId' => $groupId];
            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 群聊添加成员
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return mixed
     */
    public static function processAddClusterMember($app, $di, $hxConfig) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0159', null); }
            $members = $_POST['members'];
            if(!$members){ return ReturnMessageManager::buildReturnMessage('E0157', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检测群组是否存在
            $cluster = DBManager::getAssociationByGroupId($groupId);
            if(!$cluster){ return ReturnMessageManager::buildReturnMessage('E0160',null); }

            // 检查是否拥有群组操作的权限
            $clusterMember = DBManager::checkGroupChatMemberById($userId, $cluster->id);
	        if (!$clusterMember) {
            	return ReturnMessageManager::buildReturnMessage('E0120');
	        }

            // 获取所有成员id
            $idList = explode(',', $members);

            // 过滤已存在用户
            $existedMemberIdList = DBManager::getExistedMemberIdList($cluster->id);
            $idList = array_diff($idList, $existedMemberIdList);
            $count = count($idList) + intval($cluster->current_number);
            if($count > 10){ return ReturnMessageManager::buildReturnMessage('E0158',null); }

            if(!empty($idList)){
                // 批量检测用户是否存在
                $users = DBManager::checkUsers($idList);
                if(!$users){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

                // 检测用户是否已在群聊中
                $clusterMember = DBManager::batchCheckClusterMember($cluster->id, $idList);
                if(!$clusterMember){ return ReturnMessageManager::buildReturnMessage('E0161',null); }

                // 获取所有用户数据
                $memberList = DBManager::getMemberList($idList);

                // 环信批量添加成员
                $hxAddMembers = HxChatProxy::addGroupMember($groupId, $idList, $hxConfig);
                if(!$hxAddMembers){ return ReturnMessageManager::buildReturnMessage('E0164',null); }

                // 批量添加成员
                $clusterMemberList = DBManager::batchAddClusterMember($cluster, $memberList);

                // 更新群组成员人数
                DBManager::updateClusterNumber($cluster, count($idList));
            }

            $membersInfo = DBManager::getClusterMembers($cluster->id);

            // 处理返回数据
            $returnData = ReturnMessageManager::buildClusterMemberList($cluster, $membersInfo);

            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取家族/群组详情
     *
     * @param $di
     * @return mixed
     */
    public static function processGetFamilyDetails($di)
    {
        try {
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $groupId = $_POST['groupId'];
            if (!$groupId) {
                return ReturnMessageManager::buildReturnMessage('E0112', null);
            }

            // 页码
            $pageIndex = isset($_POST['pageIndex']) ? (int)$_POST['pageIndex'] : 1;

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            // 检查家族/群组是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if (!$association) {
                return ReturnMessageManager::buildReturnMessage('E0113', null);
            }

            // 用户是否为家族/群组成员
            $userAssociation = DBManager::checkUserAssociation($userId, $association->id);
//            if (!$userAssociation) {
//                $associationMemberData = ReturnMessageManager::buildFamilyDetailInfo($userId, $association);
//                return ReturnMessageManager::buildReturnMessage('E0000', $associationMemberData);
//            }

            // 获取家族/群组全部成员
            $associationMember = DBManager::getAssociationMember($di, $association->id, $pageIndex);

            // 处理返回数据
            $associationMemberData = ReturnMessageManager::buildFamilyDetailInfo($userId, $association, $userAssociation, $associationMember);
            return ReturnMessageManager::buildReturnMessage('E0000', $associationMemberData);
        } catch (\Exception $e) {
            //插入日志
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 创建家族
     *
     * @param $app
     * @param $di
     * @param $hxConfig
     * @return string
     */
    public static function processCreateFamily($app, $di, $hxConfig)
    {
        try {
            $userId = $_POST['userId'];
            if (!$userId) {return ReturnMessageManager::buildReturnMessage('E0013', null);}
            // 家族昵称
            $name = $_POST['name'];
            if (!$name) {return ReturnMessageManager::buildReturnMessage('E0238', null);}
            // 家族标签
	        $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
            // 家族公告
            $bulletin = isset($_POST['bulletin'])? $_POST['bulletin'] : '';
            // 家族简介
	        $info = isset($_POST['info'])? $_POST['info'] : '';

            // 加入家族是否需要验证
            $confirm = isset($_POST['confirm'])? (int)trim($_POST['confirm']) : 1;
            if (!in_array($confirm, [0,1])) {return ReturnMessageManager::buildReturnMessage('E0258', null);}

            // 家族头像
            $priList = !empty($_FILES['assoc_avatar']['tmp_name'][0]) ? $_FILES['assoc_avatar'] : '';

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {return ReturnMessageManager::buildReturnMessage('E0044', null);}
            // 检查用户昵称是否存在
            if (!$user->nickname) {return ReturnMessageManager::buildReturnMessage('E0011', null);}

            // 检查家族名称是否存在
            $association = DBManager::getAssociationByName($name);
            if ($association) {return ReturnMessageManager::buildReturnMessage('E0111', null);}

            // 检查用户等级限制
            $checkResult = DBManager::checkUserLevelLimit($userId, $user->level, 2);
            if(!$checkResult) {
                return ReturnMessageManager::buildReturnMessage('E0302',null);
            }

            // 环信创建群组
            $groupId = HxChatProxy::createGroup($userId, $name, null, $hxConfig);
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0166',null); }
            // 上传家族头像
            if ($priList) {
                // 获取更新类别, 获取OSS存储空间
                $file_id = $groupId;
                $oss_buss_type = UPLOAD_BUSS_GROUP;
                $oss_bucket = OSS_BUCKET_GAVATAR;
                // OSS上传
                $uploadRS = OssProxy::ossUploadFile($di, $oss_bucket, $file_id, $oss_buss_type, 'assoc_avatar');
                // 检查是否成功
                if ($uploadRS) {
                    // 构建保存的图片资源信息
                    $assoc_avatar = $oss_bucket.';'.$uploadRS['oss-request-url'];
                    $assoc_thumb = $uploadRS['thumb'];
                } else {
                    return ReturnMessageManager::buildReturnMessage('E0084',null);
                }
            } else {
                $assoc_avatar = '';
                $assoc_thumb = '';
            }
            $redis = RedisClient::create($di->get('config')['redis']);

            // 创建家族
            $newAssociation = DBManager::createAssociation($redis, $userId, $name, $groupId, 1, 1, 200, $assoc_avatar, $assoc_thumb, $info, $confirm);
            // 更新家族的标签
            DBManager::updateTags($groupId, $tags, 2);
	        // 添加用户
            DBManager::addAssociationMember($userId, $user->nickname, $newAssociation->id, 1);
            // 关闭Redis连接
            $redis->close();
            // 返回
            return ReturnMessageManager::buildReturnMessage('E0000', ['familyId' => $groupId]);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 邀请加入群聊
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processInviteGroupChat($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013',null); }
            $members = $_POST['members'];
            if(!$members){ return ReturnMessageManager::buildReturnMessage('E0157',null); }
            $groupId = $_POST['groupId'];
            if(!$groupId){ return ReturnMessageManager::buildReturnMessage('E0112',null); }

            $memberIdList = explode(',', $members);
            if(in_array($userId, $memberIdList)){
                $key = array_search($userId, $memberIdList);
                unset($memberIdList[$key]);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            // 检查用户的昵称是否为空
            if(!$user->nickname) { return ReturnMessageManager::buildReturnMessage('E0011', null); }

            // 检查群聊是否存在
            $groupChat = DBManager::getAssociationByGroupId($groupId);
            if(!$groupChat){ return ReturnMessageManager::buildReturnMessage('E0239',null); }

	        $pageIndex = (int)$_POST['pageIndex'];
	        $pageIndex = $pageIndex ? $pageIndex : 1;

            // 是否群聊成员
            $userGroupChat = DBManager::checkGroupChatMemberById($userId, $groupChat->id);
            if(!$userGroupChat){ return ReturnMessageManager::buildReturnMessage('E0240',null); }


            // 过滤已是群聊成员用户
            $existGroupChatMemberId = DBManager::getExistAssociationMemberId($groupChat->id);
            $idList = array_diff($memberIdList, $existGroupChatMemberId);

            // 加入群聊
            if(!empty($idList)){
                DBManager::InviteGroupChat($idList, $groupChat);
            }
            // 获取家群聊全部成员
            $associationMember = DBManager::getAssociationMember($di, $groupChat->id, $pageIndex);

            // 处理返回数据
            $associationMemberData = ReturnMessageManager::buildFamilyDetailInfo($userId, $groupChat, $associationMember);
            return ReturnMessageManager::buildReturnMessage('E0000', $associationMemberData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 更新家族发言模式
     *
     * @param $di
     * @return mixed
     */
    public static function processUpdateFamilySpeakMode($di) {
        try {
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $groupId = $_POST['familyId'];
            if (!$groupId) {
                return ReturnMessageManager::buildReturnMessage('E0112', null);
            }
            // 发言模式字段不能为空
            $speakMode = (int)$_POST['speakMode'];
		    if ($speakMode === null || !in_array($speakMode, [0, 1, 2])) {
			    return ReturnMessageManager::buildReturnMessage('E0327', null);
		    }

            // 检查家族是否存在
            $association = DBManager::getAssociationByGroupId($groupId);
            if (!$association) {
                return ReturnMessageManager::buildReturnMessage('E0113', null);
            }

            // 验证家族权限
            if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_UP_FAMILYSHUTUP)) {
                return ReturnMessageManager::buildReturnMessage('E0120', null);
            }
            $association->speak_mode = $speakMode;
            if($speakMode == 2) {
                $timeInterval = isset($_POST['timeInterval']) ? $_POST['timeInterval'] : 0;
                $association->speak_time_interval = $timeInterval;
            }
            // 保存数据
            if (!$association->save()) {
                return ReturnMessageManager::buildReturnMessage('E0257', null);
            }
            return ReturnMessageManager::buildReturnMessage('E0000', ['speakMode' => $speakMode]);
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }


    /**
     * 对家族成员禁言
     *
     * @param $di
     * @return mixed
     */
    public static function processFamilyShutup($di)
    {
    	try {
		    $userId = $_POST['userId'];
		    if (!$userId) {
			    return ReturnMessageManager::buildReturnMessage('E0013', null);
		    }
		    $groupId = $_POST['familyId'];
		    if (!$groupId) {
			    return ReturnMessageManager::buildReturnMessage('E0112', null);
		    }

		    // 检查家族是否存在
		    $association = DBManager::getAssociationByGroupId($groupId);
		    if (!$association) {
			    return ReturnMessageManager::buildReturnMessage('E0113', null);
		    }

		    // 验证家族权限
		    if (!Utils::verifyFamilyOpPerm($userId, $association->id, FMPERM_UP_FAMILYSHUTUP)) {
			    return ReturnMessageManager::buildReturnMessage('E0120', null);
		    }

		    // 检查是否存在目标禁言对象
		    $targetId = trim($_POST['targetId']);
		    if ($targetId) {
			    // 检查是否是家族成员
			    $targetFamilyMember = DBManager::getAssociationMemberByUserId($targetId, $association->id);
			    if (!$targetFamilyMember) {
			    	return ReturnMessageManager::buildReturnMessage('E0266');
			    }
			    if ($targetFamilyMember->shut_up == 1) {
				    $shutUp = 0;
			    } else {
				    $shutUp = 1;
			    }
			    $targetFamilyMember->shut_up = $shutUp;
			    // 更新
			    if (!$targetFamilyMember->save()) {
				    return ReturnMessageManager::buildReturnMessage('E0257');
			    }
		    }
		    return ReturnMessageManager::buildReturnMessage('E0000', ['shut_up' => $shutUp]);
	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
    }

    /**
     * 获取推荐家族
     *
     * @param $di
     * @return mixed
     */
    public static function processGetRecommandFamilies($di)
    {
		try {
            $redis = RedisClient::create($di->get('config')['redis']);
            $key = RedisClient::assoicLevRankKey();
            $data = array();
            if ($key) {
                $result = $redis->zRevRange($key, 0, 99, true);
                $result = Utils::sortByKeyAndSameValue($result);
                if ($result) {
                    $randNum = count($result) > 10 ? 10 : count($result);
                    $randResult = array_rand($result, $randNum);
                    $idx = 0;
                    $rankInfo = array();
                    if(is_array($randResult)) {
                        foreach ($result as $rankId => $value) {
                            foreach ($randResult as $index => $res) {
                                if($rankId == $res) {
                                    $rankInfo[$rankId] = $value;
                                }
                            }
                        }
                    } else {
                        foreach ($result as $rankId => $value) {
                            if($rankId == $randResult) {
                                $rankInfo[$rankId] = $value;
                            }
                        }
                    }
                    foreach ($rankInfo as $rankId => $value) {
                        $association = DBManager::getAssociationByGroupId($rankId);
                        if ($association->type == 1) {
                            $data[$idx]['family_id'] = $rankId;
                            $data[$idx]['family_name'] = $association->nickname;
                            $data[$idx]['familyAvatar'] = OssProxy::procOssPic($association->assoc_avatar);
                            $data[$idx]['familyLevel'] = $association->level;
                            $data[$idx]['currentNumber'] = $association->current_number;
                            //获取可领取金额
                            $amount = DBManager::getFamilyCanGrabAmount($di, $rankId);
                            $data[$idx]['amount'] = $amount;
                            $idx ++;
                        }
                    }
                }
            }
			return ReturnMessageManager::buildReturnMessage('E0000', ['recommendFamilies' => $data]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
    }

    /**
     * 更新家族成员操作权限
     *
     * @param $di
     * @return mixed
     */
	public static function processUpdateFamilyMemberPerm($di)
	{
		try {
			$userId = $_POST['userId'];
			if (!$userId) {
				return ReturnMessageManager::buildReturnMessage('E0013', null);
			}
			$groupId = $_POST['groupId'];
			if (!$groupId) {
				return ReturnMessageManager::buildReturnMessage('E0112', null);
			}

			// 检查家族是否存在
			$association = DBManager::getAssociationByGroupId($groupId);
			if (!$association) {
				return ReturnMessageManager::buildReturnMessage('E0113', null);
			}
			// 检查是否是拥有者
			if ($association->owner_id != $userId) {
				return ReturnMessageManager::buildReturnMessage('E0120', null);
			}
			// 检查是否存在目标禁言对象
			$targetId = trim($_POST['targetId']);
			if (!$targetId) {
				return ReturnMessageManager::buildReturnMessage('E0055');
			}
			$target = DBManager::getUserById($targetId);
			if(!$target) {return ReturnMessageManager::buildReturnMessage('E0044');}

			// 如果目标ID是家族所有者, 则不做任何操作
			if ($targetId == $userId) {
				return ReturnMessageManager::buildReturnMessage('E0000');
			}
			// 检查是否是家族成员
			$targetFamilyMember = DBManager::getAssociationMemberByUserId($targetId, $association->id);
			// 操作权限
			$permStr = trim($_POST['permission']);
			// 检查家族权限值
			if (!Utils::verifyFamilyMemPerm($permStr)) {
				return ReturnMessageManager::buildReturnMessage('E0307');
			}
			if (!$targetFamilyMember) {
				return ReturnMessageManager::buildReturnMessage('E0266');
			}
			// 检查用户当前的身份
            $transaction = $di->getShared(SERVICE_TRANSACTION);
            $targetFamilyMember->setTransaction($transaction);
			$targetFamilyMember->perm = $permStr;
			if ($targetFamilyMember->user_type == 3 && $permStr != "00000000") {
				$targetFamilyMember->user_type = 2;
                // 发送用户消息
                MessageSender::sendUserSetFamilyAdmin($di, $target, $association);
			} else if ($targetFamilyMember->user_type == 2 && $permStr == "00000000") {
				$targetFamilyMember->user_type = 3;
			}
			// 保存数据
			if (!$targetFamilyMember->save()) {
				return ReturnMessageManager::buildReturnMessage('E0306');
			}
			// 返回结果
            return Utils::commitTcReturn($di, null, 'E0000');
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}


    /*
     *  TODO 朋友圈相关
     */

    /**
     * 发表说说
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processPublishMoments($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $content = isset($_POST['content']) ? $_POST['content'] : '';

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044'); }

            // 验证visible
            $visible = $_POST['visible'];

            if (!$visible || !Utils::verifyMomentVisible($visible)) {
            	return ReturnMessageManager::buildReturnMessage('E0210');
            }
            // 上传图片
	        if (!$_FILES['pri_url']) {
                return ReturnMessageManager::buildReturnMessage('E0017');
	        }
	        $uploadRs = OssProxy::uploadMomentsCover($di, $userId);;
            if ($uploadRs['error']) {
	            return ReturnMessageManager::buildReturnMessage($uploadRs['error']);
            }
	        // 保存朋友圈
	        DBManager::saveMoments($userId, $content, $uploadRs, $visible, 2);

	        // 返回结果
            return ReturnMessageManager::buildReturnMessage('E0000', null);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 说说评论
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processMomentsReply($app, $di) {
        try {
            $errCode = 'E0313';
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $momentsId = $_POST['momentsId'];
            if(!$momentsId){ return ReturnMessageManager::buildReturnMessage('E0132', null); }
            $content = $_POST['content'];
            if(!$content){ return ReturnMessageManager::buildReturnMessage('E0209', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查是否有这条说说
            $moments = DBManager::getMomentsByMomentsId($momentsId);
            if(!$moments){ return ReturnMessageManager::buildReturnMessage('E0135',null); }

            $replyUser = DBManager::getUserById($moments->user_id);
            //检查被回复的评论是否存在
            $parentId = isset($_POST['parentId']) ? $_POST['parentId'] : 0;
            if($parentId && $parentId != 0) {
                $parentReply = DBManager::getMomentsReplyById($parentId);
                if($parentReply) {
                    $replyUser = DBManager::getUserById($parentReply->user_id);
                } else {
                    return ReturnMessageManager::buildReturnMessage('E0322', null);
                }
            }

            // 评论
            $momentsReply = DBManager::momentsReply($userId, $momentsId, $content, $parentId);

            // 增加热度5
            SystemHot::addHotNum($momentsId, 3, 5);
            // 发送评论说说用户消息
            if($userId != $moments->user_id) {
                MessageSender::sendUserMomentsReply($di, $user, $replyUser, $momentsId, $parentId);
            }
            // 处理返回数据
            $returnData = ReturnMessageManager::buildMomentsReply($user, $momentsReply);
            // 提交事务
            if (!Utils::commitTc($di)) {
                return ReturnMessageManager::buildReturnMessage($errCode);
            }
            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 删除说说评论
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processDelMomentsReply($app, $di) {
        try {
            $errCode = 'E0313';
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }

            $replyId = $_POST['replyId'];
            if(!$replyId){ return ReturnMessageManager::buildReturnMessage('E0321', null); }
            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查该评论是否存在
            $momentsReply = DBManager::getMomentsReplyByReplyIdAndUserId($replyId, $userId);
            if(!$momentsReply) {return ReturnMessageManager::buildReturnMessage('E0322',null);}

            $delResult = DBManager::delMomentsReply($momentsReply);
            if($delResult) {
                $isDelSuccess = true;
            } else {
                $isDelSuccess = false;
            }
            // 处理返回数据
            $returnData = array("isDelSuccess" => $isDelSuccess);
            // 提交事务
            if (!Utils::commitTc($di)) {
                return ReturnMessageManager::buildReturnMessage($errCode);
            }
            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 给说说的评论点赞
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processMomentsReplyLike($app, $di) {
        try {
            $errCode = 'E0315';
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $replyId = $_POST['replyId'];
            if(!$replyId){ return ReturnMessageManager::buildReturnMessage('E0321', null); }
            // 检查是否有这条评论
            $momentsReply = DBManager::getMomentsReplyById($replyId);
            if(!$momentsReply) {return ReturnMessageManager::buildReturnMessage('E0322', null);}
            $replyUser = $momentsReply->user;

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检测是否点赞
            $momentsReplyLike = DBManager::getMomentsReplyLike($userId, $replyId);
            $cancelLikeResult = false;
            $isLike = 1; //1 点赞   2取消点赞
            if($momentsReplyLike){
                $like = false;
                $cancelLikeResult = DBManager::delMomentsReplyLike($momentsReplyLike);
                if($cancelLikeResult){
                    $isLike = 2;
                }
                //更新说说评论的点赞数量
                $momentsReply->like_count -= 1;
                DBManager::updateMomentsReplyLikeCount($momentsReply);
            }else{
                $like = true;
                $momentsReplyLike = DBManager::momentsReplyLike($userId, $replyId);
                //更新说说评论的点赞数量
                $momentsReply->like_count += 1;
                DBManager::updateMomentsReplyLikeCount($momentsReply);
                // 添加点赞用户消息
                if ($userId != $momentsReply->user_id) {
                    MessageSender::sendUserMomentsReplyLike($di, $replyUser, $user, $replyId);
                }
            }

            // 处理返回数据
            if($like) {
                $returnData = array("replyLikeId" => $momentsReplyLike->id, "isLike" => $isLike);
            } else {
                $returnData = array("cancelLikeResult" => $cancelLikeResult, "isLike" => $isLike);
            }

            // 提交事务
            if (!Utils::commitTc($di)) {
                return ReturnMessageManager::buildReturnMessage($errCode);
            }
            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 给说说点赞
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processMomentsLike($app, $di) {
        try {
	        $errCode = 'E0315';
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $momentsId = $_POST['momentsId'];
            if(!$momentsId){ return ReturnMessageManager::buildReturnMessage('E0132', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查是否有这条说说
            $moments = DBManager::getMomentsByMomentsId($momentsId);
            if(!$moments){ return ReturnMessageManager::buildReturnMessage('E0135',null); }
			$momentUser = $moments->user;
            // 检测是否点赞
            $momentsLike = DBManager::getMomentsLike($userId, $momentsId);
            $isDelSuccess = false;
            if($momentsLike){
                $isDelSuccess = DBManager::delMomentsLike($momentsLike);
                $like = 2;
            }else{
                $momentsLike = DBManager::momentsLike($userId, $momentsId);
                $like = 1;
                // 增加热度 +2
                SystemHot::addHotNum($momentsId, 3, 2);
                // 添加点赞用户消息
                if ($userId != $moments->user_id) {
                    MessageSender::sendUserMomentsLike($di, $momentUser, $user, $momentsId);
                }
            }
            // 获取点赞数量
            $likeCount = DBManager::getMomentsLikeCount($momentsId);
            // 处理返回数据
	        if($like == 1) {
                $returnData = array("momentsLikeId" => $momentsLike->id, "isLike" => $like, "likeCount" => $likeCount);
            } else {
                $returnData = array("isDelSuccess" => $isDelSuccess, "isLike" => $like, "likeCount" => $likeCount);
            }
	        // 提交事务
	        if (!Utils::commitTc($di)) {
		        return ReturnMessageManager::buildReturnMessage($errCode);
	        }
            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 分页获取说说的点赞列表
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetMomentsLikeByPage($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $momentsId = $_POST['momentsId'];
            if(!$momentsId){ return ReturnMessageManager::buildReturnMessage('E0132', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查是否有这条说说
            $moments = DBManager::getMomentsByMomentsId($momentsId);
            if(!$moments){ return ReturnMessageManager::buildReturnMessage('E0135',null); }

            //检查该点赞记录是否存在
            $likeId = $_POST['likeId'];
            if($likeId != 0) {
                $momentsLike = DBManager::getMomentsLikeById($likeId);
                if(!$momentsLike) {return ReturnMessageManager::buildReturnMessage('E0320',null);}
            }

            // 分页查询点赞信息
            $momentsLikeList = DBManager::getMomentsLikeByPage($app, $momentsId, $likeId);

            // 处理返回数据
            $returnData = ReturnMessageManager::buildMomentsLike($momentsLikeList);

            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取说说详情
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetUserMoments($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $momentsId = $_POST['momentsId'];
            if(!$momentsId){ return ReturnMessageManager::buildReturnMessage('E0132', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查是否有这条说说
            $moments = DBManager::getMomentsByMomentsId($momentsId);
            if(!$moments){ return ReturnMessageManager::buildReturnMessage('E0135',null); }

            // 检测用户是否点赞
            $isLike = DBManager::getMomentsLike($userId, $momentsId);
            if($isLike){
                $like = 1; //已点赞
            }else{
                $like = 2; //未点赞
            }

            // 查询说说信息
            $momentsAllReply = DBManager::getMomentsAllReply($momentsId);
            $momentsReplyTopThree = DBManager::getMomentsTopThree($app, $momentsId);
            $momentsReply = DBManager::getMomentsReplyByPageIncludeHot($app, $momentsId);
            $momentsAllLike = DBManager::getMomentsAllLike($momentsId);
            $momentsLike = DBManager::getMomentsLikeByPage($app, $momentsId);
            $momentsGive = DBManager::getMomentsAllGive($momentsId);

            // 处理返回数据
            $returnData = ReturnMessageManager::buildUserMoments($user, $moments, $momentsReply, $momentsReplyTopThree, $momentsAllReply, $momentsLike, $momentsAllLike, $momentsGive, $like);

            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 分页获取说说的评论列表
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetMomentsReplyByPage($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $momentsId = $_POST['momentsId'];
            if(!$momentsId){ return ReturnMessageManager::buildReturnMessage('E0132', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 检查是否有这条说说
            $moments = DBManager::getMomentsByMomentsId($momentsId);
            if(!$moments){ return ReturnMessageManager::buildReturnMessage('E0135',null); }

            //当前最后一条评论的索引
            $lastReplyId = $_POST['lastReplyId'];

            $momentsReply = DBManager::getMomentsReplyByPageIncludeHot($app, $momentsId, $lastReplyId);

            // 处理返回数据
            $returnData = ReturnMessageManager::buildMomentsReplyByPage($user, $momentsReply);

            return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
        }catch ( \Exception $e ) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取用户的说说列表（已报废）
     *
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processGetUserAllMoments($app, $di) {
        try {
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $targetId = $_POST['targetId'];
            if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0055', null); }
            $index = isset($_POST['index']) ? $_POST['index'] : null;

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            // 判断是否有页码
            if (!$index) {
                $momentsList = DBManager::getUserMomentsList($targetId);
            } else {
                // 获取用户全部说说
                $momentsLists = DBManager::getUserMomentsList($targetId, $index);
                // 使用分页类
                $paginator = new PaginatorArray(array(
                    'data' => $momentsLists->toArray(),
                    'limit' => 10,
                    'page' => $index
                ));
                $momentsList = $paginator->getPaginate()->items;
                $momentsList = json_decode(json_encode($momentsList));
            }
            // 获取说说的回复、点赞、打赏状态
            $momentsAllReply = DBManager::getAllReply($momentsList);
            $momentsAllLike = DBManager::getAllLike($momentsList);
            $momentsAllGive = DBManager::getAllGive($momentsList);
            $nickName = DBManager::getAllMomentsUser($momentsList);
            // 获取用户信息
            $targetInfo = DBManager::getUserById($targetId);
            // 获取用户粉丝数量和关注数量
            $fansAndFollows = DBManager::getUserFansAndFollowNum($targetId);
            // 获取说说总数,礼物总数
            $momentsNum = DBManager::getUserMomentsNum($targetId);
            $giftNum = DBManager::getUserGiftNum($targetId);
            $userData = ReturnMessageManager::buildUserInfo($targetInfo, null, null, $fansAndFollows, $momentsNum, $giftNum);
            // 处理返回的数据
            $returnData = ReturnMessageManager::buildAllMomentsData($userId, $momentsList, $momentsAllReply, $momentsAllLike, $momentsAllGive, $nickName, false);
            if(!$returnData){ return ReturnMessageManager::buildReturnMessage('E0000',array('userInfo' => $userData, 'circleOfFriend' => [])); }

            return ReturnMessageManager::buildReturnMessage('E0000', array('userInfo' => $userData, 'circleOfFriend' => $returnData));
        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 设置看/不看某用户的说说
     *
     * @param $di
     * @return mixed
     */
	public static function processSetLookUserMoments($di) {
		try {
			$userId = trim($_POST['userId']);
			if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }

			$user = DBManager::getUserById($userId);
			if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

			$targetId = trim($_POST['targetId']);
			if(!$targetId){
				return ReturnMessageManager::buildReturnMessage('E0055');
			}

			$target = DBManager::getUserById($targetId);
			if(!$target){
				return ReturnMessageManager::buildReturnMessage('E0044',null);
			}

			// 检查是否存在关系
			$userRelationPerm = UserRelationPerm::findFirst("user_id=".$userId." AND target_id = ".$targetId);
			if ($userRelationPerm) {
				if ($userRelationPerm->is_look == 1) {
					$lookStatus = 0;
				} else {
					$lookStatus = 1;
				}
				$userRelationPerm->is_look = $lookStatus;
			} else {
				$lookStatus = 1;
				$userRelationPerm = new UserRelationPerm();
				$userRelationPerm->user_id = $userId;
				$userRelationPerm->target_id = $targetId;
				$userRelationPerm->is_look = 1;
			}
			// 存储
			if (!$userRelationPerm->save()){
				Utils::throwDbException($userRelationPerm);
			}
			return ReturnMessageManager::buildReturnMessage('E0000', [
					'is_look' => $lookStatus
			]);
		}catch ( \Exception $e ) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 删除说说
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processDelMoment($app, $di)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
            $momentId = $_POST['momentId'];
            if(!$momentId){ return ReturnMessageManager::buildReturnMessage('E0132', null); }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

            // 查找说说是否存在
            $momentInfo = DBManager::getMomentById($userId, $momentId);
            if (!$momentInfo) {
                return ReturnMessageManager::buildReturnMessage('E0135',null);
            }
            // 删除说说
            DBManager::delMomentById($userId, $momentId);
            return ReturnMessageManager::buildReturnMessage('E0000', null);

        }catch ( \Exception $e ) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取用户的全部说说
     *
     * @param $di
     * @return mixed
     */
	public static function processGetUserProduction($di) {
		try {
			$userId = $_POST['userId'];
			if(!$userId){ return ReturnMessageManager::buildReturnMessage('E0013', null); }
			$targetId = $_POST['targetId'];
			if(!$targetId){ return ReturnMessageManager::buildReturnMessage('E0055', null); }
			$pageIndex = (int)$_POST['pageIndex'];
			$pageIndex = $pageIndex ? $pageIndex : 1;

			// 检查用户是否存在
			$user = DBManager::getUserById($userId);
			if(!$user){ return ReturnMessageManager::buildReturnMessage('E0044',null); }
            $target = DBManager::getUserById($targetId);
            if(!$target){ return ReturnMessageManager::buildReturnMessage('E0044',null); }

			// 检查是否可以查看目标用户的全部说说
			$isFriend = DBManager::isFriend($userId, $targetId);
            $isSeeAll = $isFriend || $userId == $targetId ? true : false;

			// 获取目标用户的说说
			$momentsList = DBManager::getUserMomentsList($targetId, $pageIndex, $isSeeAll);
			// 获取说说对应的红包ID
			$redpackIds = '';
			foreach ($momentsList as $moment) {
				$redpacketId = $moment->red_packet_id;
				if ($redpacketId) {
					if ($redpackIds == '') {
						$redpackIds = $redpacketId;
					} else {
						$redpackIds .= ', '.$redpacketId;
					}
				}
			}
			// 获取用户抢红包记录
			$userGrabRedpackRecords = DBManager::getUserRedpacketRecord($userId, $redpackIds);
			// 返回数据
			$data = ['momentList' => ReturnMessageManager::buildUserProduction($userGrabRedpackRecords, $momentsList)];
			// 处理返回的数据
			return ReturnMessageManager::buildReturnMessage('E0000', $data);
		}catch ( \Exception $e ) {
			return Utils::processExceptionError($di, $e);
		}
	}


    /**
     * TODO 支付相关
     */

    /**
     * 兑换咖米
     * @param $app
     * @param $di
     * @return mixed
     */
    public static function processExchangeKaMi($app, $di) {
        try {
            // 验证参数
            $uid = trim($_POST['userId']);
            if (!$uid) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $diamond = $_POST['diamond'];
            if (!$diamond) {
                return ReturnMessageManager::buildReturnMessage('E0153', null);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($uid);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            if($diamond > $user->diamond) {
                return ReturnMessageManager::buildReturnMessage('E0156', null);
            }

            $result = DBManager::exchangeKaMi($di, $user, $diamond);
            if ($result) {
                $code = Utils::generateOrderId($uid);
                $record = DBManager::saveExchangeKaMiRecord($di, $uid, $diamond, $code);
                if($record) {
                    KakaPay::createBalanceRecord($user->id, PAYOP_TYPE_RECHARGE, $diamond, 0, $record->id);
                    MessageSender::sendExchangeKaMiSuccess($di, $user, $diamond, PAY_BY_APPLE, $record);
                }
                return Utils::commitTcReturn($di, null, "E0000");
            } else {
                return Utils::commitTcReturn($di, null, "E0186");
            }
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }


    /**
     * 苹果支付创建订单
     * @param $app
     * @param $di
     * @return mixed
     */
     public static function processApplePayGenerateOrder($app, $di) {
        try {
            // 验证参数
            $uid = trim($_POST['userId']);
            if (!$uid) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $amount = trim($_POST['amount']);
            if (!$amount) {
                return ReturnMessageManager::buildReturnMessage('E0214', null);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($uid);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            // 创建本地订单
            $orderId = Utils::generateOrderId($uid);
            $now = time();
            // 构建要保存的数据
            $orderData = [
                'user_id' => $uid,
                'order_num' => $orderId,
                'amount' => $amount,
                'balance' => $user->balance,
                'status' => 0,
                'consum_type' => PAYOP_TYPE_RECHARGE,
                'create_date' => $now,
                'pay_channel' => PAY_CHANNEL_APPLE,
                'pay_account' => '',
                'remark' => ''
            ];

            $orderInfo = KakaPay::createUserOrder($orderData);
            if (!$orderInfo) {
                return ReturnMessageManager::buildReturnMessage('E0215', null);
            }
            return ReturnMessageManager::buildReturnMessage('E0000', ['orderInfo'=>$orderInfo]);
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
     }


    /**
     * 苹果支付回调
     * @param $di
     * @return mixed
     */
     public static function processApplePayNotify($di) {
         try {
             // 验证参数
             $uid = trim($_POST['userId']);
             if (!$uid) {
                 return ReturnMessageManager::buildReturnMessage('E0013', null);
             }
             $orderNum = trim($_POST['orderNum']);
             if (!$orderNum) {
                 return ReturnMessageManager::buildReturnMessage('E0214', null);
             }
             // 检查用户是否存在
             $user = DBManager::getUserById($uid);
             if (!$user) {
                 return ReturnMessageManager::buildReturnMessage('E0044', null);
             }
             $order = DBManager::getOrderByOrderId($orderNum);
             if(!$order) {
                 return ReturnMessageManager::buildReturnMessage('E0216', null);
             }

             if($order->status != 1) {
                 if(!DBManager::updateOrderStatus($di, $order)) {
                     return ReturnMessageManager::buildReturnMessage('E0218', null);
                 }
                 if(!DBManager::updateDiamond($di, $user, $order->amount)) {
                     return ReturnMessageManager::buildReturnMessage('E0187', null);
                 }
                 return Utils::commitTcReturn($di, ['isSuccess'=>1], 'E0000');
             } else {
                 return ReturnMessageManager::buildReturnMessage('E0000', ['isSuccess'=>1]);
             }
         } catch (\Exception $e) {
             return Utils::processExceptionError($di, $e);
         }
     }
    /**
     * 微信支付创建订单
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processWxPayGenerateOrder($app, $di, $type = 0)
    {
        try {
            // 验证参数
	        $uid = trim($_POST['userId']);
            if (!$uid) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $amount = trim($_POST['amount']);
            if (!$amount) {
                return ReturnMessageManager::buildReturnMessage('E0214', null);
            }
            $ip = trim($_POST['ipAddress']);
            if (!$amount) {
                return ReturnMessageManager::buildReturnMessage('E0040', null);
            }


            // 检查用户是否存在
            $user = DBManager::getUserById($uid);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }
            /**
             * TODO 方便测试暂时注释掉
             */
//            if ($amount < 1) {
//                return ReturnMessageManager::buildReturnMessage('E0245', null);
//            }

            // 创建本地订单
            $orderId = Utils::generateOrderId($uid);
            $now = time();
	        // 构建要保存的数据
	        $orderData = [
		        'user_id' => $uid,
		        'order_num' => $orderId,
		        'amount' => $amount,
		        'balance' => $user->balance,
		        'status' => 0,
		        'consum_type' => 1,
		        'create_date' => $now,
		        'pay_channel' => 2,
		        'pay_account' => '',
		        'remark' => ''
	        ];
            $orderInfo = KakaPay::createUserOrder($orderData);
            // 微信生成订单
            $payUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder'; //接口url地址
            $orderId=$orderInfo['order_id'];

            // 微信下单
            $amount = $amount * 100;

            $data=WxPayProxy::buildXML($orderId, $ip, $amount,null, $type);
            $result = Utils::curl_post($payUrl,$data);
            $array=Utils::xmlToArray($result);
            if($array['return_code']!='SUCCESS'){
                return ReturnMessageManager::buildReturnMessage('E0215', null);
            }else{
                $array['timestamp']=$orderInfo['timestamp']; //加入交易开始时间戳
                //下单成功 取出数据 为客户端做数据组合 并签名
                $array_sign=WxPayProxy::mobileSign($array, $type);
                // 拼接返回参数
                $array['sign'] = $array_sign['sign'];
                $array['orderId'] = $orderId;

                return ReturnMessageManager::buildReturnMessage('E0000', ['wxOrder' => $array]);
            }
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 微信公众号支付创建订单
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processWxPayPublicGenerateOrder($app, $di)
    {
        try {
            // 验证参数
            $uid = trim($_POST['userId']);
            if (!$uid) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $amount = trim($_POST['amount']);
            if (!$amount) {
                return ReturnMessageManager::buildReturnMessage('E0214', null);
            }
            $ip = trim($_POST['ipAddress']);
            if (!$ip) {
                return ReturnMessageManager::buildReturnMessage('E0040', null);
            }

            $openid = trim($_POST['openid']);
            if (!$openid) {
                return ReturnMessageManager::buildReturnMessage('E0007', null);
            }

            // 检查用户是否存在
            $user = DBManager::getUserByAccountId($uid);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }
            /**
             * TODO 方便测试暂时注释掉
             */
//            if ($amount < 1) {
//                return ReturnMessageManager::buildReturnMessage('E0245', null);
//            }

            // 创建本地订单
            $orderId = Utils::generateOrderId($uid);
            $now = time();
            // 构建要保存的数据
            $orderData = [
                'user_id' => $user->id,
                'order_num' => $orderId,
                'amount' => $amount,
                'balance' => $user->balance,
                'status' => 0,
                'consum_type' => 1,
                'create_date' => $now,
                'pay_channel' => 2,
                'pay_account' => '',
                'remark' => ''
            ];
            $orderInfo = KakaPay::createUserOrder($orderData);
            // 微信生成订单
            $payUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder'; //接口url地址
            $orderId=$orderInfo['order_id'];

            // 微信下单
            $amount = $amount * 100;

            $data=WxPayProxy::buildPublicXML($orderId, $ip, $openid, $amount,null);

//            $result = Utils::curl_post($payUrl,$data);
//            $array=Utils::xmlToArray($result);
//            if($array['return_code']!='SUCCESS'){
//                return ReturnMessageManager::buildReturnMessage('E0215', null);
//            }else{
//                $array['timestamp']=$orderInfo['timestamp']; //加入交易开始时间戳
//                //下单成功 取出数据 为客户端做数据组合 并签名
//                $array_sign=WxPayProxy::mobileSign($array, 0);
//                // 拼接返回参数
//                $array['sign'] = $array_sign['sign'];
//                $array['orderId'] = $orderId;
//
//                return ReturnMessageManager::buildReturnMessage('E0000', ['wxOrder' => $array]);
//            }

            return ReturnMessageManager::buildReturnMessage('E0000', ['orderId' => $orderId,'requestData' => $data]);
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }
    /**
     * 支付宝订单创建
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processAliPayGenerateOrder($app, $di, $type = 0)
    {
        try {
            // 验证参数
            $uid = trim($_POST['userId']);
            if (!$uid) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $amount = trim($_POST['amount']);
            if (!$amount) {
                return ReturnMessageManager::buildReturnMessage('E0214', null);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($uid);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            /**
             * TODO 方便测试暂时注释掉
             */
//            if ($amount < 1) {
//                return ReturnMessageManager::buildReturnMessage('E0245', null);
//            }

            // 生成订单号
            $orderId = Utils::generateOrderId($uid);
            $now = time();
	        // 构建要保存的数据
	        $orderData = [
		        'user_id' => $uid,
		        'order_num' => $orderId,
		        'amount' => $amount,
		        'balance' => $user->balance,
		        'status' => 0,
		        'consum_type' => 1,
		        'create_date' => $now,
		        'pay_channel' => 1,
		        'pay_account' => '',
		        'remark' => ''
	        ];
            // 创建本地订单
	        $orderInfo = KakaPay::createUserOrder($orderData);
//            $order = DBManager::generateOrder($userId, $orderId, $amount, 1, null, 1);
            if (!$orderInfo) {
                return ReturnMessageManager::buildReturnMessage('E0215', null);
            }
            $request = array();
            if (!$type) {
                //   支付宝订单信息
                $request = AlipayProxy::request($orderId, $amount);
            }
            $data = ReturnMessageManager::buildAliPayOrder($orderId, $request);
            return ReturnMessageManager::buildReturnMessage('E0000', $data);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 查询用户交易记录
     *
     * @param $di
     * @return string
     */
    public static function processGetRechargeRecord($di)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $page = $_POST['page'] ? $_POST['page'] : '1';

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            // 查询交易记录
            $rechargeRecord = DBManager::getUseRechargeRecord($userId);

            // 数组分页类
            $rechargeRecordList = new PaginatorArray([
                'data' => $rechargeRecord->toArray(),
                'limit' => 10,
                'page' => $page
            ]);
            $rechargeRecordList = $rechargeRecordList->getPaginate()->items;

            $data = ReturnMessageManager::buildRechargeRecord($rechargeRecordList);

            return ReturnMessageManager::buildReturnMessage('E0000', ['balanceDetails' => $data]);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 获取钱包流水
     * @param $di
     * @return mixed
     */
    public static function processGetBalanceFlow($di)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $page = $_POST['page'] ? $_POST['page'] : '1';

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            // 查询交易记录
            $balanceFlow = DBManager::getUserBalanceFlow($userId);

            // 数组分页类
            $balanceFlowList = new PaginatorArray([
                'data' => $balanceFlow->toArray(),
                'limit' => 10,
                'page' => $page
            ]);
            $balanceFlowList = $balanceFlowList->getPaginate()->items;

            $data = ReturnMessageManager::buildBalanceFlow($balanceFlowList);

            return ReturnMessageManager::buildReturnMessage('E0000', ['balanceDetails' => $data]);
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * app支付成功，查询订单状态，返回订单结果
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processGetOrderState($app, $di)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $orderId = $_POST['orderId'];
            if (!$orderId) {
                return ReturnMessageManager::buildReturnMessage('E0217', null);
            }
            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }
            // 查询订单信息
            $orderInfo = DBManager::getOrderInfo($userId, $orderId);
            if (!$orderInfo) {
                return ReturnMessageManager::buildReturnMessage('E0216',null);
            }
            $data = ReturnMessageManager::buildUserOrderInfo($orderInfo);
            return ReturnMessageManager::buildReturnMessage('E0000', $data);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 查询用户账户信息（余额）
     *
     * @param $app
     * @param $di
     * @return string
     */
    public static function processGetBalanceInfo($app, $di)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }
            // 获取系统配置
            $systemConfig = DBManager::getSystemConfig();

            // 查询用户余额信息
            $data = ReturnMessageManager::buildUserBalanceInfo($user, $systemConfig);

            return ReturnMessageManager::buildReturnMessage('E0000', $data);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 支付宝提现
     *
     * @param $di
     * @param $hxConfig
     * @return string
     */
    public static function processAliPayWithdrawals($di, $hxConfig)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $withdrawalsAccount = $_POST['withdrawalsAccount'];
            if (!$withdrawalsAccount) {
                return ReturnMessageManager::buildReturnMessage('E0219', null);
            }
            $amount = $_POST['amount'];
            if (!$amount) {
                return ReturnMessageManager::buildReturnMessage('E0214', null);
            }

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

	        // 验证支付密码
            $payPassword = trim($_POST['payPassword']);
            if($payPassword != $user->account->pay_password) {return ReturnMessageManager::buildReturnMessage('E0252');}

            // 获取系统配置
            $systemConfig = DBManager::getSystemConfig();
            // 判断提现金额是否大于最低提现额度
            if ($amount < $systemConfig->withdraw_min_amount) {
                return ReturnMessageManager::buildReturnMessage('E0221', null);
            }
            // 提现金额必须小于等于当前余额
            if($amount > $user->balance) {
                return ReturnMessageManager::buildReturnMessage('E0208', null);
            }

            // 提现手续费
            $serviceCharge = number_format($amount * $systemConfig->withdraw_service_charge, 2);

            // 查询用户当日提现金额
            $startDate = date('Y-m-d 00:00:00');
            $endDate = date('Y-m-d 23:59:59');
            $withdrawalsAmount = DBManager::getUserWithdrawalsAmount($userId, $startDate, $endDate);

            // 判断当日提现金额是否超限
            if ($withdrawalsAmount > $systemConfig->withdraw_day_limit || ($withdrawalsAmount + $amount) > $systemConfig->withdraw_day_limit ) {
                return ReturnMessageManager::buildReturnMessage('E0246', null);
            }

            // 创建提现订单
            $orderId = Utils::generateOrderId($userId);

            $withdrawalsOrder = DBManager::generateOrder($userId, $orderId, $amount, 1, $withdrawalsAccount, 2, $serviceCharge);
            if (!$withdrawalsOrder) {
                return ReturnMessageManager::buildReturnMessage('E0215', null);
            }

            // 用户余额更改
            $userBalance = DBManager::changeUserBalance($user, $amount, 1);
            if ($userBalance) {
                // 支付宝提现
                $transfer = AlipayProxy::transfer($orderId, $withdrawalsAccount, ($amount  - $serviceCharge));
                // 添加订单回调信息
                DBManager::insertOrderCallBackInfo($orderId, $transfer);
                // 判断提现是否成功,更改订单状态
                if ($transfer->code == 10000) {
                    // 修改订单状态
                    $userOrder = DBManager::changeOrderStatusByOrderId($orderId);
                    // 处理支付
	                //KakaPay::processPayTake($di, $user, $amount, $serviceCharge, $userOrder, 1);
                    // 钱包流水
                    KakaPay::createBalanceRecord($user->id, PAYOP_TYPE_TAKE, $amount, 0, $orderId);
					// 系统金额入账
	                KakaPay::createSystemMoneyFlow($user->id, PAYOP_TYPE_TAKE, $amount, PAY_CHANNEL_ALI, 0, $userOrder->id);
					// 发送充值成功消息
					MessageSender::sendTakeSucc($di, $user, $userOrder->amount, PAY_CHANNEL_ALI, $serviceCharge, $userOrder);
                } else {
                    // 提现不成功 修改余额信息
                    DBManager::changeUserBalance($user, $amount,  2);
                    // 组装返回信息
                    return ReturnMessageManager::buildReturnMessage('E0220', (Object)[]);
                }
            }
            // 获取订单信息
            $order = DBManager::getOrderByOrderId($orderId);
            // 拼装订单信息
            $data = ReturnMessageManager::buildUserOrderInfo($order);
            // 构建返回数据
            return ReturnMessageManager::buildReturnMessage('E0000', $data);
        } catch (\Exception $e) {
	        return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 支付宝回调
     *
     * @params $app
     * @params $di
     */
    public static function processAliPayNotify($di)
    {
        require_once "lib/alipay/AopSdk.php";

        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4czu3avrl9mEBJsgOqNQhWL2I0BM3WLG0b8t16TFAN4UqmwgNSkgk5z4Ls8xhqHDHMaXCOHdOkupy1TR7mDhQ83juNlao/ER+hjaCbpaaEZewARM/nV2c9WL9nccSarMMZ7k5fNkZYYnKyM5YOxjpdhI5joMA1/2Qzhdqhl2UNCAsJMvKrIA1MwYUsFx9FqLZbw8g7JVlfj9ifI2N3IsGJcTIdovin5Ebmz+b+vcq2QsMK2aJmfumec1unnOWdMadAaFqAKBuZxWlaCxywYWcPZRln5QJ9RZ5i2tGUeeS9qvPasc6+fP/hwIjY1WRYgf5JjvoI8TkmZn+5KIeb4xFQIDAQAB";
        // 支付宝验签
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if ($flag) {
            $orderId = $_POST['out_trade_no'];
            $amount = $_POST['total_amount'];
            $status = $_POST['trade_status'];
            // 获取订单信息
	        $userOrder = DBManager::getOrderByOrderId($orderId);
	     // 判断订单已完成,返回success
            if ($userOrder->status == 1) {
                return 'success';
            } else {
                // 订单金额正确,订单状态为成功
                if ($amount == $userOrder->amount && ($status == 'TRADE_SUCCESS' || $status == 'TRADE_FINISHED')) {
                	// 将临时数据落地到数据库中
                    if (KakaPay::saveTmpPayToDB($di, PAY_BY_ALI, $userOrder)) {
	                    return 'success';
                    }
                }
            }
        } else {
            $di->get('logger')->log(date('Y-m-d H:i:s'). "-ErrorMessage:" . json_encode($_POST));
        }
    }

    /**
     * 微信回调
     *
     * @param $di
     * @return bool
     */
    public static function processWxPayNotify($di)
    {
        // 获取返回参数
        $data = file_get_contents('php://input');
        $data = Utils::xmlToArray($data);
        // 验签
        $sign = WxPayProxy::getSign($data);
        if ($sign != $data['sign']) {
            return false;
        }
    //	$data['return_code'] = 'SUCCESS';
    //	$data['result_code'] = 'SUCCESS';
    //	$data['out_trade_no'] = '201804112119081000024141';
    //	$data['total_fee'] = 1;
        // 判断返回结果是否准确
        if  ($data['return_code'] === 'SUCCESS' AND $data['result_code'] === 'SUCCESS') {
            // 获取订单信息
            $userOrder = DBManager::getOrderByOrderId($data['out_trade_no']);
            // 订单已完成
            if ($userOrder->status == 1) {
                $reply = "<xml>
                        <return_code><![CDATA[SUCCESS]]></return_code>
                        <return_msg><![CDATA[OK]]></return_msg>
                        </xml>";
                // 向微信后台返回结果
                echo $reply;
                return true;
            } else {
                if (($userOrder->amount * 100) == $data['total_fee']) {
                    if (KakaPay::saveTmpPayToDB($di, PAY_BY_WX, $userOrder)) {
                        $reply = "<xml>
                        <return_code><![CDATA[SUCCESS]]></return_code>
                        <return_msg><![CDATA[OK]]></return_msg>
                        </xml>";
                        // 向微信后台返回结果
                        echo $reply;
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    // 微信公众号支付回调
    public static function processWxPayPublicNotify($di)
    {
        // 获取返回参数
        $data = file_get_contents('php://input');
        $data = Utils::xmlToArray($data);
        // 验签
        $sign = WxPayProxy::getWxPublicSign($data);
        if ($sign != $data['sign']) {
            return false;
        }
        //	$data['return_code'] = 'SUCCESS';
        //	$data['result_code'] = 'SUCCESS';
        //	$data['out_trade_no'] = '201804112119081000024141';
        //	$data['total_fee'] = 1;
        // 判断返回结果是否准确
        if  ($data['return_code'] === 'SUCCESS' AND $data['result_code'] === 'SUCCESS') {
            // 获取订单信息
            $userOrder = DBManager::getOrderByOrderId($data['out_trade_no']);
            // 订单已完成
            if ($userOrder->status == 1) {
                $reply = "<xml>
                        <return_code><![CDATA[SUCCESS]]></return_code>
                        <return_msg><![CDATA[OK]]></return_msg>
                        </xml>";
                // 向微信后台返回结果
                echo $reply;
                return true;
            } else {
                if (($userOrder->amount * 100) == $data['total_fee']) {
                    if (KakaPay::saveTmpPayToDB($di, PAY_BY_WX, $userOrder)) {
                        $reply = "<xml>
                        <return_code><![CDATA[SUCCESS]]></return_code>
                        <return_msg><![CDATA[OK]]></return_msg>
                        </xml>";
                        // 向微信后台返回结果
                        echo $reply;
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * 发普通红包
     *
     * @param $di
     * @return string
     */
    public static function processGiveRedPacket($di)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }

            $amount = $_POST['amount'];
            if (!$amount) {
                return ReturnMessageManager::buildReturnMessage('E0214', null);
            }

            // 检查类型
            $repackType = $_POST['type'];
            if (!$repackType) {
                return ReturnMessageManager::buildReturnMessage('E0255', null);
            }

            $min = 0.01;

            $number = isset($_POST['number']) ? $_POST['number'] : 1;
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $startTime = isset($_POST['startTime']) ? $_POST['startTime'] : '';

            // 定时红包时间不能大于一天
            $appConfig = $di->get('config')['application'];
            if ($startTime) {
                if (strtotime($startTime) > (time() + $appConfig['redpackMaxKeepTime'])) {
                    return ReturnMessageManager::buildReturnMessage('E0235', null);
                }
            } else {
                $startTime = date("Y-m-d H:i:s");
            }

            $content = isset($_POST['content']) ? $_POST['content'] : '';
            $visible = isset($_POST['visible']) ? $_POST['visible'] : '00';
            $describe = isset($_POST['describe']) ? $_POST['describe'] : '';

            //检查是否是家族红包
            $groupId = isset($_POST['groupId']) ? $_POST['groupId'] : 0;
            $group = null;
            if ($groupId) {
                //检查红包数量是否超过群聊或家族的总人数
                $group = DBManager::getAssociationByGroupId($groupId);
                if($number > $group->current_number) {
                    $errorCode = $group->type == 1 ? 'E0318' : 'E0319';
                    return ReturnMessageManager::buildReturnMessage($errorCode, null);
                }
            }

            // 聊天红包
            $consumType = PAYOP_TYPE_SEND_CHAT_REDPACKET;
            if (Utils::verifyMomentVisible($visible)) {
                if ($visible[0] == 1 || $visible[1] == 1) {
                    // 说说红包
                    $consumType = PAYOP_TYPE_SEND_MOMENT_REDPACKET;
                }
            }
            // 返回数据
            $data = [];
            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            // 检查该红包金额是否足以拆分成那么多份红包
            if ($number * $min > $amount) {
                return ReturnMessageManager::buildReturnMessage('E0295');
            }

            // 如果是平均红包, 则检查是否可以均分
            if ($repackType == 1) {
                $avgMod = ($amount * 100) % $number;
                if ($avgMod > 0 ) {
                    return ReturnMessageManager::buildReturnMessage('E0293');
                }
            }
            $now = time();
            // 支付
            $payResult = KakaPay::processPay($user, $consumType, $amount);
            if (array_key_exists('error_code', $payResult)) {
                return $payResult;
            }
            $payResult['consumType'] = $consumType;
            $payResult['payTime'] = $now;

            // 获取支付信息
            $payBy = $payResult['payBy'];
            $orderId = $payResult['orderId'];
            $orderInfo = $payResult['orderInfo'];
            $tmpKey = RedisClient::tmpPayDataKey($payResult['orderId']);

            // 构建Redis对象
            $redis = RedisClient::create($di->get('config')['redis']);
            // 发送红包
            if ($payBy == PAY_BY_BL) {
                $file_prex = '';
            } else {
                $file_prex = 'tmp_';
            }
            // 上传图片 ===================================================================================
            if ($consumType == 3) {
                // 文件的地址
                if ($_FILES['pri_url']) {
                    if ($payBy == PAY_BY_BL) {
                        $uploadRs = OssProxy::uploadMomentsCover($di, $userId);
                    } else {
                        $uploadRs = OssProxy::uploadMomentsCover($di, $userId, 'tmp_');
                    }
                    if ($uploadRs['error']) {
                        return ReturnMessageManager::buildReturnMessage($uploadRs['error']);
                    }
                } else {
                    $uploadRs['url'] = $di->get('config')['application']['default_redpacket_cover'];
                    $uploadRs['thumb'] = $di->get('config')['application']['default_redpacket_cover_thumb'];
                    $uploadRs['preview'] = '';
                }
            } else {
                $uploadRs = array();
            }

            // 根据支付类型判定说说存储到哪?
            if ($payBy == PAY_BY_BL) {
                $result = DBManager::sendUserRedPacket($di, $user, $groupId, $visible, $amount, $number, $password, $startTime, $describe, $repackType, $payResult);
                // 如果要求保存进朋友圈, 则增加一条说说
                if ($consumType == PAYOP_TYPE_SEND_MOMENT_REDPACKET)
                {
                    DBManager::saveMoments($userId, $content, $uploadRs, $visible, 2, $result->id, $amount);
                }
                // 构建返回数据
                $data = ReturnMessageManager::buildGiveRedPacketInfo($result);
            } else {
                $tmpData = $_POST;
                if ($consumType == PAYOP_TYPE_SEND_MOMENT_REDPACKET) {
                    $tmpData['pri_url'] = $uploadRs['url'] ? $uploadRs['url'] : '';
                    $tmpData['pri_thumb'] = $uploadRs['thumb'] ? $uploadRs['thumb'] : '';
                    $tmpData['pri_preview'] = $uploadRs['preview'] ? $uploadRs['preview'] : '';
                }
                kakaPay::saveTmpPayData($redis, $tmpKey, $consumType, $tmpData);
                if ($payBy == PAY_BY_ALI) {
                    $data = ['aliPayOrder' => ['orderId' => $orderId,'orderInfo' => $orderInfo]];
                } else {
                    $data = ['wxOrder' => $orderInfo];
                }
            }

            $redis->close();
            // 返回
            return ReturnMessageManager::buildReturnMessage('E0000', $data);
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }

    /**
     * 抢红包
     *
     * @param $di
     * @return string
     */
    public static function processGrabRedPacket($di)
    {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {
                return ReturnMessageManager::buildReturnMessage('E0013', null);
            }
            $redPacketId = $_POST['redPacketId'];
            if (!$redPacketId) {
                return ReturnMessageManager::buildReturnMessage('E0223', null);
            }
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {
                return ReturnMessageManager::buildReturnMessage('E0044', null);
            }

            // 检查红包是否存在
            $redPacket = DBManager::getRedPacketInfo($redPacketId);
            if (!$redPacket) {
                return ReturnMessageManager::buildReturnMessage('E0224', null);
            }

            // 判断用户是否点击过红包
            $isGrab = DBManager::getUserGrabRedPacket($userId, $redPacketId);
            if (!$isGrab) {
                // 添加用户点红包记录
                DBManager::createUserGrabRedPacketRecord($userId, $redPacketId);
            }

            // 判断是否到抢红包时间
            $time = date('Y-m-d H:i:s');
            if ($redPacket->start_time) {
                if ($redPacket->start_time > $time) {
                    return ReturnMessageManager::buildReturnMessage('E0227', null);
                }
            }
            $startTime = strtotime($redPacket->start_time) ? strtotime($redPacket->start_time) : strtotime($redPacket->create_time);
            $nowTime = strtotime($time);

            // 判断红包已失效
            if ($redPacket->invalid == 1) {
                return ReturnMessageManager::buildReturnMessage('E0231', null);
            }
            $grabRecord = DBManager::getUserGrabRedPacketRecord($userId, $redPacketId);
            // 判断红包已抢完
            if ($redPacket->status == 1) {
                $result = ReturnMessageManager::buildGrabRedPacketInfo($redPacket->id, $grabRecord, $redPacket);
                return ReturnMessageManager::buildReturnMessage('E0000', ['grabRedPacket' => $result]);
            }
            // 判断为口令红包
            if ($redPacket->password) {
                // 判断时间超过24小时，口令失效
                if (($nowTime - $startTime) < 86400) {
                    // 判断是否需要口令
                    if (!$password) {
                        return ReturnMessageManager::buildReturnMessage('E0225', null);
                    } else {
                        // 判断口令是否正确
                        if ($redPacket->password != $password) {
                            return ReturnMessageManager::buildReturnMessage('E0226', null);
                        }
                    }
                }
            }
            // 抢红包
            $result = DBManager::doGrabRedPacket($di, $user, $redPacket);
            if ($result) {
                // 组装返回数据
                $result = ReturnMessageManager::buildGrabRedPacketInfo($redPacket->id, $result, $redPacket);
                return ReturnMessageManager::buildReturnMessage('E0000', ['grabRedPacket' => $result]);
            } else {
                $result = ReturnMessageManager::buildGrabRedPacketInfo($redPacket->id, $grabRecord, $redPacket);
                return ReturnMessageManager::buildReturnMessage('E0000', ['grabRedPacket' => $result]);
            }
        } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }

	/**
	 * 获取红包信息
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetRedPacketInfo($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }
	        $redPacketId = $_POST['redPacketId'];
	        if (!$redPacketId) {
	            return ReturnMessageManager::buildReturnMessage('E0223', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }
	        // 获取红包
	        $redPacket = DBManager::getRedPacketInfo($redPacketId);
	        if (!$redPacket) {
	            return ReturnMessageManager::buildReturnMessage('E0224', null);
	        }

	        // 获取红包记录
	        $redPacketRecord = DBManager::getUserGrabRedPacketRecord($userId, $redPacketId);
	        // 组装数据
	        $data = ReturnMessageManager::buildRedPacketInfo($redPacket, $redPacketRecord, $userId);

	        return ReturnMessageManager::buildReturnMessage('E0000', $data);

	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 获取红包详情
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetRedPacketDetails($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }
	        $redPacketId = $_POST['redPacketId'];
	        if (!$redPacketId) {
	            return ReturnMessageManager::buildReturnMessage('E0223', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        // 获取红包
	        $redPacket = DBManager::getRedPacketInfo($redPacketId);
	        if (!$redPacket) {
	            return ReturnMessageManager::buildReturnMessage('E0224', null);
	        }

	        // 获取红包记录
	        $redPacketRecord = DBManager::getRedPacketRecord($redPacketId);

	        $redPacketRecordNum = DBManager::getRedPacketRecordNo($redPacketId);

	        // 组装数据
	        $data = ReturnMessageManager::buildRedPacketRecord($userId, $redPacket, $redPacketRecord, $redPacketRecordNum);

	        return ReturnMessageManager::buildReturnMessage('E0000', $data);

	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 退还红包
	 *
	 * @param $app
	 * @param $di
	 * @return string
	 */
	public function processReturnRedPacket($app, $di)
	{
	    try {
	        // 获取一天之前,两天之内,发到群聊和聊天的红包
	        $startDate = date('Y-m-d H:i:s', strtotime('-2day'));
	        $endDate = date('Y-m-d H:i:s', strtotime('-1day'));
	        $redPacketList = DBManager::getRedPacketList($app, $startDate, $endDate);

	        // 获取一周之前的发到朋友圈的红包
	        $oneWeekDate = date('Y-m-d H:i:s', strtotime('-1week'));
	        $oneWeekAgoRedPacketList = DBManager::getOneWeekAgoRedPacketList($app, $oneWeekDate);

	        // 退还聊天中的红包
	        DBManager::returnRedPacketList($di, $redPacketList);
	        // 退还朋友圈红包
	        DBManager::returnRedPacketList($di, $oneWeekAgoRedPacketList);
	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 获取排行榜
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetRankingList($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        $type = $_POST['type'];
	        if (!$type || !in_array($type, [1, 2, 3])) {
	            return ReturnMessageManager::buildReturnMessage('E0255', null);
	        }

	        // 检查排行信息
	        $redis = RedisClient::create($di->get('config')['redis']);
	        // 获取排行在榜的用户ID和它发红包的总金额
	        $rankResult = RedisManager::getRank($redis, $type);
	//        $result = ['rank' => array(), 'myRank' => array()];
	        $result = array();
	        switch ($type)
	        {
	            case RANK_TYPE_REDPACK:
	                $rankKey = 'redPacketRank';
	                $result = [$rankKey => [], 'myRedPacketRank' => []];
	                break;
	            case RANK_TYPE_ASSOCIATION:
	                $rankKey = 'famliyLevelRank';
	                $result = [$rankKey => []];
	                break;
	            case RANK_TYPE_USERLEV:
	                $rankKey = 'userLevelRank';
	                $result = [$rankKey => [], 'myUserLevRank' => []];
                    break;
	        }

            // 处理返回数据
            $result = ReturnMessageManager::buildRank($rankResult, $userId, $type, $result, $rankKey);

	        return ReturnMessageManager::buildReturnMessage('E0000', $result);

	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}


	/**
	 * 获取红包排行榜
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetRedPacketRank($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        // 检查排行信息
	        $redis = RedisClient::create($di->get('config')['redis']);
	        // 获取排行在榜的用户ID和它发红包的总金额
	        $rankResult = RedisManager::getRank($redis, 1);

	        $result = ['rank' => array(), 'myRank' => array()];
	        if ($rankResult) {
	            // 处理返回数据
	            $result = ReturnMessageManager::buildRedPacketRank($rankResult, $user);
	        }
	        return ReturnMessageManager::buildReturnMessage('E0000', ['redPacketRank' => $result['rank'], 'myRank' => $result['myRank']]);

	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 获取等级排行
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetUserLevelRank($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        // 检查排行信息
	        $redis = RedisClient::create($di->get('config')['redis']);
	        // 获取排行在榜的用户ID和它发红包的总金额
	        $rankResult = RedisManager::getRank($redis, 3);
	        $result = ['rank' => [], 'myRank' => array()];
	        if ($rankResult) {
	            $result = ReturnMessageManager::buildUserLevelRank($rankResult, $userId);
	        }

	        return ReturnMessageManager::buildReturnMessage('E0000', ['userLevelRank' => $result['rank'], 'myRank' => $result['myRank']]);
	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 获取礼物排行
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetUserGiveGiftRank($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	//            // 从redis中获取排行榜信息
	//            $redis = Utils::redis();
	//            $result = $redis->hGet("rank", "giftRank");
	//            if (!empty($result)) {
	//                $result = json_decode($result, true);
	//                return ReturnMessageManager::buildReturnMessage('E0000', ['redPacketRank' => $result['rank'], 'myRank' => $result['myRank']]);
	//            }

	        // 获取前一百名用户送礼物排行榜
	        $result = DBManager::getUserGiveGiftRank();
	        if ($result) {
	            $result = ReturnMessageManager::buildGiveGiftRank($result, $user);
	        }

	//            // 存到redis中，时效1小时
	//            $redis->hSet("rank", "giftRank", json_encode($result));
	//            if (!$redis->exists("rank")) {
	//                $redis->expire("rank", 3600);
	//            }

	        return ReturnMessageManager::buildReturnMessage('E0000', ['userGiftRank' => $result['rank'], 'myRank' => $result['myRank']]);

	    } catch (\Exception $e) {
	        //插入日志
	//        $di->getLogger()->debug(date('Y-m-d H:i:s')."-METHOD:".__METHOD__."-ErrorMessage：".$e->getMessage());
	//        return ReturnMessageManager::buildReturnMessage('E9999',array('errorMessage'=>$e->getMessage()));
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 赠送礼物
	 *
	 * @param $di
	 * @return string
	 */
	public function processUserGiveGift($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        $targetId = $_POST['targetId'];
	        if (!$targetId) {
	            return ReturnMessageManager::buildReturnMessage('E0055', null);
	        }

	        $giftId = $_POST['giftId'];
	        if (!$giftId) {
	            return ReturnMessageManager::buildReturnMessage('E0232', null);
	        }
	        $momentId = isset($_POST['momentId']) ? $_POST['momentId'] : null;
	        // 赠送礼物数量默认为1
	        $number = isset($_POST['number']) ? $_POST['number'] : 1;

	        // 支付方式
	        $ipAddress = isset($_POST['ipAddress']) ? trim($_POST['ipAddress']) : '';
	        $payPassword = isset($_POST['payPassword']) ? trim($_POST['payPassword']) : '';

	        if ($userId == $targetId) {
	            return ReturnMessageManager::buildReturnMessage('E0234', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }
	        // 检查赠送用户是否存在
	        $target = DBManager::getUserById($targetId);
	        if (!$target) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }
	        // 判断说说是否存在
	        if ($momentId) {
	            $moment = DBManager::getMomentById($targetId, $momentId);
	            if (!$moment) {
	                return ReturnMessageManager::buildReturnMessage('E0135', null);
	            }
	        }

	        // 验证礼物是否存在
	        $gift = DBManager::getGiftById($giftId);

	        if(!$gift) {
	            return ReturnMessageManager::buildReturnMessage('E0233', null);
	        }

	        // 判断支付方式
	        if ($ipAddress) {
	            // ip地址不为空,采用微信支付
	            $amount = $gift->price ;
	            // 创建微信订单
	            $data = DBManager::buildWxPayOrder($userId, $ipAddress, $amount, 4, $gift->name);

	            $orderId = $data['orderId'];
	            $orderInfo = $data['orderInfo'];

	            // 判断下单是否成功
	            if ($orderInfo['return_code'] != 'SUCCESS') {
	                return ReturnMessageManager::buildReturnMessage('E0215', null);
	            } else {
	                //下单成功 取出数据 为客户端做数据组合 并签名
	                $orderInfo = WxPayProxy::mobileSign($orderInfo);

	                // 拼接返回参数
	                $orderInfo['orderId'] = $orderId;

	                // 缓存礼物数据
	                DBManager::buildGiveGiftInRedis($_POST, $orderInfo);

	                // 返回订单信息
	                return ReturnMessageManager::buildReturnMessage('E0000', ['wxOrder' => $orderInfo]);
	            }
	        }

	        // 支付宝支付
	        if (!$ipAddress && !$payPassword) {
	            $amount = $gift->price;
	            $orderInfo = DBManager::buildAliPayOrder($userId, $amount, 4, $gift->name);
	            // 将说说内容存到缓存中
	            DBManager::buildGiveGiftInRedis($_POST, $orderInfo['aliPayOrder']);
	            if ($orderInfo) {
	                return ReturnMessageManager::buildReturnMessage('E0000', $orderInfo);
	            } else {
	                return ReturnMessageManager::buildReturnMessage('E0215', $orderInfo);

	            }
	        }
	        // 余额支付
	        if ($payPassword) {
	            if (!$user->account->pay_password) {
	                return ReturnMessageManager::buildReturnMessage('E0253', null);
	            }
	            if ($payPassword != $user->account->pay_password) {
	                return ReturnMessageManager::buildReturnMessage('E0252', null);
	            }
	        }


	        // 判断余额是否足够
	        if ($user->balance <= $gift->price * $number) {
	            return ReturnMessageManager::buildReturnMessage('E0208', null);
	        }


	        // 赠送礼物
	        $result = DBManager::userGiveGift($user, $target, $gift, $number, $momentId);

	        // 处理返回数据
	        $giftRecord = ReturnMessageManager::buildGiveGiftInfo($result);

	        return ReturnMessageManager::buildReturnMessage('E0000', ['giftInfo' => $giftRecord]);

	    } catch (\Exception $e) {
	        //插入日志
	//        $di->getLogger()->debug(date('Y-m-d H:i:s')."-METHOD:".__METHOD__."-ErrorMessage：".$e->getMessage());
	//        return ReturnMessageManager::buildReturnMessage('E9999',array('errorMessage'=>$e->getMessage()));
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 获取礼物列表
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetGiftList($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        // 获取礼物列表
	        $result = DBManager::getGiftInfo();
	        $result = ReturnMessageManager::buildGiftInfo($result);
	        return ReturnMessageManager::buildReturnMessage('E0000', ['giftList' => $result]);

	    } catch (\Exception $e) {
	        //插入日志
	//        $di->getLogger()->debug(date('Y-m-d H:i:s')."-METHOD:".__METHOD__."-ErrorMessage：".$e->getMessage());
	//        return ReturnMessageManager::buildReturnMessage('E9999',array('errorMessage'=>$e->getMessage()));
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 获取个人赠送礼物记录
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetGiveGiftRecord($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        $index = isset($_POST['index']) ? $_POST['index'] : null;

	        // 获取赠送礼物记录
	        $result = DBManager::getGiveGiftRecord($userId);

	        // 处理返回数据
	        $giftRecord = ReturnMessageManager::buildGiveGiftRecord($result);
	        $giveGiftRecord = array();
	        if ($giftRecord) {
	            $paginator = new PaginatorArray(array(
	                'data' => $giftRecord,
	                'limit' => 10,
	                'page' => $index
	            ));
	            $giveGiftRecord = $paginator->getPaginate()->items;
	        }
	        return ReturnMessageManager::buildReturnMessage('E0000', ['giftRecord' => $giveGiftRecord]);

	    } catch (\Exception $e) {
		    return Utils::processExceptionError($di, $e);
	    }
	}

	/**
	 * 获取个人收礼物记录
	 *
	 * @param $di
	 * @return string
	 */
	public function processGetReceiveGiftRecord($di)
	{
	    try {
	        // 验证参数
	        $userId = $_POST['userId'];
	        if (!$userId) {
	            return ReturnMessageManager::buildReturnMessage('E0013', null);
	        }

	        // 检查用户是否存在
	        $user = DBManager::getUserById($userId);
	        if (!$user) {
	            return ReturnMessageManager::buildReturnMessage('E0044', null);
	        }

	        $index = isset($_POST['index']) ? $_POST['index'] : null;

	        // 获取收到礼物
	        $result = DBManager::getReceiveGiftRecord($userId);

	        // 处理返回数据
	        $giftRecord = ReturnMessageManager::buildReceiveGiftRecord($result);
	        $giveGiftRecord = array();
	        if ($giftRecord) {
	            $paginator = new PaginatorArray(array(
	                'data' => $giftRecord,
	                'limit' => 10,
	                'page' => $index
	            ));
	            $giveGiftRecord = $paginator->getPaginate()->items;
	        }
	        return ReturnMessageManager::buildReturnMessage('E0000', ['giftRecord' => $giveGiftRecord]);

	    } catch (\Exception $e) {
            return Utils::processExceptionError($di, $e);
	    }
	}

    /**
     * 获取家族所有的红包任务
     *
     * @param $di
     * @return mixed
     */
	public static function processGetRewardTask($di)
	{
		try {

			$uid = $_POST['userId'];
			if (!$uid) {
				return ReturnMessageManager::buildReturnMessage('E0013', null);
			}
			$uid = (int)$uid;
			// 获取任务状态
			$taskStatus = (int)trim($_POST['taskStatus']);
			if (!in_array($taskStatus, [1, 2, 0])) {
				return ReturnMessageManager::buildReturnMessage('E0263', null);
			}

			$pageIndex = trim($_POST['pageIndex']);
			if (!$pageIndex) {
				return ReturnMessageManager::buildReturnMessage('E0264', null);
			}
			$pageIndex = (int)$pageIndex;

			$groupId = $_POST['familyId'];
			if (!$groupId) {
				return ReturnMessageManager::buildReturnMessage('E0112', null);
			}
			// 获取公会信息
			$association = DBManager::getAssociationByGroupId($groupId);
			if (!$association) {
				return ReturnMessageManager::buildReturnMessage('E0112', null);
			}

			// 检查该用户是否该群组的成员
			if (!DBManager::existAssociationMember($association->id, $uid)) {
				return ReturnMessageManager::buildReturnMessage('E0266', null);
			}

			// 获取所有的奖励任务
			$data = DBManager::getAssocRewardTasks($di, $groupId, $taskStatus, $pageIndex);
			// 构建返回信息

			return ReturnMessageManager::buildReturnMessage('E0000', ['rewardTasks' => ReturnMessageManager::buildRewardTasks($data)]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 获取红包任务详细信息
     *
     * @param $di
     * @return array|mixed
     */
	public static function processGetRewardTaskDetail($di)
	{
		try {
			$pageIndex = trim($_POST['pageIndex']);
			if (!$pageIndex) {
				return ReturnMessageManager::buildReturnMessage('E0264', null);
			}
			$pageIndex = (int)$pageIndex;

			// 获取任务的权限
			$taskPermData = DBManager::checkRewardTaskReqPerm($di);
			// 检查是否异常
			if ($taskPermData['error_code']) {
				return $taskPermData;
			}
			// 获取任务数据
			$taskId = $taskPermData['task_id'];
			$rewardTask = $taskPermData['reward_task'];

			// 获取所有任务的记录
			$rewardTaskRecords = DBManager::getRewardTaskRecords($di, $taskId, "status = 1", $pageIndex);

			$return = ['collectRewardUsers' => ReturnMessageManager::buildCollectRewardUsers($rewardTaskRecords)];
			if ($pageIndex == 1) {
				$return['rewardTask'] = ReturnMessageManager::buildRewardTask($rewardTask);
			}
			// 返回结果
			return ReturnMessageManager::buildReturnMessage('E0000', $return);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 发布红包任务
     *
     * @param $di
     * @return mixed
     */
	public static function processSubmitRewardTask($di)
	{
		try {
			$uid = $_POST['userId'];
			if (!$uid) {
				return ReturnMessageManager::buildReturnMessage('E0013', null);
			}
			$uid = (int)$uid;

			// 检查用户是否存在
			$user = DBManager::getUserById($uid);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage('E0044', null);
			}

			$groupId = (int)$_POST['familyId'];
			if (!$groupId) {
				return ReturnMessageManager::buildReturnMessage('E0112', null);
			}

			// 获取公会信息
			$association = DBManager::getAssociationByGroupId($groupId);
			if (!$association) {
				return ReturnMessageManager::buildReturnMessage('E0112', null);
			}

			// 检查是否是家族
			if ($association->type == 2) {
				return ReturnMessageManager::buildReturnMessage('E0120');
			}

			// 验证用户是否有发布任务的权限
			if (!Utils::verifyFamilyOpPerm($uid, $association->id, FMPERM_PUB_FAMILYTASK)) {
				return ReturnMessageManager::buildReturnMessage('E0120');
			}

			// 必要参数
			$now = time();
			$checkRet = RewardTask::checkCreateRewardTaskPrams($now);
			if ($checkRet) {return $checkRet;}

			$rewardAmount = (float)$_POST['reward_amount'];
			// Redis客户端实例
			$redis = RedisClient::create($di->get('config')['redis']);
			// 业务类型
			$consumType = 7;
			// 支付
			$payResult = KakaPay::processPay($user, $consumType, $rewardAmount);
			if (array_key_exists('error_code', $payResult)) {
				return $payResult;
			}
			// 获取支付信息
			$payBy = $payResult['payBy'];
			$orderId = $payResult['orderId'];
			$orderInfo = $payResult['orderInfo'];
			$tmpKey = RedisClient::tmpPayDataKey($payResult['orderId']);

			if ($payBy == PAY_BY_BL) {
				$file_prex = '';
			} else {
				$file_prex = 'tmp_';
			}
            $priList = !empty($_FILES['cover_pic']['tmp_name'][0]) ? $_FILES['cover_pic'] : '';
			$coverPic = '';
			$coverThumb = '';
			if ($priList) {
				// 存储空间名
				$oss_bucket = OSS_BUCKET_RTCOVER;
				// OSS上传
//				$ossConfig = $di->get('config')->ossConfig;
				$uploadRS = OssProxy::ossUploadFile($di, $oss_bucket, $uid, UPLOAD_BUSS_RTCOVER, 'cover_pic', $file_prex);
				// 构建保存的图片资源信息
				// 检查是否成功
				if ($uploadRS) {
					// 构建原图与缩略样式的列表
					$coverPic = $uploadRS['oss-request-url'];
					$coverThumb = $uploadRS['thumb'];
				} else {
					// 上传失败
					return ReturnMessageManager::buildReturnMessage('E0061', null);
				}
			} else {
				$coverPic = 'http://dakaapp-public.oss-cn-beijing.aliyuncs.com/rtcover/rtcover_default.png';
				$coverThumb = '?x-oss-process=style/thumb_u_avatar';
			}
			// 根据支付类型判定说说存储到哪?
			if ($payBy == PAY_BY_BL) {
				// 保存红包任务
				$rewardTask = DBManager::saveRewardTask($di, $uid, $association, $coverPic, $coverThumb);
				// 构建订单
				$userOrder = new UserOrder();
				$userOrder->user_id = $uid;
				$userOrder->create_date = $now;
				$userOrder->order_num = $orderId;
				$userOrder->fee = 0;
				$userOrder->status = 1;
				$userOrder->pay_channel = PAY_BY_BL;
				$userOrder->consum_type = PAYOP_TYPE_REWARD_TASK;
				$userOrder->amount = $rewardTask->reward_amount;
				$userOrder->balance = $user->balance;
				// 发送支付红包任务成功的消息
				MessageSender::sendPayRewardTask($di, $user, $rewardTask, $payBy, $userOrder);
				$redis->close();
				// 记录红包任务的操作记录
				KakaPay::saveBalancePayFlow($user, $rewardTask->reward_amount, PAYOP_TYPE_REWARD_TASK, $rewardTask->id, $userOrder);
				// 返回结果
				return ReturnMessageManager::buildReturnMessage('E0000', ['rewardTask' => ReturnMessageManager::buildRewardTaskData($rewardTask)]);
			} else {
				$tmpData = $_POST;
				$tmpData['cover_pic'] = $coverPic;
				$tmpData['cover_thumb'] = $coverThumb;
				kakaPay::saveTmpPayData($redis, $tmpKey, $consumType, $tmpData);
				if ($payBy == PAY_BY_ALI) {
					$data = ['orderId' => $orderId, 'aliPayOrder' => $orderInfo];
				} else {
					$data = ['wxOrder' => $orderInfo];
				}
				$redis->close();
				return ReturnMessageManager::buildReturnMessage('E0000', $data);
			}
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 删除红包任务
     *
     * @param $di
     * @return array|mixed
     */
	public static function processDelRewardTask($di)
	{
		try {
			// 检查
			$taskPermData = DBManager::checkRewardTaskReqPerm($di, true);
			if ($taskPermData['error_code']) {
				return $taskPermData;
			}
			$uid = $taskPermData['uid'];
			$rewardTask = $taskPermData['reward_task'];

			if ($rewardTask['status'] == 1) {
				return ReturnMessageManager::buildReturnMessage('E0265', null);
			} else if ($rewardTask['status'] == -1) {
				return ReturnMessageManager::buildReturnMessage('E0000', ['isDeleted' => 1]);
			}
			// 更新任务状态为已删除
			if (RewardTask::deleteOne($di, $uid, $rewardTask)) {
				$isDeleted = 1;
			} else {
				$isDeleted = 0;
			}
			return ReturnMessageManager::buildReturnMessage('E0000', ['isDeleted' => $isDeleted]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 操作红包任务
     * 类型 1:点击; 2:分享
     *
     * @param $di
     * @return mixed
     */
	public static function processOpRewardTask($di)
	{
		try {
			/** 检查开始 ------------------------------------------------------------------------------------------------ */
			$opType = $_POST['opType'];
			if (!$opType || (!in_array($opType, [1, 2]))) { return ReturnMessageManager::buildReturnMessage('E0262', null); }

			$now = time();
			// 检查 (群组的成员)
			$taskPermData = DBManager::checkRewardTaskReqPerm($di, false);
			if ($taskPermData['error_code']) {
				return $taskPermData;
			}

			$taskId = $taskPermData['task_id'];
			$uid = $taskPermData['uid'];
			$groupId = $taskPermData['group_id'];
			$user = $taskPermData['user_data'];
			$association = $taskPermData['association'];
			$rewardTask = $taskPermData['reward_task'];

			// 默认操作完成
	        $opStatus = 1;
			// 检查任务时间
			if ($now > $rewardTask['end_time']) {
	            $opStatus = 2;                  // 过期操作
			}

			$redis = RedisClient::create($di->get('config')['redis']);
			$duration = DBManager::getUserAssocTaskDuration($redis, $uid, $association->id);
			// 检查用户是否有权限执行该操作
			if(!DBManager::checkUserOpTaskPerm($uid, $rewardTask, $opType)) {
			    $opStatus = 3;                  // 多次操作
			}
			/** 检查结束 ------------------------------------------------------------------------------------------------ */

			// 获取用户信息
			if ($opType == 1) {
				$addHotNum = 5;
				$consumType = PAYOP_TYPE_CLICK_TASK_GET;
				$giveExp = 2;
				$opAmount = $rewardTask['click_reward'];
			} else {
				$addHotNum = 10;
				$consumType = PAYOP_TYPE_SHARE_TASK_GET;
				$giveExp = 20;
				$opAmount = $rewardTask['share_reward'];
			}

			if ($opStatus != 1) {
			    $opAmount = 0;
	        }
			// 父任务ID
			$parentId = (int)$rewardTask['parent_id'];
			$comsPercent = (int)$rewardTask['coms_percent'];
			// 更新任务的余额
			$updateResult = RedisManager::updateOpRewardTask($di, $opStatus, $groupId, $taskId, $opType, $opAmount, $parentId, $comsPercent);
			// 构建数据
			if ($parentId == 0) {
				$dumpTaskIds = [$taskId => $groupId];
			} else {
				$dumpTaskIds = [$taskId => $groupId, $parentId => 0];
			}
			// 数据落地
			DBManager::dumpRewardTask($di, $dumpTaskIds);
			$taskRecordData = [
				'task_id' => $taskId,
				'op_type' => $opType,
				'uid' => $uid,
				'status' => $opStatus == 1 ? 1: 0,
				'op_time' => $now
			];
			// 保存任务记录信息, 记录保持到本次失效为止
			DBManager::saveRewardTaskRecord($di, $taskRecordData, $duration);
			// 增加热度
			SystemHot::addHotNum($taskId, 1, $addHotNum);

			// 更新用户等级经验
			DBManager::changeUserLevel($di, $redis, $user, $giveExp);
			// 更新发布者的经验
            $owner = DBManager::getUserById($rewardTask['owner_id']);
            $ownerExp = $opAmount * 100;
            DBManager::changeUserLevel($di, $redis, $owner, $ownerExp);
            // 更新家族经验
            DBManager::changeAssocLevel($redis, $association, $giveExp + $ownerExp);
            // 更新家族成员经验
            $member = DBManager::getAssociationMemberByUserId($user->id, $association->id);
            DBManager::changeAssociationMemberLevel($member, $giveExp);
            $sendMember = DBManager::getAssociationMemberByUserId($owner->id, $association->id);
            DBManager::changeAssociationMemberLevel($sendMember, $ownerExp);
			// 更新价值
			if ($opAmount > 0) {
                // 生成操作任务订单
				KakaPay::updateUserBalance($user, BALOP_TYPE_ADD, $opAmount, $consumType, 0, 0);
				// 将发红包的人推入周活跃
				RedisManager::pushWeekActive($redis, $owner->id, $opAmount);
			}
			// 构建返回数据
			$data = [
				'opStatus' => $updateResult['opStatus'],
				'opReward' => $updateResult['getAmount'],
				'opType' => $opType
			];
			$redis->close();
			return ReturnMessageManager::buildReturnMessage('E0000', $data);
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 操作系统的红包任务
     * type, 1:点击; 2:分享
     *
     * @param $di
     * @return mixed
     */
	public static function processOpRewardSystemTask($di)
	{
		try {
			/** 检查开始 ------------------------------------------------------------------------------------------------ */
			$opType = $_POST['opType'];
			if (!$opType || (!in_array($opType, [1, 2]))) { return ReturnMessageManager::buildReturnMessage('E0262', null); }

			$now = time();
			// 检查 (群组的成员)
			$taskPermData = DBManager::checkRewardTaskReqPerm($di, false, true, false);
			if ($taskPermData['error_code']) {
				return $taskPermData;
			}

			$taskId = $taskPermData['task_id'];
			$uid = $taskPermData['uid'];
			$user = $taskPermData['user_data'];
			$rewardTask = $taskPermData['reward_task'];

			// 默认操作完成
			$opStatus = 1;
			// 检查任务时间
			if ($now > $rewardTask['end_time']) {
				$opStatus = 2;                  // 过期操作
			}

			$redis = RedisClient::create($di->get('config')['redis']);
			$duration = 86400;

			// 检查用户是否有权限执行该操作
			if(!DBManager::checkUserOpTaskPerm($uid, $rewardTask, $now, $opType, $duration)) {
				$opStatus = 3;                  // 多次操作
			}
			/** 检查结束 ------------------------------------------------------------------------------------------------ */

			// 获取用户信息
			if ($opType == 1) {
				$giveExp = 1;
				$opAmount = $rewardTask['click_reward'];
			} else {
				$giveExp = 2;
				$opAmount = $rewardTask['share_reward'];
			}

			if ($opStatus != 1) {
				$opAmount = 0;
			}
			// 更新任务的余额
			$updateResult = RedisManager::updateOpRewardSystemTask($di, $opStatus, $taskId, $opType, $opAmount);
			// 构建数据
			$dumpTaskIds = [$taskId => 0];
			// 数据落地
			DBManager::dumpRewardTask($di, $dumpTaskIds);
			$taskRecordData = [
				'task_id' => $taskId,
				'op_type' => $opType,
				'uid' => $uid,
				'status' => $opStatus == 1 ? 1: 0,
				'op_time' => $now
			];
			// 保存任务记录信息, 记录保持到本次失效为止
			DBManager::saveRewardTaskRecord($di, $taskRecordData, $duration);
			// 更新用户等级经验
			DBManager::changeUserLevel($di, $redis, $user, $giveExp);

			// 构建返回数据
			$data = [
				'opStatus' => $updateResult['opStatus'],
				'opReward' => $updateResult['getAmount'],
				'opType' => $opType
			];
			$redis->close();
			return ReturnMessageManager::buildReturnMessage('E0000', $data);
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 发布系统红包任务
     *
     * @param $di
     * @return array|mixed
     */
	public static function processSubmitSystemRewardTask($di) {
		try {
			/** 检查开始 ------------------------------------------------------------------------------------------------ */

			// 检查
			$taskPermData = DBManager::checkRewardTaskReqPerm($di, true, true);

			if ($taskPermData['error_code']) {
				return $taskPermData;
			}
			$groupId = $taskPermData['group_id'];
			$rewardTask = $taskPermData['reward_task'];
			$association = $taskPermData['association'];
			$uid = $taskPermData['uid'];
			// 此接口不发布系统任务
			if ($rewardTask['type'] != 0) {
				return ReturnMessageManager::buildReturnMessage('E0282');
			}

			// 验证用户是否是公会管理员
			if (!Utils::verifyFamilyOpPerm($uid, $association->id, FMPERM_PUB_FAMILYTASK)) {
				return ReturnMessageManager::buildReturnMessage('E0120');
			}

			/** 检查结束 ------------------------------------------------------------------------------------------------ */

			$redis = RedisClient::create($di->get('config')['redis']);

			// 奖励的经验, 系统任务固定奖励200经验
			$giveAssocExp = 200;
			// 提高公会等级
			DBManager::changeAssocLevel($redis, $association, $giveAssocExp);

			// 从旧数据中读取新数据
			$newRewardTask = DBManager::copyOldRewardTaskToNew($di, $groupId, $rewardTask);

			if ($rewardTask['type'] == 0) {
				$newRewardTask->balance = $rewardTask['balance'];
				$newRewardTask->title = $rewardTask["title"];
				$newRewardTask->content = $rewardTask["content"];
				$newRewardTask->link = $rewardTask["link"];
				$newRewardTask->cover_pic = $rewardTask['cover_pic'];
				$newRewardTask->cover_thumb = $rewardTask['cover_thumb'];
			}
			$redis->close();
			// 返回结果
			return ReturnMessageManager::buildReturnMessage('E0000', ['rewardTask' => ReturnMessageManager::buildRewardTaskData($newRewardTask)]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 重新发布红包任务
     *
     * @param $di
     * @return mixed
     */
	public static function processResubRewardTask($di) {
		try {
			/** 检查开始 ------------------------------------------------------------------------------------------------ */

			// 检查
			$taskPermData = DBManager::checkRewardTaskReqPerm($di, true, true);
			if ($taskPermData['error_code']) {
				return $taskPermData;
			}

			$uid = $taskPermData['uid'];
			$user = $taskPermData['user_data'];
			$groupId = $taskPermData['group_id'];
			$rewardTask = $taskPermData['reward_task'];
			$association = $taskPermData['association'];

			// 此接口不发布系统任务
			if ($rewardTask['type'] != 1) {
				return ReturnMessageManager::buildReturnMessage('E0282');
			}

			// 不能重新发布继承自系统任务的红包任务
			if ($rewardTask['parent_id'] != 0) {
				return ReturnMessageManager::buildReturnMessage('E0281');
			}

			if ($rewardTask['status'] != 2) {
				return ReturnMessageManager::buildReturnMessage('E0284');
			}

			$payPassword = trim($_POST['pay_password']);
			if (!$payPassword) {
				return ReturnMessageManager::buildReturnMessage('E0249', null);
			}

			// 验证用户是否是公会管理员
			if (!Utils::verifyFamilyOpPerm($uid, $association->id, FMPERM_PUB_FAMILYTASK)) {
				return ReturnMessageManager::buildReturnMessage('E0120');
			}

			$title = $_POST['title'] ? $_POST['title'] : $rewardTask['title'];
			$content = $_POST['content'] ? $_POST['content'] : $rewardTask['content'];

			$rewardAmount = (float)$_POST['reward_amount'];
			$rewardAmount = $rewardAmount ? $rewardAmount : $rewardTask['reward_amount'];

			$clickReward = (float)$_POST['click_reward'];
			$clickReward = $clickReward ? $clickReward : $rewardTask['click_reward'];

			$shareReward = (float)$_POST['share_reward'];
			$shareReward = $shareReward ? $shareReward : $rewardTask['share_reward'];

			$link = (float)$_POST['link'];
			$link = $link ? trim($link) : trim($rewardTask['link']);
			if (!Utils::vaildLink($link)) {
				return ReturnMessageManager::buildReturnMessage('E0285');
			}

			$end_ts = (int)$_POST['end_time'];
			$end_ts = $end_ts ? $end_ts : $rewardTask['end_time'];

			// 必要参数
			$now = time();
			// 结束时间不能小于从发布时间到现在的1天
			if ($end_ts < ($now + 86399)) {
				return ReturnMessageManager::buildReturnMessage('E0276', null);
			}

			/** 检查结束 ------------------------------------------------------------------------------------------------ */

			// 检查余额
			if ($user->balance < $rewardAmount) {
				return ReturnMessageManager::buildReturnMessage('E0063', null);
			}
			$user->balance = $user->balance - $rewardAmount;
			// 封面图片
			$coverPic = $rewardTask['cover_pic'];
			$coverThumb = $rewardTask['cover_thumb'];

			$redis = RedisClient::create($di->get('config')['redis']);

			// 检查是否上传了封面图片
			if ($_FILES['cover_pic']) {
				// 存储空间名
				$oss_bucket = OSS_BUCKET_RTCOVER;
				// OSS上传
				$uploadRS = OssProxy::ossUploadFile($di, $oss_bucket, $uid, UPLOAD_BUSS_RTCOVER, 'cover_pic');
				// 构建保存的图片资源信息

				// 检查是否成功
				if ($uploadRS) {
					// 构建原图与缩略样式的列表
					for ($i = 0; $i < count($uploadRS); $i++) {
						if ($coverPic == '') {
							$coverPic = $uploadRS[$i]['oss-request-url'];
							$coverThumb = $uploadRS[$i]['thumb'];
						} else {
							$coverPic = $coverPic . '|' . $uploadRS[$i]['oss-request-url'];
							$coverThumb = $coverThumb . '|' . $uploadRS[$i]['thumb'];
						}
					}
				} else {
					// 上传失败
					return ReturnMessageManager::buildReturnMessage('E0061', null);
				}
			}
			// 奖励的经验, 1元对应10点经验
			$giveAssocExp = (int)$rewardAmount * 10;
			// 提高公会等级
			DBManager::changeAssocLevel($redis, $association, $giveAssocExp);

			// 从旧数据中读取新数据
			$newRewardTask = DBManager::copyOldRewardTaskToNew($di, $groupId, $rewardTask);
			$newRewardTask->balance = $rewardAmount;
			$newRewardTask->reward_amount = $rewardAmount;
			$newRewardTask->click_reward = $clickReward;
			$newRewardTask->share_reward = $shareReward;
			$newRewardTask->title = $title;
			$newRewardTask->content = $content;
			$newRewardTask->link = $link;
			$newRewardTask->cover_pic = $coverPic;
			$newRewardTask->cover_thumb = $coverThumb;

			// 关闭连接
			$redis->close();
			// 返回结果
			return ReturnMessageManager::buildReturnMessage('E0000', ['rewardTask' => ReturnMessageManager::buildRewardTaskData($newRewardTask)]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 获取系统红包任务
     *
     * @param $di
     * @return array|mixed
     */
	public static function processGetSysAndHisRewardTasks($di)
	{
		try {
			// 检查
			$taskPermData = DBManager::checkRewardTaskReqPerm($di, true);
			if ($taskPermData['error_code']) {
				return $taskPermData;
			}
			// 获取数据
			$uid = $taskPermData['uid'];
			$association = $taskPermData['association'];

			$pageIndex = (int)$_POST['pageIndex'];
			$pageIndex = $pageIndex ? $pageIndex : 1;

			$redis = RedisClient::create($di->get('config')['redis']);
			// 获取系统红包任务
			$systemTasks = DBManager::getNowRunSystemRewardTask($redis, $uid);
			// 获取任务历史
			$historyTasks = DBManager::getGroupRewardTaskHistory($di, $association->group_id, $pageIndex);
			$redis->close();
			return ReturnMessageManager::buildReturnMessage('E0000', [
				'systemRewardTasks' => $systemTasks,
				'historyRewardTasks' => $historyTasks
			]);
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 获取热门列表
     * 返回数据: (系统的)红包任务/ (系统推荐)红包
     *
     * @param $di
     * @return mixed
     */
	public static function getHotList($di)
	{
		try {
			$startIndex = $_POST['startIndex'];
			$uid = $_POST['userId'];
			//获取用户
			$user = DBManager::getUserById($uid);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage('E0044', null);
			}
			// 拉取SystemHot表中的数据
			return DBManager::getSysHotList($di, $uid, $startIndex);
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 分页加载热门列表
     *
     * @param $di
     * @return mixed
     */
    public static function getHotListByPage($di) {
        try {
            //获取用户
            $uid = $_POST['userId'];
            $user = DBManager::getUserById($uid);
            if (!$user) { return ReturnMessageManager::buildReturnMessage('E0044', null);}
            // 获取最后一条记录
            $startIndex = $_POST['startIndex'];
            $startIndex += 20;

            // 拉取SystemHot表中的数据
            $phpl = 'SELECT * FROM Fichat\Models\SystemHot WHERE expo_num > 0 AND type = 3 ORDER BY hot_num DESC LIMIT '.$startIndex.', 20';
            $query = new Query($phpl, $di);
            $data = ReturnMessageManager::buildHotList($di, $uid, $query->execute());
            return ReturnMessageManager::buildReturnMessage('E0000', ['hot_list' => $data, 'startIndex' => $startIndex]);
        } catch (Exception $e) {
            return Utils::processExceptionError($di, $e);
        }
    }

	public static function signHotList($di)
	{
		try {
			$uid = $_POST['userId'];
			//获取用户
			$user = DBManager::getUserById($uid);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage('E0044', null);
			}

			$signIds = json_decode($_POST['signIds']);
			if ($signIds) {
				$signHotIds = '';
				foreach ($signIds as $id) {
					if ($signHotIds == '') {
						$signHotIds = $id;
					} else {
						$signHotIds .= ','.$id;
					}
				}
				$sql = 'UPDATE Fichat\Models\SystemHot SET expo_num = expo_num - 1 WHERE id in ('.$signHotIds.')';
				$query = new Query($sql, $di);
				// 执行
				$query->execute();
			}
			return ReturnMessageManager::buildReturnMessage('E0000');
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 获取动态列表
     * 返回数据: 大咖秀(moment)和红包(所在群的红包)
     *
     * @param $di
     * @return mixed
     */
	public static function getDynList($di)
	{
		try {
			$uid = $_POST['userId'];
			$pageIndex = $_POST['pageIndex'];
			$type = $_POST['type'];
			// 类别说明 1: 好友圈, 2: 关注圈
			if (!in_array($type, [1, 2])) {
				return ReturnMessageManager::buildReturnMessage('E0303');
			}
			// 拉取SystemHot表中的数据
			return DBManager::getSysDynList($di, $uid, $type, $pageIndex);
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

    /**
     * 反馈
     *
     * @param $di
     * @return mixed
     */
	public static function feedback($di)
	{
		try {
			// 验证参数
			$userId = $_POST['userId'];
			if (!$userId) {
				return ReturnMessageManager::buildReturnMessage('E0013', null);
			}

			// 检查用户是否存在
			$user = DBManager::getUserById($userId);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage('E0044', null);
			}
			// 内容
			$content = $_POST['content'];
			if (!$content) {
				return ReturnMessageManager::buildReturnMessage('E0269');
			}
			$feedback = new Feedback();
			$feedback->uid = $userId;
			$feedback->content = $content;
			$feedback->create_time = time();
			if (!$feedback->save()) {
				Utils::throwDbException($feedback);
			}
			return ReturnMessageManager::buildReturnMessage('E0000');
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}

	}

    /**
     * 举报
     *
     * @param $di
     * @return mixed
     */
    public static function processReport($di) {
        try {
            // 验证参数
            $userId = $_POST['userId'];
            if (!$userId) {return ReturnMessageManager::buildReturnMessage('E0013', null);}
            // 检查用户是否存在
            $user = DBManager::getUserById($userId);
            if (!$user) {return ReturnMessageManager::buildReturnMessage('E0044', null);}
            // 类型 1：用户 2：家族 3：动态
            $type = $_POST['type'];
            $by_report_id = $_POST['byReportId'];
            switch ($type) {
                case 1:
                    $byReportUser = DBManager::getUserById($by_report_id);
                    if (!$byReportUser) {return ReturnMessageManager::buildReturnMessage('E0324', null);}
                    break;
                case 2:
                    $byReportAssoc = DBManager::getAssociationById($by_report_id);
                    if (!$byReportAssoc) {return ReturnMessageManager::buildReturnMessage('E0325', null);}
                    break;
                case 3:
                    $byReportMoments = DBManager::getMomentsByMomentsId($by_report_id);
                    if (!$byReportMoments) {return ReturnMessageManager::buildReturnMessage('E0326', null);}
                    break;
            }
            // 原因
            $reason = $_POST['reason'];
            // 内容
            $content = $_POST['content'];

            $report = new Report();
            $report->user_id = $userId;
            $report->by_report_id = $by_report_id;
            $report->type = $type;
            $report->reason = $reason;
            $report->content = $content;
            $report->is_act = 0;
            $report->create_time = date('Y-m-d H:i:s');
            if (!$report->save()) {
                Utils::throwDbException($report);
            }
            // 发送用户消息
            MessageSender::sendUserReport($di, $userId);
            return ReturnMessageManager::buildReturnMessage('E0000');
        } catch (Exception $e) {
            return Utils::processExceptionError($di, $e);
        }

    }


	// 过期回调
	public static function expireKeys($di)
	{
		try {
			$type = $_POST['type'];
			//  检查类型标准
			if (!in_array($type, [1, 2])) {
				return [];
			}
			$ids = trim($_POST['ids']);
			if (!$ids) {
				return [];
			}
			// 解开ID
			$expiredIds = json_decode($ids);
			$inIds = '';
			foreach($expiredIds as $expiredId) {
				if ($inIds) {
					$inIds .= ",".$expiredId;
				} else {
					$inIds = $expiredId;
				}
			}
			$inIds = '('.$inIds.')';
			$data = [];
			switch ($type) {
				case 1:
					// 红包, (到期, 退换, 更新状态)
					$redPackets = RedPacket::find("id in ".$inIds);
					if ($redPackets) {
						DBManager::returnRedPacketList($di, $redPackets);
					}
					break;
				case 2:
					// 任务, (到期, 退换, 更新状态)
					$rewardTasks = RewardTask::find("id in ".$inIds);
					if ($rewardTasks) {
						DBManager::returnRewardTaskList($di, $rewardTasks);
					}
					break;
				default:
					$data = [];
			}
			return $data;
		} catch (Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}

}


