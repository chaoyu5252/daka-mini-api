<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class LoginToken extends Model {
	public $id;
	public $user_id;
	public $token;
	
	public function initialize()
	{
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'User'
		));
	}
}