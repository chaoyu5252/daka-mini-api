<?php
/**
 * Created by PhpStorm.
 * User: pang
 * Date: 2018/6/28
 * Time: 上午6:03
 */

namespace Fichat\Models;


use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Behavior\Timestampable;
use TinyDaily\Common\Common;

class Base extends Model
{
 
	public function initialize()
	{
		// 新增
		$this->addBehavior(new Timestampable(
			array(
				'beforeCreate' => array(
					'field' => 'create_time'
				)
			)
		));
		$this->addBehavior(new Timestampable(
			array(
				'beforeCreate' => array(
					'field' => 'update_time'
				)
			)
		));
		// 更新
		$this->addBehavior(new Timestampable(
			array(
				'beforeUpdate' => array(
					'field' => 'update_time'
				)
			)
		));
	}

}