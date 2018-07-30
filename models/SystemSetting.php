<?php
/**
 * Created by PhpStorm.
 * User: pang
 * Date: 2018/6/28
 * Time: 上午6:03
 */

namespace Fichat\Models;

use TinyDaily\Common\Common;

class SystemSetting extends Base
{
    // 字段
    public $id;
    public $item;                // 项
    public $value;               // 值
    public $create_time;
    public $update_time;
	
	public function initialize()
	{
		parent::initialize();
	}
}