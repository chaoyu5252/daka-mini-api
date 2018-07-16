<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class AssociationRequest extends Model
{
	public $id;
	public $user_id;
	public $inviter_id;
	public $association_id;
	public $status;
	public $message;
	public $is_new;
	public $create_time;
	
	public function initialize()
	{
		$this->belongsTo('association_id', __NAMESPACE__ . '\Association', 'id', array(
				'alias' => 'association'
		));
	
		$this->belongsTo('inviter_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'inviter'
		));
		
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
	}
}