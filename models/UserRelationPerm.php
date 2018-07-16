<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class UserRelationPerm extends Model {
	public $id;
	public $user_id;
	public $target_id;
	public $rtype = 0;
	public $is_look;
	
	public function initialize()
	{
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
			'alias' => 'user'
		));
		
		$this->belongsTo('target_id', __NAMESPACE__ . '\User', 'id', array(
			'alias' => 'look_user'
		));
	}
}