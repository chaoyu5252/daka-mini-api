<?php

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Tag extends Model {
	public $id;
	public $tag = "";
	public $parent_id = 0;
	public $sys_rcmd = 0;
}