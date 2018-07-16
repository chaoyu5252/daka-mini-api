<?php
namespace Fichat\Models;

use Fichat\Utils\RedisClient;
use Phalcon\Mvc\Model;

class AssociationMemberTitle extends Model
{
    public $id;
	public $level;
	public $exp;
	public $title;
	public $task_limit;
	
	public static function findAll(\Redis $redis)
	{
		$key = RedisClient::associationMemberTitleKey();
		$data = $redis->hGetAll($key);
		if ($data) {
			foreach($data as $level => $titleJsonInfo){
				$titleInfo = json_decode($titleJsonInfo, true);
				$data[$level] = $titleInfo;
			}
			return $data;
		} else {
			$memberTitles = parent::find();
			if (!$memberTitles) { return false; }
			$saveData = array();
			// 构建数据
			foreach ($memberTitles as $memberTitle)
			{
				$titleInfo = array();
				$titleInfo['exp'] = $memberTitle->exp;
				$titleInfo['title'] = $memberTitle->title;
				$titleInfo['task_limit'] = $memberTitle->task_limit;
				$data[$memberTitle->level] = $titleInfo;
				$saveData[$memberTitle->level] = json_encode($titleInfo);
			}
			$redis->hMset($key, $saveData);
			return $data;
		}
	}
}