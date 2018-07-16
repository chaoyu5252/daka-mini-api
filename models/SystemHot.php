<?php
namespace Fichat\Models;

use Fichat\Utils\Utils;
use Phalcon\Mvc\Model;

class SystemHot extends Model
{
	public $id;
	public $type = 0;
	public $trigger_id = 0;
	public $expo = 0;
	public $hot = 0;
	public $create_time = 0;

	public function initialize() {
	
	}
	
	public static function saveNewHot($id, $type, $expoCount = 1000, $hotCount = 100)
	{
		
		$sysHot = new SystemHot();
		$sysHot->trigger_id = $id;
		$sysHot->expo_num = $expoCount;
		$sysHot->hot_num = $hotCount;
		$sysHot->create_time = time();
		$sysHot->type = $type;
		if (!$sysHot->save()) {
			Utils::throwDbException($sysHot);
		}
	}
	
	public static function calHotEffectNum($amount)
	{
		$expoNum = 1000 + $amount * 100;
		$hotNum = 100 + $amount * 100;
		return ['expo_num' => $expoNum, 'hot_num' => $hotNum];
	}
	
	public static function addHotNum($id, $type, $hotCount = 0)
	{
		$sysHot = SystemHot::findFirst("trigger_id = ".$id);
		if ($sysHot) {
			$sysHot->hot_num = $sysHot->hot_num + $hotCount;
			if (!$sysHot->save()) {
				Utils::throwDbException($sysHot);
			}
		} else if ($type == 2) {
			$moments = Moments::findFirst("red_packet_id = ". $id) ;
			if ($moments) {
				$sysHot = SystemHot::findFirst("trigger_id = ".$moments->id);
				$sysHot->hot_num = $sysHot->hot_num + $hotCount;
				if (!$sysHot->save()) {
					Utils::throwDbException($sysHot);
				}
			}
		}
	}
	
	public static function delExpoNum($id)
	{
		$sysHot = SystemHot::findFirst("id = ".$id);
		if ($sysHot) {
			$sysHot->expo_num = $sysHot->expo_num - 1;
			if (!$sysHot->save()) {
				Utils::throwDbException($sysHot);
			}
		}
	}
}