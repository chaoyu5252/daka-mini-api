<?php
namespace Fichat\Proxy;

use Fichat\Lib\PushSDK;
use Fichat\Models\SystemNotice;
use Fichat\Utils\Utils;

class BaiduPushProxy {
	
	/**
	 * 发送消息
	 *
	 */
	public static function pushAll($di, $title, $customData = array())
	{
		require_once 'lib/baidupush/sdk.php';
		$opts = ['msg_type'=>1];
		// IOS消息
		$iosMsg = $customData;
		$iosMsg['aps']['alert'] = $title;
		// 安卓消息
		$andMsg = [
			'title' => $title
		];
		// 检查是否有自定义的数据
		if ($customData) {
			$andMsg['custom_content'] = $customData;
		}
		// 构建推送
		$pushSdkArr = [
			'android' => BaiduPushProxy::createPushSdk($di, 'android'),
			'ios' => BaiduPushProxy::createPushSdk($di, 'ios')
		];
		// 循环推送
		foreach ($pushSdkArr as $platform => $pushSdk) {
			if ($pushSdk) {
				
				if ($platform == 'android') {
					$platID = 1;
						// 发送消息
					$rs = $pushSdk->pushMsgToAll($andMsg, $opts);
					
				} else {
					$platID = 0;
					$opts['deploy_status'] = 1;
					// 发送消息
					$rs = $pushSdk->pushMsgToAll($iosMsg, $opts);
				}
				if ($rs) {
					$msgId = $rs['msg_id'];
					$sendTime = $rs['send_time'];
					// 保存数据到系统消息中
					Self::saveMsg($msgId, $sendTime, $platID, $customData);
				}
			}
		}
	}
	
	private static function saveMsg($msgId, $sendTime, $platID, $customData)
	{
		$systemNotice = new SystemNotice();
		$systemNotice->type = $customData['messageType'];
		$systemNotice->msg_id = $msgId;
		$systemNotice->trigger_id = $customData['id'];
		$systemNotice->data = json_encode($customData);
		$systemNotice->send_time = $sendTime;
		$systemNotice->platform = $platID;
		$systemNotice->save();
	}
	
	/**
	 * 构建推送SDK
	 *
	 */
	private static function createPushSdk($di, $platform)
	{
		// 推送配置
		$pushConfig = $di->get('config')['baidu_push'];
		// 根绝平台找到具体的apiKey和secretKey
		$apiKey = $pushConfig[$platform]['apiKey'];
		$secretKey = $pushConfig[$platform]['secretKey'];
		return new \PushSDK($apiKey, $secretKey);
	}

}
