<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-24
 * Time: 下午2:15
 */

namespace Fichat\Models;

use Phalcon\Mvc\Model;

class ExchangeKaMiRecord extends Model
{
    public $id;
    public $code = '';
    public $uid = 0;
    public $amount = 0;
    public $create_time;
}