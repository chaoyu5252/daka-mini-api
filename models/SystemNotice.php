<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class SystemNotice extends Model
{
	public $id;
	public $type;
	public $platform;
	public $trigger_id;
	public $msg_id;
	public $data;
	public $send_time;

	public function initialize() {
	
	}
}