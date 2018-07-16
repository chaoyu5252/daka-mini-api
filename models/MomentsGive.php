<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class MomentsGive extends Model
{
	public $id;
	public $user_id;
	public $target_id;
	public $moments_id;
	public $amount;
	public $give_time;
	
	public function initialize() {
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
	}
}