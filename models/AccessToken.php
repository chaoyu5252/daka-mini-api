<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class AccessToken extends Model {
	public $id;
	public $token;
	public $expires_in;
	public $application;
	public $get_time;
}