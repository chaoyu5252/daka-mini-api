<?php
namespace Fichat\Library;

use Fichat\Common\ReturnMessageManager;
use Fichat\Models\User;
use Fichat\Models\UserOrder;
use Fichat\Proxy\WeixinPay;
use Fichat\Utils\Utils;


class RankProcessor {
	
	// 世界榜
	public static function getWorldRank($di)
	{
		try {
			Utils::getService($di, SERVICE_REDIS);
		
		
		} catch (\Exception $e) {
			return ReturnMessageManager::buildReturnMessage(ERROR_LOGIC);
		}
	}

}


