<?php
namespace Fichat\Common;

class APIValidator{
	/**
	 * 验证sign签名、判读请求时间
	 * 
	 * @param $params array url参数
	 * @param $token string 用户登录后的token
	 * 
	 * @return success	E0000:签名验证成功  
	 * 		   error    E0009:签名验证错误  E0010:请求时间大于10分钟
	 */
	public static function checkSign($params, $token) {
	    ksort($params);
		if(!$token){ throw new \InvalidArgumentException('token不存在'); }
		if(!$params){ throw new \InvalidArgumentException('url参数不存在'); }
//		$timestamp = $params['timestamp'];
//		if(!$timestamp){ return 'E0053'; }
		$sign = $params['sign'];
//		if(!$sign){ return 'E0052'; }
		
// 		if(abs($params['timestamp'] - time()) > 600){ return 'E0010'; }

		$newParams = '';
		foreach($params as $key => $value){
			if($key == 'sign' || $value == '' || $key == '_url'){
				continue;
			}else{
			    if ($newParams == '') {
                    $newParams =  $key . '=' . $value;
                } else {
                    $newParams .=  '&' . $key . '=' . $value;
                }
			}
		}
		$newSign = md5(trim($newParams, '&').$token);
		if($newSign != $sign){ return 'E0009'; }
		return 'E0000';
	}

	
	
}