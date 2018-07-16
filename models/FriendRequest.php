<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class FriendRequest extends Model {
	public $id;
	public $user_id;
	public $friend_id;
	public $status;
	public $message;
	public $is_new;
	public $create_time;
	
	public function initialize(){
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
		
		$this->belongsTo('friend_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'friend'
		));
	}

}