<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Report extends Model
{
	public $id;
	public $user_id;
    public $by_report_id;
	public $type;
	public $reason;
	public $content;
	public $is_act;
	public $create_time;

	public function initialize() {
	
	}
}