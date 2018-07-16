<?php
/**
 * @decription: 支付
 *
 */
namespace Fichat\Utils;

include_once '../fichat_header.php';

use Fichat\Common\DBManager;
use Fichat\Common\ReturnMessageManager;
use Fichat\Models\BalanceFlow;
use Fichat\Models\SystemMoneyFlow;
use Fichat\Models\User;
use Fichat\Models\UserOrder;
use Fichat\Proxy\AlipayProxy;
use Fichat\Proxy\OssProxy;
use Fichat\Proxy\WxPayProxy;


class KakaPay {

	/**
	 * 处理支付
	 * @params $user:   用户数据
	 * @params $type:   消费类型
	 * @params $amount: 金额
	 */
	public static function processPay($user, $type, $amount)
	{
		// 支付方式
		$ipAddress = isset($_POST['ipAddress']) ? $_POST['ipAddress'] : '';
		$payPassword = isset($_POST['payPassword']) ? $_POST['payPassword'] : '';
		if ($payPassword && !$ipAddress) {
			// 余额支付
			if (!$user->account->pay_password) {
				return ReturnMessageManager::buildReturnMessage('E0253', null);
			}
			if ($payPassword != $user->account->pay_password) {
				return ReturnMessageManager::buildReturnMessage('E0252', null);
			}
			// 判断余额是否足够
			if ($user->balance < $amount) {
				return ReturnMessageManager::buildReturnMessage('E0208', null);
			}
			// 创建订单ID
			$orderId = Utils::generateOrderId($user->id);
			$data = ['orderId' => $orderId, 'orderInfo' => '', 'key' => '', 'payBy' => PAY_BY_BL];
		} else if ($ipAddress && !$payPassword) {
			// 创建微信订单
			$orderData = self::buildWxPayOrder($user, $ipAddress, $amount, $type, '');
			$orderId = $orderData['orderId'];
			// 微信订单信息
			$orderInfo = $orderData['orderInfo'];
			// 判断下单是否成功
			if ($orderInfo['return_code'] != 'SUCCESS') {
				return ReturnMessageManager::buildReturnMessage('E0215', null);
			}
			//下单成功 取出数据 为客户端做数据组合 并签名
			$orderInfo = WxPayProxy::mobileSign($orderInfo);
			// 拼接返回参数
			$orderInfo['orderId'] = $orderId;
            $orderData['orderInfo']['sign'] = $orderInfo['sign'];
            $orderData['orderInfo']['orderId'] = $orderId;
			// 返回订单信息
			$data = array_merge(['key' => 'wxOrder', 'payBy' => PAY_BY_WX], $orderData);
		} else if (!$ipAddress && !$payPassword) {
			// 创建支付宝订单,
			// ! 处理返回 !
			$orderData = self::buildAliPayOrder($user, $amount, $type, '');
			// 支付宝订单信息
			if (!$orderData['orderInfo']) {
				return ReturnMessageManager::buildReturnMessage('E0215', $orderData['orderInfo']);
			}
			// 返回数据
			return $data = array_merge(['key' => 'aliPayOrder', 'payBy' => PAY_BY_ALI], $orderData);
		} else {
			return ReturnMessageManager::buildReturnMessage('E0300');
		}
		return $data;
	}
	
	/**
	 * 处理提现
	 */
	public static function processPayTake($di, $user, $amount, $serviceCharge, $userOrder, $payChannel)
	{
		// 从系统余额表中减掉总金额
		self::updateUserBalance($user, BALOP_TYPE_REDUCE, $amount, 2, 0, $userOrder->id);
		// 系统金额入账
		self::createSystemMoneyFlow($user->id, PAYOP_TYPE_TAKE, $amount, $payChannel, 0, $userOrder->id);
		// 发送充值成功消息
		MessageSender::sendTakeSucc($di, $user, $userOrder->amount, $payChannel, $serviceCharge, $userOrder);
	}
	
	/**
	 * 构建user_order的remark
	 */
	public static function buildUserOrderRemark($consumType, $amount)
	{
		$remark = '';
		switch ($consumType) {
			case PAYOP_TYPE_RECHARGE:
				$remark = '充值' . $amount . '元';
				break;
			case PAYOP_TYPE_TAKE:
				$remark = '提现' . $remark . '元';
				break;
			case PAYOP_TYPE_SEND_MOMENT_REDPACKET:
				$remark = '发说说红包' . $amount . '元';
				break;
			case PAYOP_TYPE_SEND_CHAT_REDPACKET:
				$remark = '发聊天红包' . $amount . '元';
				break;
			case PAYOP_TYPE_RETURN_REDPACKET:
				$remark = '红包退还' . $amount . '元';
				break;
			case PAYOP_TYPE_REWARD_TASK:
				$remark = '发悬赏任务:' . $amount . '元';
				break;
			case PAYOP_TYPE_RETURN_TASK:
				$remark = '悬赏任务退还:' . $amount . '元';
				break;
			default:
				break;
		}
		return $remark;
	}
	
	/**
	 * 创建用户订单
	 *
	 * @param $uid       用户ID
	 * @param $orderId      订单ID
	 * @param $amount       金额
	 * @param $payChannel   支付方式 1:支付宝 2:微信
	 * @param $payAccount   提现账户
	 * @param $consumType   交易类型 1,充值；2,提现,3:发朋友圈红包, 4: 发聊天红包, 7:发悬赏
	 * @param $remark       备注
	 * @return array
	 */
	public static function createUserOrder($data)
	{
		$orderInfo = new UserOrder();
//		var_dump($data);
		// 构建remark
		$data['remark'] = KakaPay::buildUserOrderRemark($data['consum_type'], $data['amount']);
		// status：订单状态，1成功,0，失败
		$orderInfo->assign($data);
		if (!$orderInfo->save()) {
			throw new \RuntimeException(__METHOD__.$orderInfo->getMessages()[0]);
		}
		return array('order_id'=>$data['order_num'],'timestamp'=>$data['create_date'],'remark'=>$data['remark'],'id'=>$orderInfo->id);
	}
	
	/**
	 * 创建余额流水记录
	 *
	 * @param $uid       用户ID
	 * @param $op_type   操作类型
	 * @param $amount    金额
	 * @param $targetId  支付方式 1:支付宝 2:微信
	 * @param $userOrderID   提现账户
	 * @return array | null
	 */
	public static function createBalanceRecord($uid, $op_type, $amount, $targetId, $userOrderID)
	{
		$now = time();
		$balanceFlow = new BalanceFlow();
		$data = array(
			'op_type' => $op_type,
			'op_amount' => $amount,
			'targetId' => $targetId,
			'user_order_id' => $userOrderID,
			'uid' => $uid,
			'create_time' => $now
		);
		$balanceFlow->assign($data);
		if (!$balanceFlow->save()) {
			Utils::throwDbException($balanceFlow);
		}
		$data['id'] = $balanceFlow->id;
		return $data;
	}
	
	/**
	 * 创建系统金额记录
	 *
	 * @param $uid       用户ID
	 * @param $op_type   订单ID
	 * @param $amount    金额
	 * @param $payChannel   提现账户
	 * @param $targetId  支付方式 1:支付宝 2:微信
	 * @return array | null
	 */
	public static function createSystemMoneyFlow($uid, $op_type, $amount, $payChannel, $targetId, $userOrderId)
	{
		$now = time();
		$systemMoenyFlow = new SystemMoneyFlow();
		$data = array(
			'op_type' => $op_type,
			'op_amount' => $amount,
			'target_id' => $targetId,
			'pay_channel' => $payChannel,
			'user_order_id' => $userOrderId,
			'uid' => $uid,
			'create_time' => $now
		);
		$systemMoenyFlow->assign($data);
		if (!$systemMoenyFlow->save()) {
			Utils::throwDbException($systemMoenyFlow);
		}
		$data['id'] = $systemMoenyFlow->id;
		return $data;
	}
	
	/**
	 * 创建微信订单
	 *
	 * @param $user         用户数据
	 * @param $ipAddress    ip地址(创建微信订单时使用)
	 * @param $amount       金额
	 * @param $remark       备注
	 * @return array
	 */
	public static function buildWxPayOrder($user, $ipAddress, $amount, $consumType, $remark)
	{
		$now = time();
		// 创建订单ID
		$orderId = Utils::generateOrderId($user->id);
		// 构建要保存的数据
		$orderData = [
			'user_id' => $user->id,
			'order_num' => $orderId,
			'amount' => $amount,
			'balance' => $user->balance,
			'status' => 0,
			'consum_type' => $consumType,
			'create_date' => $now,
			'pay_channel' => PAY_CHANNEL_ALI,
			'pay_account' => '',
			'remark' => $remark
		];
		// 构建用户订单数据
		$orderInfo = self::createUserOrder($orderData);
		// 微信生成订单
		$payUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder'; //接口url地址
		$orderId = $orderInfo['order_id'];
		
		// 微信下单
		$amount = $amount * 100;
		switch ($consumType) {
			case PAYOP_TYPE_SEND_MOMENT_REDPACKET:
				$rechargeDes = '发红包';
				break;
			case PAYOP_TYPE_SEND_CHAT_REDPACKET:
				$rechargeDes = '发红包';
				break;
			case PAYOP_TYPE_REWARD_TASK:
				$rechargeDes = '发布悬赏任务';
				break;
			default:
				$rechargeDes = '';
				break;
		}
		$data = WxPayProxy::buildXML($orderId, $ipAddress, $amount, $rechargeDes);
		$result = Utils::curl_post($payUrl, $data);
		$order = Utils::xmlToArray($result);
		$order['timestamp'] = $orderInfo['timestamp'];
		// 临时Key
		return ['orderId' => $orderId, 'orderInfo' => $order];
	}
	
	/**
	 * 创建支付宝订单
	 *
	 * @param $user         用户数据
	 * @param $amount       金额
	 * @param $consumType   消费类型
	 * @param $remark       备注
	 * @return array|bool
	 */
	public static function buildAliPayOrder($user, $amount, $consumType, $remark)
	{
		$now = time();
		// 创建订单ID
		$orderId = Utils::generateOrderId($user->id);
		
		// 构建要保存的数据
		$orderData = [
			'user_id' => $user->id,
			'order_num' => $orderId,
			'amount' => $amount,
			'balance' => $user->balance,
			'status' => 0,
			'consum_type' => $consumType,
			'create_date' => $now,
			'pay_channel' => PAY_CHANNEL_ALI,
			'pay_account' => '',
			'remark' => $remark
		];
		// 构建用户订单数据
		$orderInfo = self::createUserOrder($orderData);
		if ($orderInfo) {
			switch ($consumType) {
				case PAYOP_TYPE_SEND_MOMENT_REDPACKET:
					$rechargeDes = '发红包';
					break;
				case PAYOP_TYPE_SEND_CHAT_REDPACKET:
					$rechargeDes = '发红包';
					break;
				case PAYOP_TYPE_REWARD_TASK:
					$rechargeDes = '发布悬赏任务';
					break;
				default:
					$rechargeDes = '';
					break;
			}
			// 支付宝订单信息
			$request = AlipayProxy::request($orderId, $amount, $rechargeDes);
			// 返回数据
			return ['orderId' => $orderId, 'orderInfo' => $request];
		} else {
			return false;
		}
	}
	
	/**
	 * 存储临时数据到数据库中
	 *
	 */
	public static function saveTmpPayToDB($di, $payChannel, $userOrder)
	{
		$user = DBManager::getUserById($userOrder->user_id);
		$tmpKey = RedisClient::tmpPayDataKey($userOrder->order_num);
		$redis = RedisClient::create($di->get('config')['redis']);
		// 获取数据
		$tmpPayData = $redis->hGetAll($tmpKey);
		if ($tmpPayData) {
			switch ($userOrder->consum_type) {
				case PAYOP_TYPE_SEND_MOMENT_REDPACKET:
					$payResult = ['payBy' => $payChannel, 'orderId' => $userOrder->order_num];
					$result = self::saveTmpRedpacket($di, $user, $tmpPayData, $payResult);
					if ($result) {
						// 说说内容和图片及缩略图
						$content =  $tmpPayData['content'] ? $tmpPayData['content'] : '';
						$pri_url = $tmpPayData['pri_url'];
						$pri_thumb = $tmpPayData['pri_thumb'] ? $tmpPayData['pri_thumb'] : '';
						$pri_preview = $tmpPayData['pri_preview'] ? $tmpPayData['pri_preview'] : '';
						$visible = $tmpPayData['visible'];
						$momentType = $tmpPayData['moment_type'];
						// 存储空间名
						$oss_bucket = OSS_BUCKET_MOMENTS;
						// OSS上传
						$ossConfig = $di->get('config')->ossConfig;
						// 拷贝OSS对象到新地址
						$copyRs = OssProxy::copyFiles($ossConfig, $pri_url, $pri_preview);
						$copyRs['thumb'] = $pri_thumb;
						// 发表说说
						DBManager::saveMoments($user->account_id, $content, $copyRs, $visible, $momentType, $result->id);
					}
					break;
				case PAYOP_TYPE_SEND_CHAT_REDPACKET:
					$payResult = ['payBy' => $payChannel, 'orderId' => $userOrder->order_num];
					$result = self::saveTmpRedpacket($di, $user, $tmpPayData, $payResult);
					break;
				case PAYOP_TYPE_REWARD_TASK:
					$result = self::saveTmpRewardTask($di, $user, $tmpPayData, $payChannel, $userOrder);
					break;
				default:
					break;
			}
//            $di->get('logger')->debug(self::makeLogMessage($di, $result));
			if ($result) {
				// 修改订单状态
				DBManager::updateUserOderStatus($user, $userOrder, $result->id);
				// 增加一条充值记录(balance)
				self::createBalanceRecord($user->id, PAYOP_TYPE_RECHARGE, $userOrder->amount, $result->id, $userOrder->id);
				// 增加一条消费记录(balance)
				self::createBalanceRecord($user->id, $userOrder->consum_type, $userOrder->amount, $result->id, $userOrder->id);
				$redis->close();
				return true;
			}
		} else {
			// 根据操作类型进行判定
			switch ($userOrder -> consum_type) {
				case PAYOP_TYPE_RECHARGE:
					self::updateUserBalance($user, BALOP_TYPE_ADD, $userOrder->amount, PAYOP_TYPE_RECHARGE, 0, $userOrder->id);
					// 系统金额入账
					self::createSystemMoneyFlow($user->id, PAYOP_TYPE_RECHARGE, $userOrder->amount, $payChannel, 0, $userOrder->id);
                    // 修改订单状态
                    DBManager::updateUserOderStatus($user, $userOrder, 0);
					// 发送充值成功消息
					MessageSender::sendRechargeSucc($di, $user, $userOrder->amount, $payChannel, $userOrder);
					$ret = true;
					break;
//				case PAYOP_TYPE_TAKE:
//					// 手续费
//					$fee = round($userOrder->amount * 0.015, 2);
//					// 获取能提现的金额
////					$takeAmount = $userOrder->amount - $fee;
//					// 从系统余额表中减掉总金额
//					self::updateUserBalance($user, BALOP_TYPE_REDUCE, $userOrder->amount, PAYOP_TYPE_RECHARGE, 0, $userOrder->id);
//					// 系统金额入账
//					self::createSystemMoneyFlow($user->id, PAYOP_TYPE_TAKE, $userOrder->amount, $payChannel, 0, $userOrder->id);
//					// 发送充值成功消息
//					MessageSender::sendTakeSucc($di, $user, $userOrder->amount, $payChannel, $fee, $userOrder->order_num);
//					$ret = true;
//					break;
				default:
					$ret = false;
			}
			return $ret;
		}
		// 关闭Redis连接
		$redis->close();
	}
	
	/**
	 * 更新账户余额
	 * @param $user     用户数据
	 * @param $amount   操作金额
	 * @param $aodType  操作类型, 加/减
	 * @param $opType   操作业务类型,
	 * @param $targetId 目标ID
	 * @param $orderId  订单ID
	 * @return User
	 */
	public static function updateUserBalance($user, $aodType, $amount, $opType, $targetId = 0, $orderId = 0) {
		$toSave = true;
		switch ($aodType) {
			case BALOP_TYPE_ADD:
				$user->balance += $amount;
				break;
			case BALOP_TYPE_REDUCE:
				$user->balance -= $amount;
				break;
			default:
				$toSave = false;
				break;
		}
		if ($toSave) {
			// 更新用户余额
			if(!$user->save()){ throw new \RuntimeException(__METHOD__.$user->getMessages()[0]); }
			// 添加订单ID
			self::createBalanceRecord($user->id, $opType, $amount, $targetId, $orderId);
		}
		return $user;
	}
	
	// 余额消费
	public static function saveBalancePayFlow($user, $amount, $consumType, $targetId, $userOrder = null)
	{
		if (!$userOrder) {
			$orderId = Utils::generateOrderId($user->id);
			$userOrder = new UserOrder();
			$userOrder->order_num = $orderId;
			$userOrder->fee = 0;
			$userOrder->status = 1;
			$userOrder->create_date = time();
			$userOrder->pay_channel = PAY_BY_BL;
			$userOrder->consum_type = $consumType;
			$userOrder->amount = $amount;
			$userOrder->balance = $user->balance;
		}
		// 保存
		if (!$userOrder->save()) {
			Utils::throwDbException($userOrder);
		}
		// 保存流水
		self::updateUserBalance($user, BALOP_TYPE_REDUCE, $amount, $consumType, $targetId, $orderId);
	}
	
	public static function saveTmpPayData(\Redis $redis, $tmpKey, $consumType, $data)
	{
		// 判断类型, 处理响应内容
		switch ($consumType) {
			case PAYOP_TYPE_SEND_MOMENT_REDPACKET:
				// 将数据保存进数据库中
				$data['moment_type'] = 2;
				break;
			case PAYOP_TYPE_SEND_CHAT_REDPACKET:
				break;
			case PAYOP_TYPE_REWARD_TASK:
				$data['group_id'] = $data['familyId'];
				unset($data['familyId']);
				break;
		}
		$redis->hMset($tmpKey, $data);
		// 设定时间
		$redis->expire($tmpKey, 601);
		return true;
	}
	
	// 保存临时红包数据
	private static function saveTmpRedpacket($di, $user, $tmpPayData, $payResult)
	{
		$amount = $tmpPayData['amount'];
		$number = $tmpPayData['number'];
		$password = $tmpPayData['password'];
		$startTime =  $tmpPayData['startTime'];
		$describe = $tmpPayData['describe'];
		$type =  $tmpPayData['type'];
		$visible =  $tmpPayData['visible'];
		// $groupID = array_key_exists('groupId'. $tmpPayData) ? $tmpPayData['groupId'] : 0;

		// 保存红包数据
		return DBManager::sendUserRedPacket($di, $user, 0, $visible, $amount, $number, $password, $startTime, $describe, $type, $payResult);
	}
	
	// 保存临时任务数据
	private static function saveTmpRewardTask($di, $user, $tmpPayData, $payChannel, $userOrder)
	{
		$groupId = $tmpPayData['group_id'];
		// 获取家族ID
		$association = DBManager::getAssociationByGroupId($groupId);
		$cover_pic =  $tmpPayData['cover_pic'];
		$cover_thumb =  $tmpPayData['cover_thumb'];
		// 保存红包数据
		$rewardTask = DBManager::saveRewardTask($di, $user->id, $association, $cover_pic, $cover_thumb, $tmpPayData);
		// 发送支付悬赏任务成功的消息
		MessageSender::sendPayRewardTask($di, $user, $rewardTask, $payChannel, $userOrder);
		return $rewardTask;
	}

	// 获取支付渠道的名称
	public static function getPayChannelName($payChannel)
	{
		$payChannelName = '余额';
		switch ($payChannel) {
			case PAY_BY_ALI:
				$payChannelName = '支付宝';
				break;
			case PAY_BY_WX:
				$payChannelName = '微信';
				break;
            case PAY_BY_APPLE:
                $payChannelName = '苹果';
                break;
		}
		return $payChannelName;
	}
	
}
