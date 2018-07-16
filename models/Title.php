<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Title extends Model
{
	public $id;
	public $name;
	public $demand;
	public $pri_url;
	public $pri_thumb;
}