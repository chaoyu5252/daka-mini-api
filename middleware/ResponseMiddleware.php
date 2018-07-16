<?php
namespace Fichat\Middleware;

use Fichat\Utils\Utils;
use Phalcon\Mvc\Micro;
use Phalcon\Events\Event;

class ResponseMiddleware implements Micro\MiddlewareInterface
{
    // 不返回json数据的协议, 按各SDK需求返回数据
    private $no_json_urls = [
        '/_API/_aliPayNotify',          // 支付宝回调
        '/_API/_wxPayNotify'            // 微信回调
    ];

    /**
     * 处理返回响应的content-type
     */
    public function afterExecuteRoute(Event $event, Micro $app) {
    	// 关闭缓存连接
    	$redis = Utils::getAppRedis($app);
    	$redis->close();
        // 检查是否是需要采用Json返回的协议
        if(!in_array($_SERVER['REQUEST_URI'], $this->no_json_urls)) {
            // 设置app的返回样式
            $app->response
                ->setContentType("application/json", "UTF-8")           // 设置返回数据类型
                ->setJsonContent($app->getReturnedValue())              // 设置返回结果类型
                ->send();                                               // 发送数据
        }
        return true;
    }

    public function call(Micro $app)
    {
        return true;
    }
}