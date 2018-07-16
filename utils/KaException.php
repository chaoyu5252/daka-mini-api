<?php
namespace Fichat\Utils;

use Fichat\Common\ReturnMessageManager;
use Fichat\Constants\ErrorConstantsManager;

class KaException {

	// 处理异常错误
	public static function error_handler($di, $exception, $errCode = null)
	{
		// 获取消息
		$errorMessage = $exception->getMessage();
//		Utils::echo_debug($errorMessage.', error_code:'.$errCode);
//		echo $exception->xdebug_message;
//		var_dump($exception);
		if ($errorMessage == "Transaction aborted" && $errCode != null) {
			// 事务异常
			return ReturnMessageManager::buildReturnMessage($errCode);
		} else {
			// 其它异常
			$errorInfo = explode(":", $errorMessage);
			$errorList = ErrorConstantsManager::$errorMessageList;
			if (count($errorInfo) == 2 && $errorInfo[0] == 'ERROR' && array_key_exists($errorInfo[1], $errorList)) {
				// 抛出指定错误码
				return ReturnMessageManager::buildReturnMessage($errorInfo[1]);
			} else {
				// 业务逻辑异常
				$di->get('logger')->debug(Utils::makeLogMessage($di, $exception));
				return ReturnMessageManager::buildReturnMessage('E9999');
			}
		}
	}
	
	// 抛出异常错误码
	public static function throwErrCode($errCode) {
		throw new \Exception('ERROR:'.$errCode);
	}
	
}
