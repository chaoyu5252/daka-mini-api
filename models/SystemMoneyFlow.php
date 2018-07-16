<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class SystemMoneyFlow extends Model
{
	public $id;
	public $op_type;
	public $op_amount;
	public $target_id = 0;
	public $pay_channel;
	public $uid = 0;
	public $user_order_id = 0;
	public $create_time = 0;
	
}