<?php
namespace Fichat\Library;

use Fichat\Common\ReturnMessageManager;
use Fichat\Models\Friend;
use Fichat\Models\User;
use Fichat\Models\UserOrder;
use Fichat\Proxy\WeixinPay;
use Fichat\Utils\RedisClient;
use Fichat\Utils\Utils;


class RankProcessor {
	
	// 世界榜
	public static function getWorldRank($di)
	{
		try {
			$redis = Utils::getService($di, SERVICE_REDIS);
			$rankMembers = $redis->zRevRange(RedisClient::worldRankKey(), 0, -1, true);
			$memberIds = '';
			foreach ($rankMembers as $memberId => $score)
			{
				$memberIds .= ','.$memberId;
			}
			$data = [];
			if ($memberIds) {
				$memberIds = substr($memberIds, 1);
				$users = User::find("id in (".$memberIds.")");
				foreach ($rankMembers as $memberId => $income) {
					$income = $income ? floatval($income / 100) : 0;
					foreach ($users as $user) {
						if ($user->id = $memberId) {
							$item = [
								'nickname' => $user->nickname,
								'avatar' => $user->wx_avatar,
								'gender' => $user->gender,
								'income' => $income
							];
							
							array_push($data, $item);
							break 1;
						}
					}
				}
				
			}
			
			$redis->close();
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['rank_list' => $data]);
		} catch (\Exception $e) {
			return ReturnMessageManager::buildReturnMessage(ERROR_LOGIC);
		}
	}
	
	public static function getFriendRank($di)
	{
		try {
			$redis = Utils::getService($di, SERVICE_REDIS);
			$gd = Utils::getService($di, SERVICE_GLOBAL_DATA);
			$uid = $gd->uid;
//			$rankMembers = $redis->zRange(RedisClient::worldRankKey(), 0, -1, true);
			// 获取我的好友
			$friends = Friend::find("user_id = ".$uid);
			$friendIds = $uid;
			$rankMembers = [];
			$rankKey = RedisClient::worldRankKey();
			foreach ($friends as $friend) {
				$friendIds .= ','.$friend->friend_id;
				$score = $redis->zScore($rankKey, $friend->friend_id);
				array_push($rankMembers, [$score, $friend->friend_id]);
			}
			$score = $redis->zScore($rankKey, $uid);
			array_push($rankMembers, [$score, $uid]);
			
			$users = User::find("id in (".$friendIds.")");
			usort($rankMembers, function ($item1, $item2) {
				if ($item1[0] > $item2[0]) {
					return 0;
				} else {
					return 1;
				}
			});
			$data = [];
			foreach ($rankMembers as $rankMember) {
				foreach ($users as $user) {
					if ($user->id == $rankMember[1]) {
						$income = $rankMember[0] ? floatval($rankMember[0] /  100) : 0;
						array_push($data, [
							'nickname' => $user->nickname,
							'avatar' => $user->wx_avatar,
							'gender' => $user->gender,
							'income' => $income
						]);
						break 1;
					}
				}
			}
			return ReturnMessageManager::buildReturnMessage(ERROR_SUCCESS, ['rank_list' => $data]);
		} catch (\Exception $e) {
			var_dump($e);
			return ReturnMessageManager::buildReturnMessage(ERROR_LOGIC);
		}
	}
	
	
}


