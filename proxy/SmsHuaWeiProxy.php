<?php
namespace Fichat\Proxy;
require dirname(__DIR__) .'/vendor/autoload.php';

use Fichat\Utils\Utils;
use GuzzleHttp\Psr7;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SmsHuaWeiProxy {
    public static function sendSms($di, $phone) {
        // 请从应用管理页面获取APP接入地址，替换url中的ip地址和端口
        $url = 'https://117.78.29.66:10443/sms/batchSendSms/v1';

        // 请从应用管理页面获取APP_Key和APP_Secret进行替换
        $APP_KEY = 'wIhgi4d8643Bf2x5r15ST929GV3R';
        $APP_SECRET = 'hCH145DO7MsGF1YqrJEjgm995W6S';
        // 请从模板管理页面获取模板ID进行替换
        $TEMPLATE_ID = 'd6e7c47145104f7ea4dc171008f3e9b0';
        //模板变量请务必根据实际情况修改，查看更多模板变量规则
        //如模板内容为“您有${NUM_2}件快递请到${TXT_32}领取”时，templateParas可填写为["3","人民公园正门"]
        //双变量示例：$TEMPLATE_PARAS = '["3","人民公园正门"]';
        //生成验证码随机数
        $phoneCode = Utils::GetRandStr();
        $TEMPLATE_PARAS = '['.$phoneCode.']';

        // 填写短信签名中的通道号，请从签名管理页面获取
        $sender = 'csms18070202';
        // 填写短信接收人号码
        $receiver = $phone;
        // 状态报告接收地址，为空或者不填表示不接收状态报告
        $statusCallback = '';

        $client = new Client();
        try {
            $response = $client->request('POST', $url, [
                'form_params' => [
                    'from' => $sender,
                    'to' => $receiver,
                    'templateId' => $TEMPLATE_ID,
                    'templateParas' => $TEMPLATE_PARAS,
                    'statusCallback' => $statusCallback
                ],
                'headers' => [
                    'Authorization' => 'WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
                    'X-WSSE' => self::buildWsseHeader($APP_KEY, $APP_SECRET)
                ],
                'verify' => false
            ]);
	    $response = json_decode($response->getBody());
        return  $response->code == "000000" ? $phoneCode : false;
        } catch (RequestException $e) {
            return Utils::processExceptionError($di, $e);
        }
    }

    public static function buildWsseHeader($appKey, $appSecret){
        $now = date('Y-m-d\TH:i:s\Z');
        $nonce = uniqid();
        $base64 = base64_encode(hash('sha256', ($nonce . $now . $appSecret)));
        return sprintf("UsernameToken Username=\"%s\",PasswordDigest=\"%s\",Nonce=\"%s\",Created=\"%s\"",
            $appKey, $base64, $nonce, $now);
    }
}
