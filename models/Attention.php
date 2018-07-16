<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Attention extends Model
{
	public $id;
	public $user_id;
	public $target_id;
	public $confirm;
	public $is_look;
	public $forbid_look;
	public $is_new;
	public $create_time;
	
	public function initialize(){
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
	
		$this->belongsTo('target_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'attention'
		));
	}
}