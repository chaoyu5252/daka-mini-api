<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Friend extends Model {
	public $id;
	public $user_id;
	public $friend_id;
	public $intimacy;
	public $confirm;
	public $disturb;
	public $is_look;
	public $forbid_look;
	
	public function initialize(){
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
		
		$this->belongsTo('friend_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'friend'
		));
	}

}