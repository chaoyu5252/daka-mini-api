<?php
namespace Fichat\Proxy;

use Fichat\Utils\Utils;
/**
 * 发短信api
 */
class SmsProxy{
	
	public static function send($phoneNumber, $type){

		require_once 'lib/aliyun/TopSdk.php';
		//生成验证码随机数
		$phoneCode = Utils::GetRandStr();
		// 短信模板码
		$tplCode = "SMS_39005046";
		$smsParam = "{code:'$phoneCode',product:'大咖社'}";
		switch ($type) {
			case 0:
				$tplCode = 'SMS_39005044';
				break;
			case 2:
				$smsParam = "{code:'$phoneCode'}" ;
				$tplCode = 'SMS_124425027';
				break;
			case 3:
				$smsParam = "{code:'$phoneCode'}" ;
				$tplCode = 'SMS_124390029';
				break;
            case 4:
                $smsParam = "{code:'$phoneCode'}" ;
                $tplCode = 'SMS_135035937';
                break;
		}
		
		$c = new \TopClient();
		$c ->appkey = '23593443' ;
		$c ->secretKey = 'efc9edb87390742166c18b6a6ac1075a' ;
		$req = new \AlibabaAliqinFcSmsNumSendRequest();
		$req ->setExtend("");
		$req ->setSmsType("normal");
		$req ->setSmsFreeSignName( "身份验证" );
		$req ->setSmsParam($smsParam);
		$req ->setRecNum( $phoneNumber );
		
		$req ->setSmsTemplateCode($tplCode);
		$resp = $c ->execute($req);
		if (!$resp->result->success) {
			return false;
		}
		return $phoneCode;
	}
		
}
	
	