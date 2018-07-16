<?php
namespace Fichat\Proxy;

use Fichat\Utils\Utils;

include_once '../fichat_header.php';

define('BD_OPENAPI_URL_TOKEN', '/oauth/2.0/token');

class BaiduOpenApiProxy {
	
//	public static function oAuth($di)
//	{
//		// 获取oAuth的配置
//		$config = $di->get(SERVICE_CONFIG)[CONFIG_KEY_BAIDU_OPENAPI];
//		// 发送数据
//		$uri = self::getRequestUri($config, BD_OPENAPI_URL_OAUTH);
//		// 组合请求
//		$requestUri = $uri.self::combineParam([
//			'response_type' => 'code',
//			'client_id' => $config['app_key'],
//			'redirect_uri' => $config[''],
//			'scope' => 'email',
//			'display' => 'popup'
//		]);
//	}
	
	public static function getToken($di) {
		// 获取oAuth的配置
		$config = $di->get(SERVICE_CONFIG)[CONFIG_KEY_BAIDU_OPENAPI];
		$postParams = [
			'grant_type' => 'client_credentials',
			'client_id' => $config['app_key'],
			'client_secret' => $config['app_secret']
		];
		$uri = self::getRequestUri($config, BD_OPENAPI_URL_TOKEN);
		// 发送数据
		$resp = Utils::curl_post($uri, $postParams);
		// 检查是否是Json
		if (Utils::isJson($resp)) {
			return json_decode($resp);;
		}
		return false;
	}
	
	
	
	private static function getRequestUri($config, $path) {
		return $config['api_uri'].$path;
	}
	
	
}