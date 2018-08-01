<?php

namespace Fichat\Middleware;

use Fichat\Common\ReturnMessageManager;
use Fichat\Models\User;
use Fichat\Utils\Utils;
use Phalcon\Events\Event;
use Phalcon\Exception;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;


class SignMiddleware implements MiddlewareInterface
{
    private $sign_params = [
        'userId' => 'E0013',
        'timestamp' => 'E0053',
        'sign' => 'E0052'
    ];

    private $no_sign_proto = [
    	'/_API/_getUserInfoByUnionID',  // 根据unionid获取用户信息
        '/_API/_signup',            // 注册账号
        '/_API/_login',             // 账号登录
        '/_API/_wxLogin',           // 微信登录
        '/_API/_updatePassword',    // 找回密码
        '/_API/_logout',            // 退出登录
	    '/_API/_wxPayNotify',       // 微信支付回调
	    '/_API/_publicNoPaySucc',   // 公众号支付成功, 通知
	    '/_API/_test'
    ];

    /**
     * Before anything happens
     *
     * @params Phalcon\Events\Event  $event
     * @params Phalcon\Mvc\Micro     $app
     *
     * @returns bool
     */
    public function beforeHandleRoute(Event $event, Micro $app)
    {
        // 检查是否在非检查Sign的列表中
        try {
            if (!in_array($_SERVER['REQUEST_URI'], $this->no_sign_proto)) {
                // 从APP中获取配置文件
                $config = $app->getDI()->get('config');
                // 检查是否调试
	            $token = $app->request->getHeader('D-TOKEN');
	            if (!$token) {
		            $app->response
			            ->setJsonContent(ReturnMessageManager::buildReturnMessage(ERROR_TOKEN))
			            ->send();
	            	return false;
	            }
	
	            // 检查token是否存在
	            $user =User::findFirst("token = '".$token."'");
	            if (!$user)
	            {
		            $app->response
			            ->setJsonContent(ReturnMessageManager::buildReturnMessage(ERROR_TOKEN))
			            ->send();
	                return false;
	            }
	            
	            // 检查token是否过期
	            $now = time();
	            if ($now > $user->token_sign_time) {
		            $app->response
			            ->setJsonContent(ReturnMessageManager::buildReturnMessage(ERROR_TOKEN_TIMEOUT))
			            ->send();
	            	return false;
	            }
	            
	            $globalData = Utils::getService($app->getDI(), SERVICE_GLOBAL_DATA);
	            $globalData->uid = $user->id;
	            $globalData->token = $token;
	            $globalData->sessionKey = $user->session_key;
            }
            return true;
        } catch (Exception $e) {
            echo '<br/>Exception:'.$e->getMessage();
            return false;
        }
    }

    /**
     * Calls the middleware
     *
     * @param Micro $application
     *
     * @returns bool
     */
    public function call(Micro $application)
    {
        return false;
    }

    /**
     * 检查参数是否存在
     */
    private function checkBaseParams()
    {
        // 返回编码
        $return = 'E0000';
        // 获取签名参数
        foreach ($this->sign_params as $key => $value) {
            if (!$_POST[$key]) {
                return $value;
                break;
            }
        }
        return $return;
    }
}