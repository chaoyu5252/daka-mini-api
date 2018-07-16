<?php
namespace Fichat\Models;

use Phalcon\Mvc\Model;

class Moments extends Model
{
	public $id;
	public $user_id = 0;
	public $content = '';
	public $pri_url = '';
	public $pri_thumb = '';
	public $pri_preview = '';
	public $friend = 0;
	public $attention = 0;
	public $world = 0;
    public $type;
    public $red_packet_id = 0;
    public $create_time;

	public function initialize() {
		$this->belongsTo('user_id', __NAMESPACE__ . '\User', 'id', array(
				'alias' => 'user'
		));
		$this->belongsTo('red_packet_id', __NAMESPACE__ . '\RedPacket', 'id', array(
				'alias' => 'redPacket'
		));
	}
}