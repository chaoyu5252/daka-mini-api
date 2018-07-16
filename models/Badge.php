<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Badge extends Model
{
	public $id;
	public $user_id;
	public $funs;
	public $enemy;
	public $friend;
}