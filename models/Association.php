<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Association extends Model
{
	public $id;
	public $assoc_id;
	public $owner_id;
	public $group_id;
	public $nickname;
	public $level;
	public $exp;
	public $bulletin = '';
	public $assoc_avatar;
    public $assoc_thumb;
	public $type;
	public $open;
	public $confirm;
	public $current_number;
	public $max_number;
	public $create_time;
	public $speak_mode;
    public $speak_time_interval;
	public $tags;
	public $info;
	
	public function initialize() {
		$this->belongsTo('owner_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));

        $this->belongsTo('level', __NAMESPACE__ . '\AssociationLevel', 'level', array(
            'alias' => 'associationLevel'
        ));
	}
}