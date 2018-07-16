<?php
namespace Fichat\Models;

use Fichat\Common\DBManager;
use Fichat\Common\RedisManager;
use Fichat\Utils\RedisClient;
use Phalcon\Di;
use Phalcon\Mvc\Model;

class RewardTaskRecord extends Model
{
	public $id;
	public $task_id;
	public $op_type;
	public $uid;
	public $status;
	public $op_time;
	
	public function initialize() {
		$this->belongsTo('uid', __NAMESPACE__ . '\User', 'id', array(
			'alias' => 'user'
		));
		$this->belongsTo('task_id', __NAMESPACE__ . '\RewardTask', 'id', array(
			'alias' => 'rewardTask'
		));
	}
	
	public static function getTmpRecordInfo($tmpkey)
	{
		$tmpKeyInfo = explode(":", $tmpkey);
		$id = (int)$tmpKeyInfo[1];
		$tmpTaskInfo = explode("@", $tmpKeyInfo[0]);
		$taskId = $tmpTaskInfo[1];
		return ['tmp_id' => $id, 'task_id' => $taskId];
	}
	
	// 查询一个用户的信息
	public static function findOne($di, $uid, $taskId, $expire)
	{
		// 检查是否存在该Key
		$redis = RedisClient::create($di->get('config')['redis']);
		// 检查是否有数据
		$keys = $redis->keys('rtr@'.$taskId.'#'.$uid.':*');
		if ($keys) {
			// 找到ID值最大的一个Key
			$maxKey = '';
			$maxId = 0;
			foreach ($keys as $key) {
				$keyInfo = explode(':', $key);
				$id = (int)$keyInfo[1];
				if ( $id > $maxId) {
					$maxId = $id;
					$maxKey = $key;
				}
			}
			// 获取数据
			$data = $redis->hGetAll($redis, $maxKey);
			if ($data) {
				// 设置id和uid
				$data['id'] = $maxId;
			} else {
				$data = Self::findFromMySQL($di, $taskId, $uid, $expire);
			}
		} else {
			$data = Self::findFromMySQL($di, $taskId, $uid, $expire);
		}
		return $data;
	}
	
	// 查找任务记录
	public static function findAll($di, $params)
	{
		// 获取所有的数据
		$records = parent::find($params);
		if (!$records) { return false; }
		
		
		
	}
	
	/** 从MySql中拿取数据 */
	private static function findFromMySQL($di, $taskId, $uid, $expire)
	{
		$data = Self::findFirst([
			"task_id = ".$taskId." AND uid=".$uid,
			"order" => "id desc"
		]);
		if ($data) {
			// 保存赏金任务数据
			return RedisManager::saveRewardTaskRecord($di, $data, $expire);
		} else {
			return false;
		}
	}
	
	
	
}