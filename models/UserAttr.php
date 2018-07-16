<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class UserAttr extends Model
{
	public $id;
	public $level = 0;
	public $exp = 0;
	public $headimg_border_color = 0;
	public $friend_num = 0;
	public $assoc_num = 0;
	public $atten_num = 0;
}