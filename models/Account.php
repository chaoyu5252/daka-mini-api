<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Account extends Model {
	public $id;
	public $uid;
	public $openid;
	public $phone;
	public $password;
	public $pay_password;
	public $status;
	public $create_time;

	public function initialize()
	{
		$this->belongsTo('id', __NAMESPACE__ . '\User', 'account_id', array(
				'alias' => 'user'
		));
	}
}