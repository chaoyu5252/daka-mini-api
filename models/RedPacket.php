<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-31
 * Time: 下午5:32
 */

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class RedPacket extends Model
{
    public $id;
    public $user_id;
    public $group_id = 0;
    public $amount = 0;
    public $number = 0;
    public $type;
    public $password = '';
    public $balance;
    public $status = 0;
    public $invalid = 0;
    public $des = '';
    public $create_time;
    public $start_time;

    public function initialize()
    {
        $this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
            'alias' => 'User'
        ));
        $this->belongsTo('id', __NAMESPACE__ . '\Moments', 'red_packet_id', array(
            'alias' => 'Moments'
        ));
        $this->hasMany('id', __NAMESPACE__ . '\RedPacketRecord', 'red_packet_id', array(
            'alias' => 'redPacketRecord'
        ));
        $this->hasMany('id', __NAMESPACE__ . '\UserGrabRedPacket', 'red_packet_id', array(
            'alias' => 'UserGrabRedPacket'
        ));
    }
}