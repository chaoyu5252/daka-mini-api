<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Friend extends Base {
	public $id;
	public $user_id;
	public $friend_id;
	public $create_time;
	public $update_time;
	
	public function initialize(){
		parent::initialize();
	}

}