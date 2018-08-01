<?php
namespace Fichat\Library;

use Fichat\Common\ReturnMessageManager;
use Fichat\Models\BalanceFlow;
use Fichat\Models\User;
use Fichat\Models\UserOrder;
use Fichat\Proxy\WeixinPay;
use Fichat\Utils\Utils;


class PayProcessor {

	/**
	 * 公众号支付成功
	 *
	 */
	public static function publicNoPaySucc($di)
	{
		try {
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			
			$unionid = $_POST['union_id'];
			$totalFee = $_POST['total_fee'] ? floatval($_POST['total_fee']) : 0;
			$orderNum = $_POST['order_num'] ? trim($_POST['order_num']) : '';
			$orderRemark = $_POST['order_remark'] ? trim($_POST['order_remark']) : '';
			$data = [];
			
			$user = User::findFirst([
				"conditions" => "unionid='".$unionid."'"
			]);
			// 用户不存在
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage(ERROR_NO_USER);
			}
			$user->setTransaction($transaction);
			
			// 淡定已经有了就不再处理了
			if (UserOrder::findFirst("order_num = '".$orderNum.'"')) {
				return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS);
			}
			
			$newBalance = floatval($user->balance) + $totalFee;
			// 创建用户订单
			$order = new UserOrder();
			$order->setTransaction($transaction);
			$order->status = 1;
			$order->user_id = $user->id;
			$order->consum_type = PAY_ITEM_RECHARGE;
			$order->amount = $totalFee;
			$order->fee = 0;
			$order->order_num = $orderNum;
			$order->remark = $orderRemark;
			$order->balance = $newBalance;
			if (!$order->save())
			{
				$transaction->rollback();
			}
			
			// 更新用户余额
			$user->balance = $newBalance;
			if (!$user->save())
			{
				$transaction->rollback();
			}
			$data['stata'] = 1;
			return Utils::commitTcReturn($di, $data);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	/**
	 * 企业账户提现到零钱
	 */
	public static function wxTakeToUser($di)
	{
		try {
			
			$takeFee = $_POST['take_fee'] ? floatval($_POST['take_fee']) : 0;
			
			$transaction = Utils::getService($di, SERVICE_TRANSACTION);
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			
			// 获取用户信息
			$user = User::findFirst("id = ".$uid);
			if (!$user) {
				return ReturnMessageManager::buildReturnMessage(ERROR_NO_USER);
			}
			$user->setTransaction($transaction);
			
			if ($takeFee < 1) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TAKE_MORE_ONE);
			}
			
			// 检查金额
			if ($takeFee > $user->balance) {
				return ReturnMessageManager::buildReturnMessage(ERROR_TAKE_MORE);
			}
			
			$config = Utils::getService($di, SERVICE_CONFIG);
			$wxAppConfig = $config[CONFIG_KEY_WXMINI];
			$wxPayConfig = $config[CONFIG_KEY_WXPAY];
			
			//配置信息
			$appid      = $wxAppConfig['app_id'];
			$mch_id     = $wxPayConfig['mch_id'];
			$mch_key        = $wxPayConfig['mch_secret'];
			
			$now = time();
			
			$out_trade_no = $mch_id.$now;
			$body = '';
			
			$newBalance = floatval($user->balance) - $takeFee;
			// 更新订单
			$uo = new UserOrder();
			$uo -> order_num = $out_trade_no;
			$uo -> balance = $newBalance;
			$uo -> amount = $takeFee;
			$uo -> remark = "用户提现";
			$uo -> consum_type = PAY_ITEM_TAKE;
			$uo -> status = 1;
			$uo -> fee = 0;
			// 保存
			$uo -> setTransaction($transaction);
			if (!$uo->save()) {
				$transaction->rollback();
			}
			
			// 更新用户的余额
			$user->balance = $newBalance;
			if (!$user->save()) {
				$transaction->rollback();
			}
			
			// 存入现金流
			$bf = new BalanceFlow();
			$bf -> op_type = BALANCE_FLOW_TAKE;
			$bf -> op_amount = $takeFee;
			$bf -> target_id = 0;
			$bf -> user_order_id = $out_trade_no;
			$bf -> uid = $uid;
			$bf -> setTransaction($transaction);
			if (!$bf -> save()) {
				$transaction->rollback();
			}
			
			$takeFee = $takeFee * 100;
			// 创建订单
			$wxPay = new WeixinPay($appid, $user->openid, $mch_id, $mch_key, $out_trade_no, $body, $takeFee, '');
			$wxPayRs = $wxPay->transfers_pay();
			var_dump($wxPayRs);
			if (array_key_exists('return_code', $wxPayRs)) {
				return ReturnMessageManager::buildReturnMessage($di, ERROR_TAKE);
			}
			// 更新流水和订单, 并返回
			return Utils::commitTcReturn($di, ['balance' => $newBalance], ERROR_SUCCESS);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
	/**
	 * 获取余额流水
	 *
	 */
	public static function loadBalanceFlow($di)
	{
		try {
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
			
			// 获取流水
			$bfs = BalanceFlow::find([
				"conditions" => "uid = ".$uid
			]);
			
			$balanceFlows = [];
			if ($bfs) {
				foreach ($bfs as $bf) {
					$item = [
						'op_type' => $bf->op_type,
						'op_amount' => $bf->op_amount,
						'create_time' => $bf->create_time
					];
					array_push($balanceFlows, $item);
				}
			}
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['balance_flow' => $balanceFlows]);
		} catch (\Exception $e) {
			return Utils::processExceptionError($di, $e);
		}
	}
	
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
			
			$now          = time();
			//订单号
			$out_trade_no = $mch_id.$now;
			
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
			$userOrder->consum_type = BALANCE_FLOW_RECHARGE;
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
			echo "wxPayNotify";
			// 获取微信支付回调数据
            $xml  = file_get_contents("php://input");
            error_log($xml);
            if(!$xml){
	            exit();
            }
            $data = self::xml2array($xml);
            var_dump($data);
			//error_log(json_encode($data));
            //微信给的sign
            $wxSign = $data['sign'];
            unset($data['sign']);   //sign不参与签名算法
			
			$config = Utils::getService($di, SERVICE_CONFIG);
//			$wxAppConfig = $config[CONFIG_KEY_WXMINI];
			$wxPayConfig = $config[CONFIG_KEY_WXPAY];
            //自己生成sign
            $key  = $wxPayConfig['mch_secret'];
            $mySign = self::MakeSign($data,$key);

            // 判断签名是否正确，判断支付状态
            if ( ($mySign===$wxSign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ) {
//	            $result         = $data;
	            //获取服务器返回的数据
	            $out_trade_no   = $data['out_trade_no'];    // 订单单号
	            $itemId         = $data['item_id'];         // 类型
	            $total_fee      = $data['total_fee'];       // 付款金额
	            
	            /** TODO 支付成功结果处理 */
	            $result = self::PaySuccess($di, $out_trade_no, $itemId, $total_fee);
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
	private static function PaySuccess($di, $out_trade_no, $itemId, $total_fee)
	{
		$transaction = Utils::getService($di, SERVICE_TRANSACTION);
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
		$payOrder->setTransaction($transaction);
		$payOrder->status = 1;
		if(!$payOrder->save()) {
			$transaction->rollback();
			return false;
		}
		
		// 根据支付内容
		if ($itemId == PAY_ITEM_RECHARGE) {
			// 充值
			$uid = $payOrder->user_id;
			// 更新用户的余额
			$user = User::findFirst([
				"conditions" => "id = ".$uid,
				"for_update" => true
			]);
			if (!$user) {
				return false;
			}
			$user->setTransaction($transaction);
			// 保存用户数据
			$newBalance = floatval($user->balance) + floatval($total_fee);
			$user->balance = $newBalance;
			if(!$user->save()) {
				$transaction->rollback();
				return false;
			}
			
			// 创建支付流水
			$bf = new BalanceFlow();
			$bf->op_type = PAY_ITEM_RECHARGE;
			$bf->target_id = 0;
			$bf->op_amount = $total_fee;
			$bf->user_order_id = $payOrder->order_num;
			$bf->uid = $uid;
			$bf->create_time = time();
			$bf->setTransaction($transaction);
			if (!$bf->save()) {
				$transaction->rollback();
			}
			
		} else {
			// VIP购买
		}
		$transaction->commit();
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


