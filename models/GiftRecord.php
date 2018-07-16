<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-31
 * Time: 下午5:32
 */
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class GiftRecord extends Model
{
    public $id;
    public $user_id;
    public $target_id;
    public $moment_id;
    public $gift_id;
    public $number;
    public $amount;
    public $create_time;

    public function initialize()
    {
        $this->belongsTo('gift_id', __NAMESPACE__ . '\Gift', 'id', array(
            'alias' => 'gift'
        ));
        $this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
            'alias' => 'user'
        ));
        $this->belongsTo('target_id', __NAMESPACE__ . '\User', 'id', array(
            'alias' => 'target'
        ));
        $this->belongsTo('moment_id', __NAMESPACE__ . '\Moments', 'id', array(
            'alias' => 'moments'
        ));
    }
}