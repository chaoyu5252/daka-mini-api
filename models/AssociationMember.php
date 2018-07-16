<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class AssociationMember extends Model
{
	public $id;
	public $association_id;
	public $member_id;
	public $nickname;
	public $user_type;
	public $type;
	public $add_time;
	public $confirm;
	public $level;
	public $exp;
	public $shut_up;
	public $perm;
	
	public function initialize()
	{
		$this->belongsTo('association_id', __NAMESPACE__ . '\Association', 'id', array(
				'alias' => 'association'
		));

        $this->belongsTo('member_id', __NAMESPACE__ . '\User', 'id', array(
            'alias' => 'user'
        ));
        
        $this->belongsTo('level', __NAMESPACE__ . '\AssociationMemberTitle', 'level', array(
            'alias' => 'member_title'
        ));

    }
}