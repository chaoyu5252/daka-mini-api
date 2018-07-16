<?php

/**
 * Read services
 */
define('BASE_DIR', dirname(__DIR__));
define('APP_DIR', BASE_DIR . '/FichatAPI');
define('DOMAIN_NAME', 'http://60.205.1.4:8888');

include APP_DIR . '/fichat_header.php';
include APP_DIR . '/config/config_dev.php';
include APP_DIR . '/lib/aliyun-oss-sdk-2.2.3.phar';

use Fichat\Library\ApiProcessor;

use Phalcon\Mvc\Micro;
use Phalcon\Events\Manager as EventManager;

/** 中间件 */
use Fichat\Middleware\SignMiddleware;
use Fichat\Middleware\ResponseMiddleware;

// 测试事物异常
use Phalcon\Mvc\Model\Transaction\Manager as TcManager;
use Phalcon\Mvc\Model\Transaction\Failed as TcFailed;

// 测试用库引用
use Fichat\Utils;
use Fichat\Utils\SwooleConn;

// 初始化事件管理器
$eventManager = new EventManager();
// 初始化微应用
$app = new Micro();
// 绑定DI到应用
$app->setDI($di);

/** Event PROCESS BEGIN  =================================== */

// 签名 检查
$eventManager->attach('micro', new SignMiddleware());
$app->before(new SignMiddleware());

// 响应 处理
$eventManager->attach('micro', new ResponseMiddleware());
$app->after(new ResponseMiddleware());


// 注册事件管理器
$app->setEventsManager($eventManager);
/** Event PROCESS END    =================================== */


// API接口文件

/*
 * TODO 帐号相关
 */

// 短信
$app->post('/_API/_sendSMS', function() use ($di){
	return ApiProcessor::processSMS($di);
});

// 注册
//$app->post('/_API/_register', function() use ($di, $config) {
//	return ApiProcessor::processRegister($di, $config->hxConfig);
//});

// 完善用户资料
$app->post('/_API/_updateUserInfo', function() use ($app,$config,$di) {
	return ApiProcessor::processUpUserInfo($config->hxConfig,$di);
});

// 登陆API
$app->post('/_API/_login', function() use ($di) {
	return ApiProcessor::processLogin($di);
});

// 微信登陆
$app->post('/_API/_wxLogin', function() use ($app, $di, $config) {
	return ApiProcessor::processWxLogin($app, $di, $config->hxConfig);
});

// 找回密码
$app->post('/_API/_updatePassword', function() use ($app,$config,$di) {
	return ApiProcessor::processUpdatePassword($app,$config->hxConfig,$di);
});

// 退出登陆API
$app->post('/_API/_logout', function() {
	return ApiProcessor::processLogout();
});

// 添加、更新用户绑定手机号
$app->post('/_API/_bindPhone', function() use ($di) {
	return ApiProcessor::processBindPhone($di);
});

// 设置密码
$app->post('/_API/_setPassword', function() use ($app, $di, $config) {
	return ApiProcessor::processSetPassword($app, $di, $config->hxConfig);
});

// 设置支付密码
$app->post('/_API/_setPayPassword', function() use ($di) {
    return ApiProcessor::processSetPayPassword($di);
});

// 实名认证
$app->post('/_API/_realNameValid', function () use ($di) {
	return ApiProcessor::processRealName($di);
});

/*
 * TODO 人物相关
 */

// 获取用户数据API
$app->post('/_API/_getUser', function() use ($app,$di) {
	return ApiProcessor::processGetUser($app, $di);
});

// 上传用户、家族头像
$app->post('/_API/_user/_profileUpload', function() use($di){
    return ApiProcessor::processProfileUpload($di);
});

// 获取用户称号
$app->post('/_API/_getTitle', function() use ($app,$di) {
    return ApiProcessor::processGetUserTitle($app, $di);
});

// username获取用户数据API
$app->post('/_API/_getUserByUsername', function() use ($app,$di) {
	return ApiProcessor::processGetUserByUsername($app, $di);
});

// 获取新消息数量
$app->post('/_API/_getUserBadge', function() use ($app, $di) {
	return ApiProcessor::processBadge($app, $di);
});

//好友验证
$app->post('/_API/_friendVerify', function() use ($app, $di) {
    return ApiProcessor::processFriendVerify($app, $di);
});

// 设置人物背景图片
$app->post('/_API/_setBackgroundPicture', function() use($di){
    return ApiProcessor::processSetBackgroundPicture($di);
});

// 获取标签列表
$app->post('/_API/_getTags', function () use ($di) {
	return ApiProcessor::processTagList($di);
});

// 获取实名认证
$app->post('/_API/_getRealNameInfo', function () use ($di) {
	return ApiProcessor::processGetRealName($di);
});

/**
 * TODO 搜索相关
 */

// 搜索用户/家族
$app->post('/_API/_search', function() use ($app, $di) {
    return ApiProcessor::processSearch($app, $di);
});

// 搜索用户/家族/家族成员
$app->post('/_API/_searchByType', function() use ($app, $di) {
    return ApiProcessor::processSearchByType($app, $di);
});

// 获取用户消息
$app->post('/_API/_getUserMsg', function () use ($di) {
    return ApiProcessor::processGetUserMsg($di);
});

/**
 * TODO 消息通知相关
 */

// 检查是否有新消息
$app->post('/_API/_checkNewNotice', function() use ($app, $di) {
    return ApiProcessor::processCheckNewNotice($app, $di);
});

// 获取申请列表
$app->post('/_API/_getApplyList', function() use($app, $di, $config){
    return ApiProcessor::processGetApplyList($app, $di);
});

/*
 * TODO 好友相关
 */

// 申请添加好友
$app->post('/_API/_applyAddFriend', function() use ($app,$di, $config) {
	return ApiProcessor::processApplyAddFriend($app, $di, $config->hxConfig);
});

// 删除好友API
$app->post('/_API/_delFriend', function() use ($app, $di) {
	return ApiProcessor::processDelFriend($app, $di);
});

// 关注/取消关注
$app->post('/_API/_followOrNot', function() use ($app, $di, $config) {
	return ApiProcessor::processFollowOrNot($app, $di, $config->hxConfig);
});

// 批量关注
$app->post('/_API/_followManyUsers', function () use ($di) {
	return ApiProcessor::processFollowManyUsers($di);
});

// 获取用户好友列表
$app->post('/_API/_getFriends', function() use ($app, $di) {
	return ApiProcessor::processGetFriends($app, $di);
});

// 获取用户粉丝列表
$app->post('/_API/_getFans', function() use ($di, $config) {
    return ApiProcessor::processGetFans($di, $config->hxConfig);
});

// 获取用户关注列表
$app->post('/_API/_getAttentions', function() use ($app, $di) {
    return ApiProcessor::processGetAttentions($app, $di);
});

// 随机分配陌生人列表
$app->post('/_API/_randomStrangerList', function() use ($app, $di) {
    return ApiProcessor::processRandomBattleList($app, $di);
});

// 屏蔽/解除屏蔽消息
$app->post('/_API/_avoidDisturb', function() use($app, $di){
	return ApiProcessor::processMessageDoNotDisturb($app, $di);
});

// 屏蔽消息用户列表
$app->post('/_API/_messageDoNotDisturbUsers', function() use($app, $di){
	return ApiProcessor::processMessageDoNotDisturbUsers($app, $di);
});

// 确认添加好友
$app->post('/_API/_allowAddFriend', function() use($di, $config){
    return ApiProcessor::processAllowAddFriend($di, $config->hxConfig);
});

// 删除好友请求
$app->post('/_API/_deleteAddFriend', function() use($app, $di){
    return ApiProcessor::processDeleteAddFriend($app, $di);
});

// 获取推荐大咖
$app->post('/_API/_getRecommendUsers', function () use($di) {
	return ApiProcessor::processGetRecommandUser($di);
});


/*
 * TODO 家族、群聊相关
 */

// 获取家族列表
$app->post('/_API/_getFamilies', function() use ($app, $di) {
	return ApiProcessor::processGetFamilies($app, $di);
});

// 申请加入家族
$app->post('/_API/_applyAddAssociation', function() use ($app, $di, $config) {
	return ApiProcessor::processApplyAssociation($app, $di, $config->hxConfig);
});

// 邀请加入家族
$app->post('/_API/_invitAddAssociation', function() use ($app, $di, $config) {
    return ApiProcessor::processInvitAddAssociation($app, $di, $config->hxConfig);
});

// 同意加入家族
$app->post('/_API/_allowAddFamily', function() use($app, $di, $config){
    return ApiProcessor::processAllowAddFamily($app, $di, $config->hxConfig);
});

// 删除家族请求
$app->post('/_API/_deleteAddFamily', function() use($app, $di){
    return ApiProcessor::processDeleteAddFamily($app, $di);
});

// 批量申请加入家族
$app->post('/_API/_applyJoinManyFamilies', function () use ($di) {
	return ApiProcessor::processJoinManyAssociation($di);
});

// 创建家族
$app->post('/_API/_createFamily', function() use ($app, $di, $config) {
    return ApiProcessor::processCreateFamily($app, $di, $config->hxConfig);
});

// 踢出家族
$app->post('/_API/_kickAssociation', function() use ($di, $config) {
	return ApiProcessor::processKickAssociation($di, $config->hxConfig);
});

// 增减管理员
$app->post('/_API/_addDelAssociationAdmin', function() use ($app, $di) {
	return ApiProcessor::processAddDelAssociationAdmin($app, $di);
});

// 解散家族
$app->post('/_API/_dissolveAssociation', function() use ($app, $di, $config) {
	return ApiProcessor::processDissolveAssociation($app, $di, $config->hxConfig);
});

// 转让家族
$app->post('/_API/_makeOverAssociation', function() use ($app, $di, $config) {
	return ApiProcessor::processMakeOverAssociation($app, $di, $config->hxConfig);
});

// 退出家族、群聊
$app->post('/_API/_quitAssociation', function() use ($app, $di, $config) {
	return ApiProcessor::processQuitAssociation($app, $di, $config->hxConfig);
});

// 更新家族、群聊信息
$app->post('/_API/_updateAssociationInfo', function() use ($di) {
	return ApiProcessor::processUpdateAssociationInfo($di);
});

// 获取家族、群聊信息
$app->post('/_API/_getAssociationInfo', function() use ($app, $di) {
	return ApiProcessor::processGetAssociationInfo($app, $di);
});

// 获取家族、群聊成员信息
$app->post('/_API/_getMemberList', function() use ($app, $di) {
	return ApiProcessor::processGetMemberList($app, $di);
});

// 创建群组
$app->post('/_API/_createCluster', function() use ($app, $di, $config) {
	return ApiProcessor::processCreateCluster($app, $di, $config->hxConfig);
});

// 添加群聊成员
$app->post('/_API/_addClusterMember', function() use ($app, $di, $config) {
	return ApiProcessor::processAddClusterMember($app, $di, $config->hxConfig);
});

// 邀请加入群聊
$app->post('/_API/_inviteGroupChat', function() use ($app, $di) {
    return ApiProcessor::processInviteGroupChat($app, $di);
});

// 获取家族/群组详情
$app->post('/_API/_getFamilyDetails', function() use ($di) {
    return ApiProcessor::processGetFamilyDetails($di);
});

// 家族禁言
$app->post('/_API/_familyShutup', function () use ($di) {
    return ApiProcessor::processFamilyShutup($di);
});

// 获取推荐家族列表
$app->post('/_API/_getRecommendUFamilies', function () use ($di) {
	return ApiProcessor::processGetRecommandFamilies($di);
});

// 更新家族成员权限
$app->post('/_API/_updateFamilyMemberPerm', function () use ($di) {
	return ApiProcessor::processUpdateFamilyMemberPerm($di);
});

// 更新家族发言模式
$app->post('/_API/_updateFamilySpeakMode', function () use ($di) {
    return ApiProcessor::processUpdateFamilySpeakMode($di);
});


/*
 * TODO 排行相关
 */

// 获取排行列表
$app->post('/_API/_getRankingList', function() use ($di) {
	return ApiProcessor::processGetRankingList($di);
});

// 获取用户送礼物排行
$app->post('/_API/_getUserGiveGiftRank', function() use ($di) {
    return ApiProcessor::processGetUserGiveGiftRank($di);
});

// 获取红包排行
$app->post('/_API/_getRedPacketRank', function() use ($di) {
    return ApiProcessor::processGetRedPacketRank($di);
});

// 获取用户等级排行
$app->post('/_API/_getUserLevelRank', function() use ($di) {
    return ApiProcessor::processGetUserLevelRank($di);
});

/*
 * TODO 朋友圈相关
 */

// 发表说说
$app->post('/_API/_publishMoments', function() use ($app, $di) {
	return ApiProcessor::processPublishMoments($app, $di);
});

// 说说评论
$app->post('/_API/_momentsReply', function() use ($app, $di) {
	return ApiProcessor::processMomentsReply($app, $di);
});

// 删除评论
$app->post('/_API/_delMomentsReply', function() use ($app, $di) {
    return ApiProcessor::processDelMomentsReply($app, $di);
});

// 分页查询说说评论
$app->post('/_API/_momentsReplyByPage', function() use ($app, $di) {
    return ApiProcessor::processGetMomentsReplyByPage($app, $di);
});

// 说说点赞
$app->post('/_API/_momentsLike', function() use ($app, $di) {
    return ApiProcessor::processMomentsLike($app, $di);
});

// 说说的评论点赞
$app->post('/_API/_momentsReplyLike', function() use ($app, $di) {
    return ApiProcessor::processMomentsReplyLike($app, $di);
});

// 获取更多的点赞
$app->post('/_API/_getMomentsLikeByPage', function() use ($app, $di) {
    return ApiProcessor::processGetMomentsLikeByPage($app, $di);
});

// 单个说说详情
$app->post('/_API/_getMomentDetail', function() use ($app, $di) {
	return ApiProcessor::processGetUserMoments($app, $di);
});

// 查看、取消查看好友朋友圈
$app->post('/_API/_lookOtherMoments', function() use ($app, $di) {
	return ApiProcessor::processLookOtherMoments($app, $di);
});

// 允许、禁止好友查看我的朋友圈
$app->post('/_API/_lookMyMoments', function() use ($app, $di) {
    return ApiProcessor::processLookMyMoments($app, $di);
});

// 不让他看我的朋友圈与不看他的朋友圈用户列表
$app->post('/_API/_notLookMoments', function() use ($app, $di) {
	return ApiProcessor::processNotLookMoments($app, $di);
});

// 设置看不看用户的朋友圈
$app->post('/_API/_setLookUserMoments', function () use ($di) {
	return ApiProcessor::processSetLookUserMoments($di);
});

// 删除说说
$app->post('/_API/_delMoment', function() use ($app, $di) {
    return ApiProcessor::processDelMoment($app, $di);
});

// 获取用户所有作品
$app->post('/_API/_getUserMoments', function () use ($di) {
	return ApiProcessor::processGetUserProduction($di);
});

// 查看、取消查看好友朋友圈 (功能删除)

// 允许、禁止好友查看我的朋友圈 (功能删除)

// 不让他看我的朋友圈与不看他的朋友圈用户列表 (功能删除)

// 添加不让看、不看朋友圈用户列表 (功能删除)

// 删除不让看、不看朋友圈用户 (功能删除)

// 说说打赏 (功能删除)

/**
 * TODO 支付相关
 */

// 兑换咖米
$app->post('/_API/_exchangeKaMi', function() use ($app, $di) {
    return ApiProcessor::processExchangeKaMi($app, $di);
});

// 苹果支付创建订单
$app->post('/_API/_applePayGenerateOrder', function() use ($app, $di) {
    return ApiProcessor::processApplePayGenerateOrder($app, $di);
});

// 苹果支付回调
$app->post('/_API/_applePayNotify', function() use ($di) {
    return ApiProcessor::processApplePayNotify($di);
});

// 微信支付创建订单
$app->post('/_API/_wxPayGenerateOrder', function() use ($app, $di) {
    return ApiProcessor::processWxPayGenerateOrder($app, $di);
});

// 微信H5支付创建订单
$app->post('/_API/_wxPayH5GenerateOrder', function() use ($app, $di) {
    return ApiProcessor::processWxPayGenerateOrder($app, $di, 1);
});

// 微信公众号支付创建订单
$app->post('/_API/_wxPayPublicGenerateOrder', function() use ($app, $di) {
    return ApiProcessor::processWxPayPublicGenerateOrder($app, $di);
});

// 支付宝支付创建订单
$app->post('/_API/_aliPayGenerateOrder', function() use ($app, $di) {
    return ApiProcessor::processAliPayGenerateOrder($app, $di);
});

// 支付宝H5支付创建订单
$app->post('/_API/_aliPayH5GenerateOrder', function() use ($app, $di) {
    return ApiProcessor::processAliPayGenerateOrder($app, $di, 1);
});

// 支付宝回调
$app->post('/_API/_aliPayNotify', function() use ($di) {
    return ApiProcessor::processAliPayNotify($di);
});

// 支付宝提现
$app->post('/_API/_aliPayWithdrawals', function() use ($di, $config) {
    return ApiProcessor::processAliPayWithdrawals($di, $config->hxConfig);
});

// 查询用户交易记录
$app->post('/_API/_getRechargeRecord', function() use ($di) {
    return ApiProcessor::processGetBalanceFlow($di);
});

// 获取订单状态
$app->post('/_API/_getOrderState', function() use ($app, $di) {
    return ApiProcessor::processGetOrderState($app, $di);
});

// 查询账户信息（余额）
$app->post('/_API/_getBalanceInfo', function() use ($app, $di) {
    return ApiProcessor::processGetBalanceInfo($app, $di);
});

// 微信回调
$app->post('/_API/_wxPayNotify', function() use($di) {
    return ApiProcessor::processWxPayNotify($di);
});

// 公众号支付回调
$app->post('/_API/_wxPayPublicNotify', function() use($di) {
    return ApiProcessor::processWxPayPublicNotify($di);
});


/**
 * TODO 红包
 */

// 发红包
$app->post('/_API/_giveRedPacket', function() use($di) {
    return ApiProcessor::processGiveRedPacket($di);
});

// 抢红包
$app->post('/_API/_grabRedPacket', function() use($di) {
    return ApiProcessor::processGrabRedPacket($di);
});

// 获取红包信息
$app->post('/_API/_getRedPacketInfo', function() use($di) {
    return ApiProcessor::processGetRedPacketInfo($di);
});

// 获取红包详情
$app->post('/_API/_getRedPacketDetails', function() use($di) {
    return ApiProcessor::processGetRedPacketDetails($di);
});

// 红包退还
$app->post('/_API/_returnRedPacket', function() use($app, $di) {
    return ApiProcessor::processReturnRedPacket($app, $di);
});


/**
 * TODO 礼物相关
 */

// 获取礼物列表
$app->post('/_API/_getGiftList', function() use($di) {
    return ApiProcessor::processGetGiftList($di);
});

// 发礼物
$app->post('/_API/_giveUserGift', function() use($di) {
    return ApiProcessor::processUserGiveGift($di);
});

// 获取发礼物记录
$app->post('/_API/_getGiveGiftRecord', function() use($di) {
    return ApiProcessor::processGetGiveGiftRecord($di);
});

// 获取收礼物记录
$app->post('/_API/_getReceiveGiftRecord', function() use($di) {
    return ApiProcessor::processGetReceiveGiftRecord($di);
});


/**
 * TODO 红包任务相关
 */

// 发布红包任务
$app->post('/_API/_createRewardTask', function () use ($di) {
    return ApiProcessor::processSubmitRewardTask($di);
});

// 删除红包任务
$app->post('/_API/_deleteRewardTask', function () use ($di) {
    return ApiProcessor::processDelRewardTask($di);
});

// 获取红包任务列表
$app->post('/_API/_getRewardTasks', function () use ($di) {
	return ApiProcessor::processGetRewardTask($di);
});

// 获取红包任务详细信息
$app->post('/_API/_getRewardTaskDetails', function () use ($di) {
	return ApiProcessor::processGetRewardTaskDetail($di);
});

// 点击/分享任务
$app->post('/_API/_clickOrShareTask', function () use ($di) {
	return ApiProcessor::processOpRewardTask($di);
});

// 点击/分享系统任务
$app->post('/_API/_clickOrShareSystemTask', function () use ($di) {
	return ApiProcessor::processOpRewardSystemTask($di);
});

// 重新发布系统红包任务
$app->post('/_API/_reSubmitRwardTask', function () use ($di) {
	Return ApiProcessor::processResubRewardTask($di);
});

// 发布系统任务
$app->post('/_API/_submitSystemRewardTask', function () use ($di) {
	Return ApiProcessor::processSubmitSystemRewardTask($di);
});

// 获取系统红包任务和家族历史红包任务
$app->post('/_API/_getSystemRewardTasks', function () use ($di) {
	return ApiProcessor::processGetSysAndHisRewardTasks($di);
});

/**
 * 系统
 */
$app->post('/_API/_getHotList', function () use ($di) {
	return ApiProcessor::getHotList($di);
});

$app->post('/_API/_getHotListByPage', function () use ($di) {
    return ApiProcessor::getHotListByPage($di);
});

$app->post('/_API/_signHotList', function () use ($di) {
	return ApiProcessor::signHotList($di);
});

$app->post('/_API/_getDynList', function () use ($di) {
	return ApiProcessor::getDynList($di);
});

// 举报
$app->post('/_API/_report', function () use ($di) {
    return ApiProcessor::processReport($di);
});

// 意见反馈
$app->post('/_API/_feedback', function () use ($di) {
	return ApiProcessor::feedback($di);
});

// 退还
$app->post('/_API/_expired', function () use ($di) {
	ApiProcessor::expireKeys($di);
	return json_encode(['type'=>$_POST['type'], 'ids' => $_POST['ids']]);
});

$app->post('/_API/_upLevelMessage', function () use ($di) {
	$uid = $_POST['userId'];
	$user = \Fichat\Common\DBManager::getUserById($uid);
	if (!$user) {
		return \Fichat\Common\ReturnMessageManager::buildReturnMessage('E0044');
	}
	$userAttr = \Fichat\Models\UserAttr::findFirst("id = ".$user->level);
	
	Utils\MessageSender::sendUserUpLevel($di, $user);

	return \Fichat\Utils\Utils::commitTcReturn($di, null, 'E0000');
});


$app->get('/_API/_test2', function () use ($di) {
	
//	$user = \Fichat\Common\DBManager::getUserById(100057);
	$user = \Fichat\Common\DBManager::getUserById(100106);
	$redis = Utils\RedisClient::create($di->get('config')['redis']);
	\Fichat\Common\DBManager::changeUserLevel($di, $redis, $user, 8000);
	$redis->close();
	return null;
});

$app->post('/_API/_test3', function () use ($di) {
	try {
		$manager = new TcManager();
		$transaction = $manager->get();
//		$transaction->isValid()
		$testA = new \Fichat\Models\TestA();
		$testB = new \Fichat\Models\TestB();
		$testA->setTransaction($transaction);
		$testB->setTransaction($transaction);

		$testA->name = "上上坐右";
		if (!$testA->save()) {
			$transaction->rollback();
		}
		$testB->aId = $testA->id;
		if (!$testB->save()) {
			$transaction->rollback();
		}
		var_dump($transaction->commit());
	} catch (TcFailed $e) {
		var_dump($e->getTrace()[1]);
		var_dump($e->getMessage());
	}
});

// 发送TCP消息
$app->post('/_API/_sendTcpMsg', function () use ($di) {
	$client = new SwooleConn($di);
	$pack = json_encode([
		'cmd' => 'HMGET',
		'key' => 'redpack:11',
		'data' => ['amount', 'num']
	]);
	$recv = $client->send($pack);
	$client->close();
	return $recv;
});

$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    return ['error_code'=>'E0404', 'message' => 'not found this page'];
});

$app->handle();