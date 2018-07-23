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

class Files extends Base
{
    // 字段
    public $id;
    public $url;                // 文件地址
    public $type;               // 类型
    public $create_time;
    public $update_time;
	
	public function initialize()
	{
		parent::initialize();
	}

}