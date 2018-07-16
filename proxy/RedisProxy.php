<?php
namespace Fichat\Proxy;

class RedisProxy
{
	
	private $client;
	
	public function __construct($host, $port, $timeout = 2)
	{
		$client = new \swoole_client(SWOOLE_SOCK_TCP);
		if (!$client->connect($host, $port, -1)) {
			exit("connection failed");
		};
		$this->client = $client;
	}
	
	public function send($data) {
		$this->client->send($data);
		return $this->client->recv();
	}
	
	public function close()
	{
		$this->client->close();
	}
	
}