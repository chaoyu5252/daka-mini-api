<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class AssociationLevel extends Model
{
	public $level;
	public $exp;
	public $member_limit;
	public $modify_sign;
	public $modify_title;
}