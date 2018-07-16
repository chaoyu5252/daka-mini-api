<?php
namespace Fichat\Models;

use Fichat\Common\RedisManager;
use Fichat\Common\ReturnMessageManager;
use Fichat\Utils\RedisClient;
use Phalcon\Mvc\Model;

class RewardTask extends Model
{
	public $id;
	public $owner_id = 0;
	public $group_id = 0;
	public $cover_pic = "";
	public $cover_thumb = "";
	public $title = "";
	public $content = "";
	public $reward_amount = 0;
	public $click_reward = 0;
	public $share_reward = 0;
	public $link = "";
	public $type = 1;
	public $end_time;
	public $create_time = 0;
	public $click_count = 0;
	public $share_count = 0;
	public $total_click_count = 0;
	public $total_share_count = 0;
	public $status = 1;
	public $balance = 0;
	public $parent_id = 0;
	public $coms_percent = 0;
	public $task_income = 0;
	
	public function initialize() {
		$this->belongsTo('owner_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
		// 一对多关系
		$this->hasMany("id", __NAMESPACE__.'\RewardTaskRecord', "task_id", array(
			'alias' => 'rewardTaskRecord'
		));
	}
	
	/**
	 * 重写原有find
	 * @params di:  Di
	 * @params uid: 用户ID
	 * @params qc:  查看条件
	 *
	 */
	
	/**
	 * 获取用户所有任务
	 *
	 */
	public static function getUserRewardTasks(\Redis $redis, $groupId)
	{
		// 构建Key
		$key = RedisClient::userRewardTaskKey($groupId);
		$rewardTasks= $redis->hGetAll($key);
		// UserRewardTask Exist Ever
		if ($rewardTasks) {         // Exist
			// 反序列化ids
			$tasks_ids = unserialize($rewardTasks['ids']);
			// 获取加载了的和未加载的Key
			$rewardTaskKeys = RedisClient::clipLoadKeys($redis, $groupId, array_keys($tasks_ids));
			// 获取所有已经加载了的Key的数据
			$inCacheTasks = RedisClient::mHgetAll($redis, array_values($rewardTaskKeys['in_cache']));
			$rewardTaskData1 = array();
			// 将Redis中的数据拿出来组装, 并返回
			foreach ($inCacheTasks as $key => $task) {
				$task['id'] = RedisManager::getIdByRewardKey($key);
				array_push($rewardTaskData1, $task);
			}
			
			// 从MySQL中加载不在Redis中的数据
			$rewardTaskData2 = array();
			if ($rewardTaskKeys['no_cache']) {
				$rewardTaskData2 = DBManager::getRewardTasks($rewardTaskKeys['no_cache']);
				foreach ($rewardTaskData2 as $noCacheTask) {
					// 存储悬赏任务缓存
					RedisManager::saveRewardTask($redis, $noCacheTask);
				}
			}
			// 合并
			return array_merge($rewardTaskData1, $rewardTaskData2);
		} else {                    // Not Exist
			// 获取MySQL中的用户悬赏任务数据
			return Self::createUserTaskDATA($redis, $groupId);
		}
	}
	
	// 查询一个用户的信息
	public static function findOne($di, $id, $groupId = 0)
	{
		// 构建Redis客户端对象
		$redisConf = $di->get('config')['redis'];
		$redis = RedisClient::create($redisConf);
		// 构建任务Key
		if ($groupId) {
			$key = RedisClient::rewardTaskKey($groupId, $id);
		} else {
			$key = RedisClient::getRewardTaskKeyByID($redis, $id);
		}
		if ($key) {
			// 获取数据
			$data = $redis->hGetAll($key);
			if ($data) {
				// 设置id和uid
				$data['id'] = $id;
				$data = Self::procParentRewardTask($di, $data);
				// 关闭redis连接
                $redis->close();
                return Self::checkUpdateStatus($data);
			} else {
                return Self::procfindNoCache($di, $redis, $id);
			}
		} else {
		    return Self::procfindNoCache($di, $redis, $id);
		}
	}

	// 删除一条记录
	public static function deleteOne($di, $uid, $rewardTaskData)
    {
    	$id = $rewardTaskData['id'];
        // 构建任务Key
        $key = RedisClient::rewardTaskKey($uid, $id);

        // 构建Redis客户端对象
        $redisConf = $di->get('config')['redis'];
        $redis = RedisClient::create($redisConf);

        // 更新状态
        $redis->hSet($key, 'status', -1);
        /** ---- 将数据推入到更新队列中 ---------*/
	    $rewardTask = RewardTask::fromArray($rewardTaskData);
	    $rewardTask->status = -1;
        /** --------------------------------- */
        if (!$rewardTask->save()) {
        	$rewardTaskData['status'] = -1;
        	RedisManager::saveRewardTask($rewardTaskData);
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * 检查并更新任务状态
     *
     */
    public static function checkUpdateStatus($rewardTaskData)
    {
        $now = time();
        if (($rewardTaskData['end_time'] < $now) || ($rewardTaskData['balance'] == 0)) {
	        $rewardTaskData['status'] = 2;
        }
        return $rewardTaskData;
    }
	
	/**
	 * 检查创建悬赏任务的必要参数
	 * 需要检查的参数:
	 * title':              标题为5-18个字
	 * 'content':           内容的上限为120字
	 * 'reward_amount':     总金额, 不能超过自己的余额
	 * 'click_reward':      点击金额, 浮点数, 不能为空, 点击金额不能少于reward_amount / assoc_member_count / 3
	 * 'share_reward':      分享金额, 浮点数, 不能为空
	 * 'link':              地址, 不能为空
	 * 'end_time:           时间戳, 必能小于当前时间
	 */
    public static function checkCreateRewardTaskPrams($now)
    {
    	$return = array();
    	$titleLength = mb_strlen(trim($_POST['title']), 'UTF-8');
	    if ($titleLength < 5 || $titleLength > 18) { return ReturnMessageManager::buildReturnMessage('E0268'); }
	    $contentLength = mb_strlen(trim($_POST['content']), 'UTF-8');
	    if ($contentLength == 0) { return ReturnMessageManager::buildReturnMessage('E0269'); }
        if ($contentLength > 120) { return ReturnMessageManager::buildReturnMessage('E0328'); }
	    if (!(float)$_POST['reward_amount']) { return ReturnMessageManager::buildReturnMessage('E0270'); }
	    if (!(float)$_POST['click_reward']) { return ReturnMessageManager::buildReturnMessage('E0271'); }
	    if (!(float)$_POST['share_reward']) { return ReturnMessageManager::buildReturnMessage('E0272'); }
	    $rewardAmount = (float)$_POST['reward_amount'];
	    $clickReward = (float)$_POST['click_reward'];
        $shareReward = (float)$_POST['share_reward'];
//	    if ($clickReward > ($rewardAmount / 2)) {
//	    	return ReturnMessageManager::buildReturnMessage('E0273');
//	    }
	    // 单次点击金额 + 分享金额 不能大于 悬赏的总金额
	    if ($rewardAmount < ($shareReward + $clickReward)) {
	    	return ReturnMessageManager::buildReturnMessage('E0279');
	    }
    	if (!$_POST['link']) { $return = ReturnMessageManager::buildReturnMessage('E0274', null); }
	    if (!preg_match("/^https?:\/\//", trim($_POST['link']))) {
		    return ReturnMessageManager::buildReturnMessage('E0285');
	    }
	    $end_ts = (int)$_POST['end_time'];
	    if (!$end_ts) { $return = ReturnMessageManager::buildReturnMessage('E0275', null); }
	    // 结束时间不能小于从发布时间到现在的1天
	    if ($end_ts < ($now + 86399)) { $return = ReturnMessageManager::buildReturnMessage('E0276', null); }
    	return $return;
    }
    
    public static function fromArray($rewardTaskArrData)
    {
        $rewardTask = new RewardTask();
	    $rewardTask->id = $rewardTaskArrData['id'];
	    $rewardTask->create_time = $rewardTaskArrData['create_time'];
	    $rewardTask->end_time = $rewardTaskArrData['end_time'];
	    $rewardTask->title = $rewardTaskArrData['title'];
	    $rewardTask->content = $rewardTaskArrData['content'];
	    $rewardTask->owner_id = $rewardTaskArrData['owner_id'];
	    $rewardTask->reward_amount = $rewardTaskArrData['reward_amount'];
	    $rewardTask->click_count = $rewardTaskArrData['click_count'];
	    $rewardTask->share_count = $rewardTaskArrData['share_count'];
	    $rewardTask->click_reward = $rewardTaskArrData['click_reward'];
	    $rewardTask->share_reward = $rewardTaskArrData['share_reward'];
	    $rewardTask->cover_pic = $rewardTaskArrData['cover_pic'];
	    $rewardTask->cover_thumb = $rewardTaskArrData['cover_thumb'];
	    $rewardTask->link = $rewardTaskArrData['link'];
	    $rewardTask->type = $rewardTaskArrData['type'];
	    $rewardTask->balance = $rewardTaskArrData['balance'];
	    $rewardTask->group_id = $rewardTaskArrData['group_id'];
	    $rewardTask->status = $rewardTaskArrData['status'];
	    $rewardTask->parent_id = $rewardTaskArrData['parent_id'];
	    $rewardTask->coms_percent = $rewardTaskArrData['coms_percent'];
	    $rewardTask->task_income = $rewardTaskArrData['task_income'];
	    $rewardTask->total_click_count = $rewardTaskArrData['total_click_count'];
	    $rewardTask->total_share_count = $rewardTaskArrData['total_share_count'];
	    return $rewardTask;
    }
    
    
    public static function saveOpedRewardTask($taskId)
    {
    
    }
	
	/** 从MySQL中拉取数据返回, 并保存在Redis中 */
	private static function createUserTaskDATA(\Redis $redis, $uid)
	{
		// 获取MySQL中的用户悬赏任务数据
		$data = DBManager::getUserRewardTasks($uid);
		$ids = array();
		$tasks_ids = array();
		// 初始化用户所有悬赏任务的数据
		foreach ($data as $rewardTask) {
			// 操作次数
			$id = $rewardTask['id'];
			$opCount = $rewardTask['click_count'] + $rewardTask['share_count'];
			// 存储悬赏任务缓存
			RedisManager::saveRewardTask($redis, $rewardTask);
			$ids[$id] = $opCount;
			array_push($tasks_ids, $id);
		}
		// ID操作索引
		$ids = serialize($ids);
		$up_ts = microtime();
		// MD5值
		$md5 = md5($ids);
		$userTaskKey = RedisClient::userRewardTaskKey($uid);
		// 存储
		$redis->eval(CHECK_SAVE_USER_REWARD, [$userTaskKey, $ids, $up_ts, $md5], 1);
		// 返回结果
		return $data;
	}
	
	/**
	 * 如果父任务存在, 用父任务的基础数据替换当前任务的(当前任务也没有这些数据)
	 *
	 */
	private static function procParentRewardTask($di, $data)
    {
        if ($data['parent_id'] != 0) {
            $parentData = self::findOne($di, $data['parent_id']);
            $data['title'] = $parentData['title'];
            $data['content'] = $parentData['content'];
            $data['cover_pic'] = $parentData['cover_pic'];
            $data['cover_thumb'] = $parentData['cover_thumb'];
            $data['link'] = $parentData['link'];
            $data['balance'] = $parentData['balance'];
	        $data['reward_amount'] = $parentData['reward_amount'];
	        $data['click_reward'] = $parentData['click_reward'];
	        $data['share_reward'] = $parentData['share_reward'];
            $data['status'] = $parentData['status'];
        }
        return $data;
    }

    /**
     * 从Mysql中拉取没有在缓存中的数据
     *
     */
    private static function procfindNoCache($di, $redis, $id)
    {
        $data = Self::findFirst("id = ".$id);
        if ($data) {
            $data = $data->toArray();
            // 如果不存在, 就调用
            $data = Self::procParentRewardTask($di, $data);
            // 保存赏金任务数据
            RedisManager::saveRewardTask($redis, $data);
        } else {
            return false;
        }
        $redis->close();
        return Self::checkUpdateStatus($data);
    }
	
	
	
}