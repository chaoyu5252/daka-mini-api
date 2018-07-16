<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class BalanceFlow extends Model
{
	public $id;
	public $op_type = 0;
	public $op_amount = 0;
	public $target_id = 0;
	public $user_order_id = 0;
	public $create_time = 0;
}