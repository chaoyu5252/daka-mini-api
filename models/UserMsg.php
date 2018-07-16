<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class UserMsg extends Model {
	public $id;
	public $user_id;
	public $from_id;
	public $type;
	public $ext_params;
	public $status;
	public $update_time;
	
	public function initialize()
	{
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
			'alias' => 'user'
		));
		
		$this->belongsTo('from_id', __NAMESPACE__ . '\User', 'id', array(
			'alias' => 'from_user'
		));
	}
}