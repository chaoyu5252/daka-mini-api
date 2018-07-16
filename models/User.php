<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class User extends Model {
	public $id;
	public $account_id;
	public $phone;
	public $nickname;
	public $gender;
	public $signature;
	public $user_avatar;
    public $user_thumb;
	public $title_id;
	public $level;
	public $exp;
	public $balance;
	public $verify;
	public $background;
	public $background_thumb;
	public $platform;
	public $channel;
	public $recommand_tags;
	public $birthday = '1993-12-26';
	public $email = '';
	public $name = '';
	public $id_code = '';
	public $invite_code = 0;
	public $diamond = 0;

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