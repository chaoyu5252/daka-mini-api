<?php
/**
 * Created by PhpStorm.
 * User: xingzhengmao
 * Date: 2018/6/28
 * Time: 下午2:18
 */

namespace Fichat\Proxy;

use Fichat\Utils\Utils;
use Phalcon\Di\FactoryDefault;

define('WX_LOGIN_VERIFY_URI', "https://api.weixin.qq.com/sns/jscode2session");

class WxLoginProxy
{
	// 微信小程序登录验证
	public static function loginVerify(FactoryDefault $di, $code)
	{
		$wxConfig = $di->getShared('config')['wxminiapp'];
		$appId = $wxConfig['app_id'];
		$appKey = $wxConfig['app_key'];
		// 发送请求获取openid和seesionKey
		$wxLoginVerfiyUrl =  self::makeFullVerifyUri($appId, $appKey, $code);
		//echo $wxLoginVerfiyUrl;
		$result = json_decode(Utils::http_get($wxLoginVerfiyUrl), true);
        if (array_key_exists('errcode', $result)) {
            return false;
        }
        return $result;
	}
	
	// 构建完整的登录验证地址
	private static function makeFullVerifyUri($appId, $appKey, $code)
	{
		return WX_LOGIN_VERIFY_URI .
			'?appid='.$appId.'&secret='.$appKey.'&js_code='.$code.'&grant_type=authorization_code';
	}
	
	
}