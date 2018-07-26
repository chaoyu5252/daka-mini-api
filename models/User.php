<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class User extends Model {
	public $id;
	public $phone = '';
	public $nickname = '';
	public $gender = 1;
    public $wx_avatar = '';
	public $level = 1;
	public $exp = 0;
	public $balance = 0;
	public $task_income = 0;
	public $diamond = 0;
	public $birthday = '1993-12-26';
	public $email = '';
	public $name = '';
	public $session_key = '';
	public $token = '';
	public $token_sign_time = 0;
	public $id_code = '';
	public $create_time;
	public $update_time;

	public function initialize() {
		$this->belongsTo('account_id', __NAMESPACE__ . '\Account', 'id', array(
				'alias' => 'account'
		));
		
		$this->hasMany('id', __NAMESPACE__ . '\Friend', 'user_id', array(
				'alias' => 'LoginToken'
		));
		
		$this->hasMany('id', __NAMESPACE__ . '\LoginToken', 'user_id', array(
				'alias' => 'LoginToken'
		));
		$this->belongsTo('title_id', __NAMESPACE__ . '\Title', 'id', array(
				'alias' => 'Title'
		));
		$this->belongsTo('level', __NAMESPACE__ . '\UserAttr', 'id', array(
				'alias' => 'attr'
		));
		$this->belongsTo('id', __NAMESPACE__ . '\AssociationMember', 'member_id', array(
				'alias' => 'associationMember'
		));
		$this->belongsTo('id', __NAMESPACE__ . '\LoginToken', 'user_id', array(
				'alias' => 'token'
		));
	}
}