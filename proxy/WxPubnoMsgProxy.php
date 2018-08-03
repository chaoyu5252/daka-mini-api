<?php
/**
 * Created by PhpStorm.
 * User: xingzhengmao
 * Date: 2018/8/3
 * Time: 下午2:22
 */

namespace Fichat\Proxy;

define('ENCODING_AES_KEY', '2PTGFfThqfSq8CsRt1MVhl7juizik6whaXyel2SVOv3');
define('TOKEN', 'dakamini2018');

class WxPubnoMsgProxy
{
	// 固定配置
	
	private $appid;
	private $appSecret;
	
	private $signature;
	private $timestamp;
	private $nonce;
	private $toUserOpenId;
	private $encryptType;
	private $msgSignature;
	private $accessToken;
	private $recvMsg;
	
	function __construct($wxConfig)
	{
		$this->appid = $wxConfig['app_id'];
		$this->appSecret = $wxConfig['app_secret'];
	}
	
	
	public function getMessage($di)
	{
		// 初始化消息
		$this->signature = $_GET["signature"];
		$this->timestamp = $_GET["timestamp"];
		$this->nonce = $_GET["nonce"];
		$this->toUserOpenId = $_GET["openid"];
		$this->encryptType = $_GET["encrypt_type"];
		$this->msgSignature = $_GET["msg_signature"];
		
		// 检查签名
		if (!$this->checkSignature()) {
			return false;
		}
		
		// 拉取授权
		$this->getAccessToken();
		
		// 处理消息
		$procRs = $this->processRecvMsg($di);
		if ($procRs) {
			return $procRs;
		}
		// 返回接收的消息
		return $this->recvMsg;
	}
	
	public function sendMessage()
	{
		$curl2 = curl_init();
		//设置抓取的url
		$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$this->accessToken;
		curl_setopt($curl2, CURLOPT_URL, $url);
		//设置头文件的信息作为数据流输出
		curl_setopt($curl2, CURLOPT_HEADER, 0);
		//设置获取的信息以文件流的形式返回，而不是直接输出。
		curl_setopt($curl2, CURLOPT_RETURNTRANSFER, 1);
		//设置post方式提交
		curl_setopt($curl2, CURLOPT_POST, 1);
		
		$post_data = '{
			    "touser": "'.$this->toUserOpenId.'",
			    "msgtype": "link",
			    "link": {
			          "title": "大咖悬赏充值",
			          "description": "大咖悬赏-新概念传播平台",
			          "url": "http://zombiepang.yuanshuoit.com/pay",
			          "thumb_url": "http://api.dakaapp.com/share_icon.png"
			    }
			}';
		curl_setopt($curl2, CURLOPT_POSTFIELDS, $post_data);
		//执行命令
		$data = curl_exec($curl2);
		//关闭URL请求
		curl_close($curl2);
	}
	
	private function processRecvMsg($di)
	{
		$log = Utils::getService($di, SERVICE_LOG);
		$postData = file_get_contents("php://input");
		$log->debug("wxmessage:".$postData);
		// 解密消息
		$msgCrypt = new \WXBizMsgCrypt();
		$msg = '';
		$errCode = $msgCrypt->decryptMsg($this->msgSignature, $this->timestamp, $this->nonce, $postData, $msg);
		if ($errCode != 0) {
			return $errCode;
		}
		switch ($msg) {
			case "":
				break;
			default:
				// echo "默认的处理";
				return;
		}
	}
	
	private function checkSignature()
	{
		// 2. 检查签名确认消息来自微信服务器
		$tmpArr = array(TOKEN, $this->signature, $this->nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		if( $tmpStr != $this->msgSignature ){
			return false;
		}
		return true;
	}
	
	private function getAccessToken()
	{
		// 获取access_token的地址
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appSecret;
		
		$curl = curl_init();
		//设置抓取的url
		curl_setopt($curl, CURLOPT_URL, $url);
		//设置头文件的信息作为数据流输出
		curl_setopt($curl, CURLOPT_HEADER, 0);
		//设置获取的信息以文件流的形式返回，而不是直接输出。
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		//设置post方式提交
		curl_setopt($curl, CURLOPT_POST, 1);
		//执行命令
		$data = curl_exec($curl);
		//关闭URL请求
		curl_close($curl);
		
		$data = json_decode($data);
		$this->accessToken = $data->access_token;
	}
	
	
	
}