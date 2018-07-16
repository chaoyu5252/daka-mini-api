<?php
namespace Fichat\Models;

use Fichat\Utils\Utils;
use Phalcon\Mvc\Model;

class SystemDyn extends Model
{
	public $id;
	public $type = 0;
	public $trigger_id = 0;
	public $group_id = 0;
	public $uid = 0;
	public $create_time = 0;
	
	
	public static function saveNewDyn($id, $type, $groupId = 0, $uid = 0)
	{
		$sysDyn = new SystemDyn();
		$sysDyn->trigger_id = $id;
		$sysDyn->create_time = time();
		$sysDyn->type = $type;
		$sysDyn->group_id = $groupId;
		$sysDyn->uid = $uid;
		if (!$sysDyn->save()) {
			Utils::throwDbException($sysDyn);
		}
	}
}