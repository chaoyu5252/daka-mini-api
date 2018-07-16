<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class SystemConfig extends Model
{
    public $id;
	public $sms_code_expire_time = 0;
	public $task_push_min_amount = 0;
	public $redpacket_push_min_amount = 0;
	public $redpacket_max_expire_time = 0;
	public $withdraw_service_charge = 0;
    public $withdraw_min_amount = 0;
    public $withdraw_day_limit = 0;
    public $is_verify_phone_code = 0;
}