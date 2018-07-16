<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-31
 * Time: 下午5:32
 */
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class UserGrabRedPacket extends Model
{
    public $id;
    public $red_packet_id;
    public $user_id;

    public function initialize()
    {
        $this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
            'alias' => 'User'
        ));
        $this->belongsTo('red_packet_id', __NAMESPACE__ . '\RedPacket', 'id', array(
            'alias' => 'redPacket'
        ));
    }
}