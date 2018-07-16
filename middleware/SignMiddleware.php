<?php

namespace Fichat\Middleware;

use Fichat\Common\ReturnMessageManager;
use Phalcon\Events\Event;
use Phalcon\Exception;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;

use Fichat\Common\APIValidator;
use Fichat\Common\DBManager;


class SignMiddleware implements MiddlewareInterface
{
    private $sign_params = [
        'userId' => 'E0013',
        'timestamp' => 'E0053',
        'sign' => 'E0052'
    ];

    private $no_sign_proto = [
        '/_API/_signup',            // 注册账号
        '/_API/_login',             // 账号登录
        '/_API/_wxLogin',           // 微信登录
        '/_API/_updatePassword',    // 找回密码
        '/_API/_logout',            // 退出登录
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
                if ($config['debug']['check_sign']) {
                    $checkParamsCode = $this->checkBaseParams();
                    // 如果参数不正确,返回错误码
                    if ($checkParamsCode != 'E0000') {
                        $app->response
                            ->setJsonContent(ReturnMessageManager::buildReturnMessage($checkParamsCode, null))
                            ->send();
                        return false;
                    }
                    $uid = trim($_POST['userId']);
                    // 获取token验证sign
                    $token = DBManager::getToken($uid);
                    // 验证结果
                    $validSignCode = APIValidator::checkSign($_POST, $token);
                    if ($validSignCode != 'E0000') {
                        $app->response
                            ->setJsonContent(ReturnMessageManager::buildReturnMessage($validSignCode))
                            ->send();
                        // 返回结果
                        return false;
                    }
                }
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