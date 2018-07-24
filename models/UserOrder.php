<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-24
 * Time: 下午2:15
 */

namespace Fichat\Models;

class UserOrder extends Base
{
    public $id;
    public $user_id = 0;
    public $balance = 0;
    public $amount = 0;
    public $status = 0;
    public $order_num = '';
    public $consum_type = 0;
    public $fee = 0;            // 手续费
    public $remark = '';
	public $create_time;
	public $update_time;

    public function initialize()
    {
        parent::initialize();
    }
}