<?php
namespace Fichat\Utils;

use Fichat\Common\DBManager;
use Fichat\Proxy\HxChatProxy;

define('UMSG_TYPE_LOGIN', 1);
define('UMSG_TYPE_REG', 2);
define('UMSG_TYPE_TICKE_FROM_FAMILY', 3);
define('UMSG_TYPE_NEWFANS', 4);
define('UMSG_TYPE_REMFRD', 5);
define('UMSG_TYPE_MOMENTS_REPLY', 6);
define('UMSG_TYPE_MOMENTS_LIKE', 7);
define('UMSG_TYPE_MOMENTS_REPLY_LIKE', 8);
define('UMSG_TYPE_MOMENTS_REPLY_REPLY', 9);
define('UMSG_TYPE_FAMILY_ADMIN', 10);
define('UMSG_TYPE_PASS_FRIEND', 11);
define('UMSG_TYPE_PASS_FAMILY', 12);
define('UMSG_TYPE_REPORT', 13);
define('UMSG_TYPE_UP_LEVEL', 14);


class MessageSender {
	
	
	/** 用户信息 */
	
	// 1. 发送登录欢迎消息
	public static function sendUserWelcomeForLogin($di, $user)
	{
		$msg = "欢迎回到大咖社";
		$extParams = [
			'content' => $msg
		];
		// 保存用户消息
		return DBManager::sendUserMessage($di, $user->id, 0, UMSG_TYPE_LOGIN, $extParams);
	}
	
	// 2. 发送注册消息
	public static function sendUserWelcomeForReg($di, $user)
	{
		$msg = "欢迎来到大咖社";
		$extParams = [
			'content' => $msg
		];
		// 保存用户消息
		return DBManager::sendUserMessage($di, $user->id, 0, UMSG_TYPE_REG, $extParams);
	}
	
	// 3. 移除家族
	public static function sendUserTickFromAssociation($di, $target, $association)
	{
		$msg = '您已被移除'.$association->nickname.'家族......';
		$extParams = [
			'group_id' => $association->group_id,
			'familyAvatar' => OssApi::procOssPic($association->assoc_avatar),
			'familyName' => $association->nickname,
            'familyLevel' => $association->level,
			'content' => $msg
		];
		// 保存用户消息
		return DBManager::sendUserMessage($di, $target->id, $association->group_id, UMSG_TYPE_TICKE_FROM_FAMILY, $extParams);
	}
	
	// 4. 粉丝关注
	public static function sendUserNewFans($di, $user, $target)
	{
		$msg = $user->nickname.'关注了您！';
		$extParams = [
			'userId' => $user->id,
			'userAvatar' => OssApi::procOssPic($user->user_avatar),
			'nickname' => $user->nickname,
            'userLevel' => $user->level,
			'content' => $msg
		];
		// 保存用户消息
		return DBManager::sendUserMessage($di, $target->id, $user->id, UMSG_TYPE_NEWFANS, $extParams);
	}
	
	// 4. 用户消息: 新粉丝(S) | 产生多条记录
	public static function sendUserNewMutiFans($di, $user, $targets)
	{
		$uid = $user->id;
		$ret = true;
		foreach($targets as $target) {
			$msg = $target->nickname.'成了你的粉丝';
			$extParams = [
				'userId' => $user->id,
				'userAvatar' => OssApi::procOssPic($user->user_avatar),
				'nickname' => $user->nickname,
                'userLevel' => $user->level,
				'content' => $msg
			];
			if (!DBManager::sendUserMessages($di, $target->id, 0, UMSG_TYPE_NEWFANS, $extParams))
			{
				$ret = false;
				break;
			}
		}
		// 保存用户消息
		if ($ret) {
			$hxConfig = $di->get('config')['hxConfig'];
			// 发送透传消息
			HxChatProxy::sendSilenceMessage([$uid], 'uMessage', $hxConfig);
		}
		return $ret;
	}
	
	// 5. 好友移除
	public static function sendUserRemFriend($di, $user, $target)
	{
		$msg ='很遗憾，'.$user->nickname.'与您的友谊小船已沉入大海......';
		$extParams = [
			'userId' => $user->id,
			'userAvatar' => OssApi::procOssPic($user->user_avatar),
			'nickname' => $user->nickname,
            'userLevel' => $user->level,
			'content' => $msg
		];
		// 保存用户消息
		return DBManager::sendUserMessage($di, $target->id, $user->id, UMSG_TYPE_REMFRD, $extParams);
	}
	
	// 6. 说说评论/ 评论动态的评论
	public static function sendUserMomentsReply($di, $user, $replyUser, $momentId, $parentId) {
        $msg = $parentId == 0 ? $user->nickname.'评论了您的动态。' : $user->nickname.'评论了您的评论。';
        $msgType = $parentId == 0 ? UMSG_TYPE_MOMENTS_REPLY : UMSG_TYPE_MOMENTS_REPLY_REPLY;
		$extParams = [
			'userId' => $user->id,
			'userAvatar' => OssApi::procOssPic($user->user_avatar),
			'nickname' => $user->nickname,
            'userLevel' => $user->level,
			'content' => $msg,
			'momentId' => $momentId
		];
		// 保存用户消息
		return DBManager::sendUserMessage($di, $replyUser->id, $user->id, $msgType, $extParams);
	}
	
	// 7. 用户消息: 说说点赞
	public static function sendUserMomentsLike($di, $user, $likeUser, $momentId) {
		$msg = $likeUser->nickname.'点赞了您的动态。';
		$extParams = [
			'userId' => $likeUser->id,
			'userAvatar' => OssApi::procOssPic($likeUser->user_avatar),
			'nickname' => $likeUser->nickname,
            'userLevel' => $likeUser->level,
			'content' => $msg,
			'momentId' => $momentId
		];
		// 保存用户消息
		return DBManager::sendUserMessage($di, $user->id, $likeUser->id,UMSG_TYPE_MOMENTS_LIKE, $extParams);
	}

	// 8.用户消息：评论点赞
    public static function sendUserMomentsReplyLike($di, $user, $likeUser, $replyId) {
        $msg = $likeUser->nickname.'赞了你的评论';
        $extParams = [
            'userId' => $likeUser->id,
            'userAvatar' => OssApi::procOssPic($likeUser->user_avatar),
            'nickname' => $likeUser->nickname,
            'userLevel' => $likeUser->level,
            'content' => $msg,
            'replyId' => $replyId
        ];
        // 保存用户消息
        return DBManager::sendUserMessage($di, $user->id, $likeUser->id,UMSG_TYPE_MOMENTS_REPLY_LIKE, $extParams);
    }

    // 被设置为家族管理员
    public static function sendUserSetFamilyAdmin($di, $user, $family) {
	    $msg = "您已成为".$family->nickname."家族的管理员";
        $extParams = [
            'group_id' => $family->group_id,
            'familyAvatar' => OssApi::procOssPic($family->assoc_avatar),
            'familyName' => $family->nickname,
            'familyLevel' => $family->level,
            'content' => $msg
        ];
        // 保存用户消息
        return DBManager::sendUserMessage($di, $user->id, $family->group_id,UMSG_TYPE_FAMILY_ADMIN, $extParams);
    }

    // 通过好友申请
    public static function sendUserPassFriend($di, $user, $friendId) {
        $msg = $user->nickname."已通过您的好友申请，快去与TA共建友谊的巨轮吧！";
        $extParams = [
            'userId' => $user->id,
            'userAvatar' => OssApi::procOssPic($user->user_avatar),
            'nickname' => $user->nickname,
            'userLevel' => $user->level,
            'content' => $msg
        ];
        // 保存用户消息
        return DBManager::sendUserMessage($di, $friendId, $user->id,UMSG_TYPE_PASS_FRIEND, $extParams);
    }

    // 通过家族申请
    public static function sendUserPassFamily($di, $userId, $family) {
        $msg = "您已加入".$family->nickname."家族！";
        $extParams = [
            'group_id' => $family->group_id,
            'familyAvatar' => OssApi::procOssPic($family->assoc_avatar),
            'familyName' => $family->nickname,
            'familyLevel' => $family->level,
            'content' => $msg
        ];
        // 保存用户消息
        return DBManager::sendUserMessage($di, $userId, $family->group_id,UMSG_TYPE_PASS_FAMILY, $extParams);
    }

    // 举报
    public static function sendUserReport($di, $userId) {
        $msg = "我们已收到您的举报投诉，我们将在第一时间进行查实，感谢您的支持！";
        $extParams = [
            'adminAvatar' => 'https://dakaapp-avatar.oss-cn-beijing.aliyuncs.com/default_admin_avatar.png',
            'content' => $msg
        ];
        // 保存用户消息
        return DBManager::sendUserMessage($di, $userId, 0,UMSG_TYPE_REPORT, $extParams);
    }

    // 用户升级
    public static function sendUserUpLevel($di, $user) {
	    $msg = "恭喜您升级啦！您当前的个人等级为".$user->level."级，等级越高就能解锁更多特权哦~查看等级说明";
        $extParams = [
            'adminAvatar' => 'https://dakaapp-avatar.oss-cn-beijing.aliyuncs.com/default_admin_avatar.png',
            'currentLevel' => $user->level,
            'content' => $msg
        ];
        // 保存用户消息
        return DBManager::sendUserMessage($di, $user->id, 0,UMSG_TYPE_UP_LEVEL, $extParams);
    }
	
	/**
	 * 用户透传消息
	 *
	 */
	
	// 发送升级透传扩展消息
	public static function sendUserUpLevelMessage($di, $user, $newLevFrdNum, $newLevAssocNum, $newLevAttenNum)
	{
		$extMessage = [
			'level' => $user->level,
			'friendNum' => $newLevFrdNum,
			'familyNum' => $newLevAssocNum,
            'attentionNum' => $newLevAttenNum
		];
		$hxConfig = $di->get('config')['hxConfig'];
		HxChatProxy::sendSilenceExtMessage([$user->id],'uplevel', $extMessage, $hxConfig);
	}
	
	// 3. 家族申请消息
	public static function sendApplyAssociation($di, $user, $group, $adminIds, $applyMessage)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		// 发送环信信息
		HxChatProxy::sendExtMessages(
			'admin',
			$adminIds,
			$user->nickname.'申请加入<<'.$group->nickname.'>>家族',
			[
				'type' => 3,
				'applyMessage' => $applyMessage,
				'userId' => $user->id,
				'userAvatar' => OssApi::procOssPic($user->user_avatar),
				'nickname' => $user->nickname,
				'familyName' => $group->nickname,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	// 4. 被踢出家族通知
//	public static function sendTickFromAssociation($di, $target, $association)
//	{
//		$hxConfig = $di->get('config')['hxConfig'];
//		$message = '你已被管理员从家族['.$association->nickname.']中踢出';
//		HxChatProxy::sendExtMessages(
//			'admin',
//			$target->id,
//			$message,
//			[
//				'type' => 4,
//				'group_id' => $association->group_id,
//				'familyAvatar' => OssApi::procOssPic($association->assoc_avatar),
//				'familyName' => $association->nickname,
//				'time' => date('Y-m-d H:i:s')
//			],
//			$hxConfig);
//	}
	
	// 5. 新增粉丝通知
//	public static function sendNewFans($di, $user, $target)
//	{
//		$hxConfig = $di->get('config')['hxConfig'];
//		$message = $target->nickname.'成了你的粉丝';
//		HxChatProxy::sendExtMessages(
//			'admin',
//			$target->id,
//			$message,
//			[
//				'type' => 5,
//				'userId' => $user->id,
//				'userAvatar' => OssApi::procOssPic($user->user_avatar),
//				'nickname' => $user->nickname,
//				'time' => date('Y-m-d H:i:s')
//			],
//			$hxConfig);
//	}
	
	// 6. 好友申请消息
	public static function sendApplyFriend($di, $user, $target, $requestId, $applyMessage)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		// 发送环信信息
		HxChatProxy::sendExtMessages(
			'admin',
			$target->id,
			$user->nickname.'申请成为你的好友',
			[
				'type' => '6',
				'userId' => $user->id,
				'userAvatar' => OssApi::procOssPic($user->user_avatar),
				'nickname' => $user->nickname,
				'requestId' => $requestId,
				'applyMessage' => $applyMessage,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	// 7. 好友移除
	public static function sendRemFriendByUser($di, $user, $target)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		$message ='亲爱的"'.$target->nickname.'"你和"'.$user->nickname.'"友谊的小船翻了, 翻了, 了...';
		HxChatProxy::sendExtMessages(
			$user->id,
			$target->id,
			$message,
			[
				'type' => 14,
				'userId' => $user->id,
				'userAvatar' => OssApi::procOssPic($user->user_avatar),
				'nickname' => $user->nickname,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	// 8. 红包退还通知
	public static function sendReturnRedpacket($di, $user, $redpacketId, $redPacketBalance, $orderId)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		// 发送环信信息
		HxChatProxy::sendExtMessages(
			'admin',
			$user->id,
			'红包退还通知',
			[
				'type' => 8,
				'payType' => '退回零钱',
				'amount' => $redPacketBalance,
				'redPacketId' => $redpacketId,
                'orderId' => $orderId,
				'payFor' => '红包24小时未被领取',
				'remark' => '未领取红包，24小时后发起退款',
				'payTime' => date('Y-m-d H:i:s'),
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	// 9. 红包支付通知
	public static function sendPayRedpacket($di, $user, $redpacket, $payBy, $userOrder)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		$payBy = KakaPay::getPayChannelName($payBy).'支付';
		$orderId = $userOrder->order_num;
		$payTime = date('Y-m-d H:i:s', $userOrder->create_date);
		HxChatProxy::sendExtMessages(
			'admin',
			$user->id,
			'红包支付通知',
			[
				'type' => 9,
				'amount' => number_format(floatval($redpacket->amount), 2, '.', ''),
				'payType' => $payBy,
				'payFor' => '发送红包',
				'orderId' => $orderId,
				'remark' => '了解完整零钱收支明细,可在我的钱包-收支明细中查看.',
				'payTime' => $payTime,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	// 10. 悬赏任务到期余额退还
	public static function sendReturnRewardTask($di, $user, $rewardTask, $userOrder)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		$orderId = $userOrder['order_id'];
		$payTime = date('Y-m-d H:i:s', $userOrder['timestamp']);
		HxChatProxy::sendExtMessages(
			'admin',
			$user->id,
			'悬赏任务退款通知',
			[
				'type' => 10,
				'amount' => number_format(floatval($rewardTask->balance), 2, '.', ''),
				'payType' => '退回零钱',
				'payFor' => '悬赏任务截止,剩余赏金未被领取',
				'orderId' => $orderId,
				'remark' => '了解完整零钱收支明细,可在我的钱包-收支明细中查看.',
				'payTime' => $payTime,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	
	// 11. 悬赏任务支付通知
	public static function sendPayRewardTask($di, $user, $rewardTask, $payBy, $userOrder)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		$payBy = KakaPay::getPayChannelName($payBy).'支付';
		$orderId = $userOrder->order_num;
		$payTime = date('Y-m-d H:i:s', $userOrder->create_date);
		HxChatProxy::sendExtMessages(
			'admin',
			$user->id,
			'悬赏任务支付通知',
			[
				'type' => 11,
				'amount' => number_format(floatval($rewardTask->reward_amount), 2, '.', ''),
				'payType' => $payBy,
				'payFor' => '发布悬赏任务, 支付赏金',
				'orderId' => $orderId, 'balance' => '账户余额：' . $user->balance, 'remark' => '悬赏任务支付成功', 'time' => date('Y-m-d H:i:s'),
				'remark' => '了解完整零钱收支明细,可在我的钱包-收支明细中查看.',
				'payTime' => $payTime,
				'time' => date('Y-m-d H:i:s')
				
			],
			$hxConfig
		);
	}
	
	// 10. 系统任务奖励结算
	
	
	// 12. 充值成功通知
	public static function sendRechargeSucc($di, $user, $amount, $payBy, $userOrder)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		$payBy = KakaPay::getPayChannelName($payBy).'充值';
		$orderId = $userOrder->order_num;
		$payTime = date('Y-m-d H:i:s', $userOrder->create_date);
		HxChatProxy::sendExtMessages(
			'admin',
			$user->id,
			'充值成功通知',
			[
				'type' => 12,
				'amount' => number_format(floatval($amount), 2, '.', ''),
				'orderId' => $orderId,
				'payType' => $payBy,
				'payFor' => '用户充值',
				'remark' => '了解完整零钱收支明细,可在我的钱包-收支明细中查看.',
				'payTime' => $payTime,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	// 13. 提现成功通知
	public static function sendTakeSucc($di, $user, $amount, $payBy, $serviceCharge, $userOrder)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		$payBy = KakaPay::getPayChannelName($payBy).'提现';
		$orderId = $userOrder->order_num;
		$payTime = date('Y-m-d H:i:s', $userOrder->create_date);
		HxChatProxy::sendExtMessages(
			'admin',
			$user->id,
			'提现成功通知',
			[
				'type' => 13,
				'amount' => number_format(floatval($amount), 2, '.', ''),
				'orderId' => $orderId,
				'serviceCharge' => $serviceCharge,
				'payType' => $payBy,
				'remark' => '了解完整零钱收支明细,可在我的钱包-收支明细中查看.',
				'payTime' => $payTime,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}
	
	// 14. 邀请加入家族消息
	public static function sendInvitJoinAssociation($di, $user, $group, $invitUsers)
	{
		$hxConfig = $di->get('config')['hxConfig'];
		// 发送环信信息
		HxChatProxy::sendExtMessages(
			'admin',
			$invitUsers,
			$user->nickname.'邀请您加入<<'.$group->nickname.'>>家族',
			[
				'type' => 14,
				'userId' => $user->id,
				'userAvatar' => OssApi::procOssPic($user->user_avatar),
				'nickname' => $user->nickname,
				'familyName' => $group->nickname,
				'time' => date('Y-m-d H:i:s')
			],
			$hxConfig
		);
	}

	// 15. 兑换咖米成功
    public static function sendExchangeKaMiSuccess($di, $user, $amount, $payBy, $record)
    {
        $hxConfig = $di->get('config')['hxConfig'];
        $payBy = KakaPay::getPayChannelName($payBy).'充值';
        $orderId = $record->code;
        $payTime = date('Y-m-d H:i:s', $record->create_time);
        HxChatProxy::sendExtMessages(
            'admin',
            $user->id,
            '兑换咖米成功通知',
            [
                'type' => 15,
                'amount' => $amount,
                'orderId' => $orderId,
                'payType' => $payBy,
                'payFor' => '用户兑换咖米',
                'remark' => '了解完整零钱收支明细,可在我的钱包-收支明细中查看.',
                'payTime' => $payTime,
                'time' => date('Y-m-d H:i:s')
            ],
            $hxConfig
        );
    }

	// 登录欢迎语
	public static function sendLoginWelcome($di, $user) {
        $msg = "欢迎您再次回到大咖社！\n快去看看关注的人有什么动态更新吧~\n在大咖社，与大咖同行！";
        $hxConfig = $di->get('config')['hxConfig'];
        // 发送环信信息
        HxChatProxy::sendExtMessages(
            'admin',
            $user->id,
            '欢迎您再次回到大咖社！',
            [
                'type' => 1,
                'content' => $msg,
                'time' => date('Y-m-d H:i:s')
            ],
            $hxConfig
        );
    }

    // 注册欢迎语
	public static function sendRegisterWelcome($di, $user) {
        $msg = "        您好，欢迎来到大咖社！《大咖社》是专门为潮人打造的一款简单、时尚的互动软件。在这里您可以：\n    ·关注喜爱的大咖，与大咖零距离互动；\n    ·广交好友，展示自我风采，做与众不同的你；\n    ·成立自己的家族，让关注您的人齐聚一堂；\n    ·关注更多精彩内容，红包抢到手软；\n    ·发放红包任务，让家族的人气暴涨；\n    ·发布特殊动态，得到更多人的关注，成为最耀眼的大咖。\n        希望有大咖社的陪伴会让您的生活更加愉快、精彩，关注我们的公众号（dakashe2018）可获得更多关于大咖社的咨询哦！在大咖社，与大咖同行！";
        $hxConfig = $di->get('config')['hxConfig'];
        // 发送环信信息
        HxChatProxy::sendExtMessages(
            'admin',
            $user->id,
            '欢迎您来到大咖社！',
            [
                'type' => 2,
                'content' => $msg,
                'time' => date('Y-m-d H:i:s')
            ],
            $hxConfig
        );
    }
	
	
	
	
}