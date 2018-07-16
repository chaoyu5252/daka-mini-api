<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-31
 * Time: 下午5:32
 */
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Gift extends Model
{
    public $id;
    public $name;
    public $price;
    public $picture;
    public $des;
}