<?php
namespace Fichat\Utils;

use Phalcon\Di;
use Swoole;

class SwooleConn
{
	private $conn;
	private $config;
	
	public function __construct($di)
	{
		try {
			// 获取连接配置文件
			$this->config = $di->get('config')['swoole'];
			// 创建客户端连接
			$this->conn = new \swoole_client(SWOOLE_SOCK_TCP);
			$this->conn->connect($this->config['host'], $this->config['port'], $this->config['timeout']);
			// 检查是否已经连接成功
			if (!$this->conn->isConnected()) {
				return null;
			}
		} catch (Swoole\Exception $e) {
			return null;
		}
	}
	
	public function send($data)
	{
		$this->conn->send($data);
		$recv = $this->conn->recv();
		if (Utils::isJson($recv) ){
			$recv = json_decode($recv);
		}
		return $recv;
	}
	
	public function close()
	{
		$this->conn->close();
	}
	
	
}