<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class MomentsLike extends Model
{
	public $id;
	public $moments_id;
	public $user_id;
	public $like_time;
	
	public function initialize() {
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
	}
}