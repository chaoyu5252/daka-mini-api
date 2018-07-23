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
	public $count = 0;
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
	
}