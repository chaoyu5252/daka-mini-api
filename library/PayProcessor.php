<?php
namespace Fichat\Library;

use Fichat\Common\ReturnMessageManager;
use Fichat\Models\User;
use Fichat\Models\UserOrder;
use Fichat\Proxy\WeixinPay;
use Fichat\Utils\Utils;


class PayProcessor {

    /*
     *  TODO 微信下单接口
     */
	/** 微信登录 */
	/** 微信下单 */
	public static function wxPayOrder($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			$uid = $gd->uid;
			
			$payAmount = $_POST['pay_amount'] ? floatval($_POST['pay_amount']) : 0;
			$payItem = $_POST['pay_item'] ? intval($_POST['pay_item']) : 0;
			
			if (!in_array($payItem, [PAY_ITEM_RECHARGE, PAY_ITEM_VIP, PAY_ITEM_TAKE])) {
				return ReturnMessageManager::buildReturnMessage(ERROR_PAY_ITEM);
			}
			
			$config = Utils::getService($di, SERVICE_CONFIG);
			$wxAppConfig = $config[CONFIG_KEY_WXMINI];
			$wxPayConfig = $config[CONFIG_KEY_WXPAY];
			//配置信息
			$appid      = $wxAppConfig['app_id'];
			$mch_id     = $wxPayConfig['mch_id'];
			$mch_key        = $wxPayConfig['mch_secret'];
			// 回调地址
			$notify_url = $_SERVER['SERVER_NAME']."/_API/_wxPayNotify";
			
			//订单号
			$out_trade_no = $mch_id.time();
			$now          = time();
			
			$user = User::findFirst("id = ".$uid);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage(ERROR_NO_USER);
			}
			
			//订单金额
			$total_fee = floatval($payAmount * 100);
			$body      = "账户充值";
			
			//保存订单
			$userOrder = new UserOrder();
			$userOrder->setTransaction($transaction);
			$userOrder->user_id = $uid;
			$userOrder->status = 0;
			$userOrder->balance = $user->balance;
			$userOrder->order_num = $out_trade_no;
			$userOrder->amount = $total_fee;
			$userOrder->consum_type = PAY_ITEM_RECHARGE;
			// 保存用户订单
			if (!$userOrder->save()){
				$transaction->rollback();
			}
			//创建订单
			$weixinpay = new WeixinPay($appid, $user->openid, $mch_id, $mch_key, $out_trade_no, $body, $total_fee, $notify_url);
			$payReturn = $weixinpay->pay();
			if (!array_key_exists('return_msg', $payReturn)) {
				return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['order_info' => $payReturn]);
			} else {
				return ReturnMessageManager::buildReturnMessage(ERROR_PAY_ORDER, ['error_msg' => $payReturn['return_msg']]);
			}
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	// 回调函数
	public static function wxPayNotify($di) {
		try {
			error_log("wxPayNotify");
			// 获取微信支付回调数据
            $xml  = file_get_contents("php://input");
            error_log($xml);
            if(!$xml){
	            exit();
            }
            $data = self::xml2array($xml);
			error_log(json_encode($data));
            //微信给的sign
            $wxSign = $data['sign'];
            unset($data['sign']);   //sign不参与签名算法

            //自己生成sign
            $key  = config('config_app.minapp_mch_key');
            $mySign = self::MakeSign($data,$key);

            // 判断签名是否正确，判断支付状态
            if ( ($mySign===$wxSign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ) {
//	            $result         = $data;
	            //获取服务器返回的数据
	            $out_trade_no   = $data['out_trade_no'];    // 订单单号
	            $itemId         = $data['item_id'];         // 类型
	            $total_fee      = $data['total_fee'];       // 付款金额
	            
	            /** TODO 支付成功结果处理 */
	            $result = self::PaySuccess($out_trade_no, $itemId, $total_fee);
            }else{
	            $result = false;
            }
            // 返回状态给微信服务器
            if ($result) {
	            // 更新用户为VIP
	            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            } else {
	            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
            }
            // 调试信息
            echo $str;
            return $result;
		} catch (\Exception $e) {
			return false;
		}
	}
	
	// 支付完成
	private static function PaySuccess($out_trade_no, $itemId, $total_fee)
	{
		//获取订单信息
		$payOrder = UserOrder::findFirst([
			"conditions" => "order_num = ".$out_trade_no
		]);
		if (!$payOrder) {
			return false;
		}
		// 已经处理过了
		if ($payOrder->getData('status') == 1) {
			return true;
		}
		
		// 更新订单状态
		$payOrder->status = 1;
		if(!$payOrder->save()) {
			return false;
		}
		
		// 根据支付内容
		if ($itemId == PAY_ITEM_RECHARGE) {
			// 充值
			$uid = $payOrder->user_id;
			// 更新用户的余额
			$user = User::findFirst("id = ".$uid);
			if (!$user) {
				return false;
			}
			// 保存用户数据
			$newBalance = floatval($user->balance) + floatval($total_fee);
			$user->balance = $newBalance;
			if(!$user->save()) {
				return false;
			}
		} else {
			// VIP购买
		}
		// 返回
		return true;
	}
	
	
	//xml转换成数组
	private static function xml2array($xml)
	{
		//禁止引用外部xml实体
		libxml_disable_entity_loader(true);
		$xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		$val = json_decode(json_encode($xmlstring) , true);
		return $val;
	}
	
	//作用：生成签名
	private static function MakeSign($params, $KEY)
	{
		//签名步骤一：按字典序排序数组参数
		ksort($params);
		$string = self::ToUrlParams($params);  //参数进行拼接key=value&k=v
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".$KEY;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}
	
	private static function ToUrlParams( $params )
	{
		$string = '';
		if( !empty($params) ){
			$array = array();
			foreach( $params as $key => $value ){
				$array[] = $key.'='.$value;
			}
			$string = implode("&",$array);
		}
		return $string;
	}

}


