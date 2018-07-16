<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Feedback extends Model {
	public $id;
	public $uid = 0;
	public $conetent = '';
	public $create_time = 0;
	
	public function initialize(){
		$this->belongsTo('uid', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
	}

}