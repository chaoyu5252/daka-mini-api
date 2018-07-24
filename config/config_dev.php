<?php

use Phalcon\Config;
use Phalcon\Loader;
use Phalcon\Logger;
use Phalcon\DI\FactoryDefault;
use Phalcon\Crypt;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Logger\Adapter\File as FileLogger;
use Phalcon\Logger\Formatter\Line as FormatterLine;
use Phalcon\Mvc\Model\Transaction\Manager as TcManager;

use Fichat\Utils\RedisClient;

$config = new Config([
		CONFIG_KEY_DB => [
				'adapter' => 'Mysql',
				'host' => '127.0.0.1',
				'port' => 3306,
				'username' => 'root',
				'password' => 'DKOLChina2016',
				'dbname' => 'fichat_mini',
				"charset" => "utf8mb4"
		],
		CONFIG_KEY_APP => [
				'modelsDir' => APP_DIR . '/models/',
				'libraryDir' => APP_DIR . '/library/',
				'proxyDir' => APP_DIR . '/proxy/',
				'commonDir' => APP_DIR . '/common/',
				'constantsDir' => APP_DIR . '/constants/',
				'libraryDir' => APP_DIR . '/library/',
				'utilsDir' => APP_DIR . '/utils/',
				'middleware' => APP_DIR . '/middleware/',
				'cryptSalt' => 'eEAfR|_&G&f,+vU]:jFr!!A&+71w1Ms9~8_4L!<@[N@DyaIP_2My|:+.u>/6m,$D',
				'smsCodeExpireTme' => 180,
				'rewardAmountLine' => 100,           // 悬赏金额基线
				'redpackAmountLine' => 10,           // 红包金额基线
				'redpackMaxKeepTime' => 86400,       // 红包最大持续时间
				'default_redpacket_cover' => 'http://dakaapp-public.oss-cn-beijing.aliyuncs.com/rtcover/rtcover_default.png',
				'default_redpacket_cover_thumb' => '?x-oss-process=style/thumb_resize'
		],
		CONFIG_KEY_LOG => [
				'path'     => APP_DIR . '/logs/',
				'format'   => '%date% - %type% - %message%',
				'date'     => 'D j H:i:s',
				'logLevel' => Logger::DEBUG,
				'filename' => 'application.log',
		],
		CONFIG_KEY_HX => [
				'appKey'     => '1129170207115947#fichat',
				'clientId'   => 'YXA6MN2LoOz_Eea5bxsUEtrLJg',
				'clientSecret'     =>'YXA6DrBwMgYXaifo2HtdyrHGehtPy5w',
				'orgName' =>'1129170207115947',
				'appName' =>'fichat',
				'url' => 'https://a1.easemob.com/1129170207115947/fichat/'
		],
        CONFIG_KEY_DEBUG => [
            'check_sign' => false
        ],
        CONFIG_KEY_REDIS => [
            'host' => '127.0.0.1',
            'port' => 6379
        ],
        CONFIG_KEY_BAIDU_PUSH => [
        	'android' => [
        		'apiKey' => 'hVBvzM1pSI2Pk6eDisrnlx5Y',
		        'secretKey' => 'ViKfnd4t3VEbRDS4jXUMxLeHWiXc5lE7'
	        ],
	        'ios' => [
		        'apiKey' => 'RRRG8BosL48c3G6DSBx35zsK',
		        'secretKey' => '81Hw8lEAGj3VLWsVvqva230sG9BDjtZa'
	        ]
        ],
        CONFIG_KEY_BAIDU_OPENAPI => [
        	'api_uri' => 'http://openapi.baidu.com',
        	'app_id' => '10813766',
            'app_key' => 'kCa96X7Tfup0vGW1LZgD3zfO',
	        'app_secret' => '9P2WqsHceEOK0dVUkZpnfCLhbq8cOv7p'
        ],
        CONFIG_KEY_VIDEO_THUMB => [
            'resoRate' => '640x960',
	        'frameRate' => 10,
	        'duration' => 1,
	        'startTime' => '00:00:00'
        ],
        CONFIG_KEY_OSS => [
                'appKey' => 'LTAISMpurfqOEpcG',
                'appSecret' => 'eCkoDmLyRa7dcJh4mILyIo8ndfGm36',
                'bucket' => [
                    'uavatar' => ['space' => 'dakaapp-avatar', 'end_point' => 'oss-cn-beijing.aliyuncs.com'],
                    'gavatar' => ['space' =>'dakaapp-group', 'end_point' => 'oss-cn-beijing.aliyuncs.com'],
                    'moments' => ['space' => 'dakaapp-moments', 'end_point' => 'oss-cn-beijing.aliyuncs.com'],
                    'rtcover' => ['space' => 'dakaapp-rtcover', 'end_point' => 'oss-cn-beijing.aliyuncs.com'],
                    'background' => ['space' => 'dakaapp-background', 'end_point' => 'oss-cn-beijing.aliyuncs.com'],
	                'video' => ['space' => 'dakaapp-video', 'end_point' => 'oss-cn-beijing.aliyuncs.com']
                ]
        ],
		CONFIG_KEY_SWOOLE => [
			'host' => '127.0.0.1',
			'port' => 10000,
			'timeout' => 3
		],
		CONFIG_KEY_WXMINI => [
			'app_id' => 'wx929f0995fb3cd01f',
			'app_key' => 'd6b2d3925e4177645b405145e0736325'
		],
		CONFIG_KEY_WXPAY => [
			'mch_id' => '1486539082',
			'mch_secret' => ''
		]
	       
]);

$loader = new Loader();
/**
 * We're a registering a set of directories taken from the configuration file
 */
$loader->registerNamespaces([
		'Fichat\Models'	    => $config->application->modelsDir,
		'Fichat\Proxy'		=> $config->application->proxyDir,
		'Fichat\Common'		=> $config->application->commonDir,
		'Fichat\Constants'	=> $config->application->constantsDir,
		'Fichat\Library'	=> $config->application->libraryDir,
		'Fichat\Utils'		=> $config->application->utilsDir,
        'Fichat\Middleware' => $config->application->middleware
]);

$loader->register();

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();
/**
 * Register the global configuration as config
 */
$di->setShared('config', function () use ($config) {
	return $config;
});

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () use ($config) {
	return new DbAdapter(array(
			'host' => $config->database->host,
			'username' => $config->database->username,
			'password' => $config->database->password,
			'dbname' => $config->database->dbname,
            'port' => $config->database->port,
			"charset" => $config->database->charset
	));
});

/**
 * Crypt service
 */
$di->setShared('crypt', function () use ($config) {
	$crypt = new Crypt();
	$crypt->setKey($config->application->cryptSalt);
	return $crypt;
});

/**
 * 设置Redis连接池
 */
$di->setShared('redis_conns', function () use ($config) {
	return [
	
	];
});

/**
 * Logger service
 */

$di->setShared('logger', function () use ($config) {
	$loggerFile = $config['logger']['path'] . $config['logger']['filename'];
	$logger = new FileLogger($loggerFile, array(
		'mode' => 'aw'
	));
	// 日志格式
	$formatter = new FormatterLine($config['logger']['format']);
	$logger->setFormatter($formatter);
	$logger->setLogLevel($config['logger']['logLevel']);
	return $logger;
});


$di->setShared(SERVICE_TRANSACTION, function () {
	$manager = new TcManager();
	return $manager->get();
});


$di->setShared(SERVICE_REDIS, function () use ($config) {
	return RedisClient::create($config['redis']);
});

$di->setShared(SERVICE_GLOBAL_DATA, function () {
	return new \Fichat\Constants\GlobalData();
});
