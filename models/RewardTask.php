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
	public $cover_pic = "";
	public $content = "";
	public $task_amount = 0;
	public $click_price = 0;
	public $share_price = 0;
	public $end_time;
	public $create_time = 0;
	public $click_count = 0;
	public $share_count = 0;
	public $share_join_count = 0;
	public $total_click_count = 0;
	public $total_share_count = 0;
	public $status = 1;
	public $balance = 0;
	
	public function initialize() {
		$this->belongsTo('owner_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
		
		$this->belongsTo('cover_pic', __NAMESPACE__ .'\Files', 'id');
//		$this->hasOne('cover_pic', __NAMESPACE__ .'\Files', 'id', array(
//				'alias' => 'files'
//		));
		
		// 一对多关系
		$this->hasMany("id", __NAMESPACE__.'\RewardTaskRecord', "task_id", array(
			'alias' => 'rewardTaskRecord'
		));
	}
	
}