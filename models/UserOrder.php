<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-24
 * Time: 下午2:15
 */

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class UserOrder extends Model
{
    public $id;
    public $user_id = 0;
    public $balance = 0;
    public $pay_channel = 0;
    public $amount = 0;
    public $status = 0;
    public $order_num = '';
    public $callback_data = '';
    public $consum_type = 0;
    public $pay_account = '';
    public $fee = 0;            // 手续费
    public $red_packet_gift_id;
    public $remark = '';
	public $create_date = 0;
	public $withdrawals_account = '';   // 提现帐户

    public function initialize()
    {
        $this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
            'alias' => 'order'
        ));
    }
}