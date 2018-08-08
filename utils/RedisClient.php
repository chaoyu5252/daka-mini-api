<?php

namespace Fichat\Utils;

/**
 * 一次获取多个Key
 *
 */

define('MHGETALL', <<<LUA
local keys = cjson.decode(ARGV[1])
local result = {}
for idx, key in ipairs(keys) do
	local subData = redis.call('HGETALL', key)
	if (subData) then
		local subData2 = {}
		local k = ""
		for idx, v in pairs(subData) do
			if ((idx + 1) % 2 == 0) then
				k = v
			else
				subData2[k] = v
			end
		end
		result[key] = subData2
	end
end
return cjson.encode(result)
LUA
);


class RedisClient {

    /**
     * 获取所有数据
     */
    public static function hGetAll(\Redis $redis, $key)
    {
        $data = $redis ->hGetAll($key);
        foreach ($data as $key => $value)
        {
            if (!$value) {
                $data[$key] = '';
            }
        }
        return $data;
    }
	
	/**
	 * 获取多个HashSet数据
	 */
	public static function mHgetAll(\Redis $redis, $keys)
	{
		$return = array();
		if ($keys) {
			$return = json_decode($redis->eval(MHGETALL, [json_encode($keys)], 0), true);
		}
		return $return;
	}
    
    /**
     * 获取数据
     */
    public static function hGet(\Redis $redis, $key, $field, $def = '') {
        $value = $redis->hGet($key, $field);
        $value ? $value : $def;
        return $value;
    }
    
    /**
     * 将Keys拆分成已加载和未加载的
     */
    public static function clipLoadKeys(\Redis $redis, $groupId, $ids)
	{
		$return = ['in_cache' => array(), 'no_cache' => array()];
		foreach ($ids as $id)
		{
			$key = RedisClient::rewardTaskKey($groupId, $id);
			
			if ($redis->exists($key)) {
				// 缓存了的Key
				$return['in_cache'][$id] = $key;
			} else {
				// 未缓存的Key
				$return['no_cache'][$id] = $key;
			}
		}
		return $return;
	}

    public static function create($redisConf)
    {
        $redis = new \Redis();
        $redis->connect($redisConf['host'], $redisConf['port']);
        if ($redisConf['password']) {
            if ($redis->auth($redisConf['password']) == false) {
                $redis = null;
            }
        }
        return $redis;
    }

    // 创建红包的Key
    public static function redpack_key($id)
    {
        return "redpack:".$id;
    }
    // 抢过红包的用户KEY
	public static function grab_redpack_users_key($id)
	{
		return "grab_redpack:".$id;
	}
	
	// 抢过红包的用户KEY
	public static function grab_redpack_key($id)
	{
		return "grab_redpack:".$id;
	}
	
	// 红包配额key
	public static function redpack_dist_key($id)
	{
		return "redpack_dist:".$id;
	}

    // 获取红包Redis数据
    public static function redis_redpack($redis, $key)
    {
        $redPack = $redis->hget($key);
        if ($redPack) {
            // 处理数据
            $redPack['user_id'] = intval($redPack['user_id']);
            $redPack['amount'] = intval($redPack['amount']);
            $redPack['number'] = intval($redPack['number']);
            $redPack['visible'] = intval($redPack['visible']);
            $redPack['timestamp'] = intval($redPack['timestamp']);
            return $redPack;
        } else {
            return false;
        }
    }

    /**
     * 创建红包说说key
     *
     * @param $id
     * @return string
     */
    public static function moment_key($id)
    {
        return "moment:" . $id;
    }

    /**
     * 使用微信,支付宝支付红包创建key
     *
     * @param $id
     * @return string
     */
    public static function redPacketTemporaryKey($id)
    {
        return "redPacketTemporaryKey:" . $id;
    }

    /**
     * 使用微信,支付宝支付礼物创建key
     *
     * @param $id
     * @return string
     */
    public static function giveGiftTemporaryKey($id)
    {
        return "giveGiftTemporaryKey:" . $id;
    }

    public static function rankInfoKey()
    {
        return "rank_info";
    }

    public static function redpackRankKey()
    {
        return "redpack_rank";
    }

    public static function userLevRankKey()
    {
        return "userlevel_rank";
    }
    
    public static function worldRankKey()
    {
        return "world_rank";
    }
	
	public static function dakaRankKey()
	{
		return "daka_rank";
	}
    
    // 全服周粉丝
    public static function weekFansKey()
    {
    	return "week_fans:".Utils::getYearWeek();
    }
    
    // 全服周好友
	public static function weekFriendKey()
	{
		return "week_friend:".Utils::getYearWeek();
	}
	
	// 全服周红包
	public static function weekRedPacketKey()
	{
		return "week_redpack:".Utils::getYearWeek();
	}
	
	// 周活跃
	public static function weekActiveKey()
	{
		return "week_active:".Utils::getYearWeek();
	}
		
		// 每日(超级)悬赏
	public static function dayBigReward()
	{
		return "day_reward:".Utils::getDayth();
	}
	
	
	
	// 每日(超级)红包
	public static function dayBigRedpack()
	{
		return "day_redpack:".Utils::getDayth();
	}
	

    public static function userAttrKey()
    {
        return "user_attr";
    }

    public static function associationKey()
    {
        return "association_attr";
    }

    public static function assoicLevRankKey()
    {
        return "assoclevel_rank";
    }

    public static function associationLevKey()
    {
        return "association_attr";
    }
    
    public static function userRewardTaskKey($groupId)
    {
        return "rt@".$groupId;
    }
    
    public static function tmpPayDataKey($orderId)
    {
        return "tmp_pay:".$orderId;
    }
    
    /** 悬赏任务Key ----------------------------------- */
    
    public static function rewardTaskKey($groupId, $taskId)
    {
        return "rt@".$groupId.":".$taskId;
    }
    
    public static function rewardTaskKeyMatchFmt($groupId, $taskId = 0)
    {
    	// 检查是否制定了ID, 如果制定了ID则直接返回Key
    	if ($taskId) {
    	    return Self::rewardTaskKey($groupId, $taskId);
	    } else {
		    return "rt@".$groupId.":*";
	    }
     
    }
    
    public static function getRewardTaskKeyByID(\Redis $redis, $taskId)
    {
        $keys = $redis->keys("rt@*:".$taskId);
        if ($keys) {
        	return $keys[0];
        } else { return false; }
    }
	
    public static function rewardTaskRecordTmpPrex()
    {
    	return "tmp_rtr";
    }
    
	public static function rewardTaskRecordTmpKey($taskId, $tmpId)
	{
		// 前缀
		$prex = Self::rewardTaskRecordTmpPrex();
		// 返回键
		return $prex . "@" . $taskId . ":" . $tmpId;
	}
	
	public static function rewardTaskRecordTmpIncrKey()
	{
		return "rtrecord_tmp_incr";
	}
	
	public static function getTmpRtrId(\Redis $redis)
	{
		$key = Self::rewardTaskRecordTmpIncrKey();
		return (int)$redis->incr($key);
	}
	
	public static function rewardTaskRecordKey($taskId, $uid, $opType, $id)
	{
		return "rtr@".$taskId."#".$uid."_".$opType.":".$id;
	}
	
	public static function associationMemberTitleKey()
	{
		return "assoc_member_titles";
	}
	
	public static function getTaskRecordInfo($key)
	{
		$data = array();
		// $ki=key_info缩写
		$ki1 = explode(":", $key);
		$data['id'] = (int)$ki1[1];
		$ki2 = explode("_", $ki1[0]);
		$data['op_type'] = (int)$ki2[1];
		$ki3 = explode('#', $ki2[0]);
		$data['groupId'] = $ki3[1];
		$ki4 = explode('@', $ki3[0]);
		$data['task_id'] = $ki4[1];
		return $data;
	}
	
	/**
	 * 获取缓存内的数据
	 *
	 */
	public static function matchRedisHashData($redis, $matchStr)
	{
		$keys = $redis->keys($matchStr);
		return RedisClient::mHgetAll($redis, $keys);
	}
	
	// 获取Key的ID
	public static function getKeyId($key)
	{
		$keyInfo = explode(':', $key);
		return (int)$keyInfo[1];
	}

}
