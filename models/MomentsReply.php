<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class MomentsReply extends Model
{
	public $id;
	public $moments_id;
	public $user_id;
	public $content;
	public $reply_time;
	public $parent_id;
	public $like_count;
	public $status;
	
	public function initialize() {
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));

        $this->belongsTo('parent_id', __NAMESPACE__ . '\MomentsReply', 'id', array(
            'alias' => 'parentReply'
        ));
	}
}