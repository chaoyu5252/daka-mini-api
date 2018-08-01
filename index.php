<?php

/**
 * Read services
 */
define('BASE_DIR', dirname(__DIR__));
define('APP_DIR', BASE_DIR . '/daka-mini-api');
define('DOMAIN_NAME', 'http://60.205.1.4:8888');

include APP_DIR . '/config/global_define.php';
include APP_DIR . '/config/config_dev.php';
include APP_DIR . '/lib/aliyun-oss-sdk-2.2.3.phar';

use Fichat\Library\ApiProcessor;
use Fichat\Library\PayProcessor;
use Fichat\Library\RankProcessor;

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

// 微信登录
$app->post('/_API/_wxLogin', function() use ($di){
	return ApiProcessor::wxLogin($di);
});

// 拉取用户微信信息
$app->post('/_API/_wxUpUserInfo', function() use ($di){
	return ApiProcessor::wxInitUserInfo($di);
});

// 推送支付客服消息
$app->post('/_API/_pushWxClientMsg', function () use ($di){
	return ApiProcessor::pushWxClientPayMsg($di);
});

//
$app->post('/_API/_getUserInfoByUnionID', function () use ($di) {
	return ApiProcessor::getUserInfoByUnionId($di);
});

// 获取用户等级
$app->post('/_API/_getUserInfo', function () use ($di) {
	return ApiProcessor::getUserInfo($di);
});

$app->post('/_API/_upload', function() use ($di){
	return ApiProcessor::uploadFile($di);
});

$app->post('/_API/_checkBalance', function() use ($di){
	return ApiProcessor::checkBalance($di);
});

$app->post('/_API/_loadTasks', function () use ($di) {
	return ApiProcessor::loadTaskList($di);
});

$app->post('/_API/_myPubTasks', function () use ($di) {
	return ApiProcessor::loadMyPubTaskList($di);
});

$app->post('/_API/_myJoinTasks', function () use ($di) {
	return ApiProcessor::loadMyJoinTaskList($di);
});

$app->post('/_API/_clickTask', function () use ($di) {
	return ApiProcessor::clickTask($di);
});

$app->post('/_API/_shareTask', function () use ($di) {
	return ApiProcessor::shareTask($di);
});

$app->post('/_API/_shareTaskJoinCount', function () use ($di) {
	return ApiProcessor::addTaskShareCount($di);
});

$app->post('/_API/_publishTask', function() use ($di){
	return ApiProcessor::publishTask($di);
});

// 获取分享任务需要的人数
$app->post('/_API/_shareNeedsJoinCount', function() use ($di){
	return ApiProcessor::getShareJoinCount($di);
});

// 检查是否已经加入了该任务
$app->post('/_API/_isJoinedTask', function() use ($di){
	return ApiProcessor::isJoinedTask($di);
});

// 获取任务手续费
$app->post('/_API/_getTaskFee', function () use ($di) {
	return ApiProcessor::getTaskFee($di);
});

// 获取任务详情
$app->post('/_API/_getTaskDeail', function () use ($di) {
	return ApiProcessor::getTaskDeail($di);
});

// 支付相关
$app->post('/_API/_wxPayOrder', function() use ($di){
	return PayProcessor::wxPayOrder($di);
});

$app->post('/_API/_wxPayNotify', function () use ($di) {
	return PayProcessor::wxPayNotify($di);
});

$app->post('/_API/_publicNoPaySucc', function () use ($di) {
	return PayProcessor::publicNoPaySucc($di);
});

$app->post('/_API/_loadBalanceFlow', function () use ($di) {
	return PayProcessor::loadBalanceFlow($di);
});

$app->post('/_API/_wxPayTake', function () use ($di) {
	return PayProcessor::wxTakeToUser($di);
});

// 获取世界排行
$app->post('/_API/_getWorldRank', function () use ($di) {
	return RankProcessor::getWorldRank($di);
});

// 获取好友排行
$app->post('/_API/_getFriendRank', function () use ($di) {
	return RankProcessor::getFriendRank($di);
});

$app->get('/_API/_test', function () use ($di) {
	return Utils\Utils::getAcessToken($di);
});

$app->post('/_API/_test', function () use ($di) {
	try {
		return Utils\Utils::checkMsg($di, "毛泽东万岁");
	} catch (\Exception $e) {
		var_dump($e);
	}
});

$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    return ['error_code'=>'E0404', 'message' => 'not found this page'];
});

$app->handle();