<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class SignToken extends Model {
    public $id;
    public $code;
    public $phone;
    public $time;
}