<?php

namespace Fichat\Utils;

use Fichat\Common\APIValidator;

use Fichat\Common\ReturnMessageManager;

use Fichat\Common\DBManager;
use Fichat\Constants\ErrorConstantsManager;
use Fichat\Models\AssociationMember;
use Phalcon\Di;
use Phalcon\Tag\Select;
use Swoole\Exception;


class Utils
{
	
	// 创造TOKEN
	public static function makeToken($openid)
	{
		return md5($openid.'*'.TOKEN_MD5_KEY.'#'.time());
	}
	
	// 获取服务
	public static function getService($di, $serviceName) {
		return $di->getShared($serviceName);
	}
	
	// 发送http
	public static function http_get($url){
		$oCurl = curl_init();
		if(stripos($url,"https://")!==FALSE){
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
		}
		curl_setopt($oCurl, CURLOPT_URL, $url);//目标URL
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );//设定是否显示头信息,1为显示
		curl_setopt($oCurl, CURLOPT_BINARYTRANSFER, true) ;//在启用CURLOPT_RETURNTRANSFER时候将获取数据返回
		$sContent = curl_exec($oCurl);
		$aStatus = curl_getinfo($oCurl);//获取页面各种信息
		curl_close($oCurl);
		if(intval($aStatus["http_code"])==200){
			return $sContent;
		}else{
			return false;
		}
	}
	
	// 生成UUID
	public static function guid()
	{
		if (function_exists('com_create_guid')) {
			return com_create_guid();
		} else {
			mt_srand(( double )microtime() * 10000);
			$charid = strtoupper(md5(uniqid(rand(), true)));
			$hyphen = chr(45);
			$uuid = substr($charid, 0, 8) . $hyphen . substr($charid, 8, 4) . $hyphen . substr($charid, 12, 4) . $hyphen . substr($charid, 16, 4) . $hyphen . substr($charid, 20, 12);
			return $uuid;
		}
	}
	
	public static function GetRandStr()
	{
		$chars = array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9");
		
		$charsLen = count($chars) - 1;
		shuffle($chars);
		$output = "";
		for ($i = 0; $i < 6; $i++) {
			$output .= $chars[mt_rand(0, $charsLen)];
		}
		return $output;
	}

	public static function getRandInt($len) {
	    if($len == 6) {
            $type = 1;
	        $min = 100000;
	        $max = 999999;
        } else {
            $type = 2;
            $min = 10000000;
            $max = 99999999;
        }
        $bestIds = DBManager::getAllBestID($type);
        while(true) {
            $aid = mt_rand($min, $max);
            if(!in_array($aid, $bestIds)) {
                return $aid;
            }
        }
    }
	
	public static function randImgName()
	{
		$i = rand(0, 2);
		$array = array('default1', 'default2', 'default3');
		return $array[$i];
	}
	
	public static function preprocessBody($rawBody)
	{
		$out = '';
		return parse_str($rawBody, $out);;
	}
	
	/**
	 * 生成订单号
	 *
	 * @param  userId
	 * @return false|string
	 */
	public static function generateOrderId($userId)
	{
		$time = date("YmdHis");
		$time .= $userId;
		$time .= mt_rand(0, 10000);
		return $time;
	}
	
	/**
	 * xml转array
	 *
	 * @param $xml
	 * @return mixed
	 */
	public static function xmlToArray($xml)
	{
		//禁止引用外部xml实体
		libxml_disable_entity_loader(true);
		$values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		return $values;
	}
	
	/**
	 * curl post请求
	 *
	 * @param $url
	 * @param $data
	 * @return mixed
	 */
	public static function curl_post($url, $data)
	{
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
		curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
	
	public static function redis()
	{
		$redis = new \Redis();
		$redis->connect('127.0.0.1', 6379);
		return $redis;
	}
	
	// 创建红包的Key
	public static function redpack_key($id)
	{
		return "redpack:" . $id;
	}
	
	// 抢过红包的用户数
	public static function grab_redpack_users_key($id)
	{
		return "grab_redpack:" . $id;
	}
	
	
	/**
	 * 创建红包说说key
	 *
	 * @param $id
	 * @return string
	 */
	public static function moment_key($id)
	{
		return "moment:" . $id;
	}
	
	/**
	 * 使用微信,支付宝支付红包创建key
	 *
	 * @param $id
	 * @return string
	 */
	public static function redPacketTemporaryKey($id)
	{
		return "redPacketTemporaryKey:" . $id;
	}
	
	/**
	 * 使用微信,支付宝支付礼物创建key
	 *
	 * @param $id
	 * @return string
	 */
	public static function giveGiftTemporaryKey($id)
	{
		return "giveGiftTemporaryKey:" . $id;
	}
	
	/**
	 * 处理空值
	 *
	 */
	public static function procNull($value, $target_value = null)
	{
		if (!$value) {
			if ($target_value) {
				return $target_value;
			} else {
				return '';
			}
		} else {
			return $value;
		}
	}
	
	/**
	 * 验证说说的Visible
	 * "朋友|关注"
	 */
	public static function verifyMomentVisible($visible)
	{
		$pattern = '/^[0-1]{2}$/';
		preg_match($pattern, $visible, $match);
		if ($match[0]) {
			return true;
		} else {
			return false;
		}
	}
	
	/** 处理moments的图片及缩略图 */
	public static function evoMomentPri($moments)
	{
		// 朋友圈图片
		$priUrl = explode('|', $moments->pri_url);
		$pri_thumb = explode('|', $moments->pri_thumb);
		$priInfo = array();
		foreach ($priUrl as $index => $value) {
			$priInfo['pri_url'][$index] = OssApi::procOssPic($value);
			$priInfo['pri_thumb'][$index] = OssApi::procOssPic($pri_thumb[$value]);
			$priInfo['pri_preview'][$index] = OssApi::procOssPic($value);
		}
		$priInfo['pri_url'] = OssApi::procOssPic($priUrl[0]);
		$priInfo['pri_thumb'] = OssApi::procOssThumb($pri_thumb[0]);
		return $priInfo;
	}
	
	/**
	 * 排行标记时间
	 *
	 */
	public static function rankSignTS()
	{
		return $startDate = strtotime("this week Monday", time());
	}
	
	/**
	 * 打包
	 *
	 */
	public static function pack($data)
	{
		bzcompress(serialize($data));
	}
	
	/**
	 * 解包
	 *
	 */
	public static function unpack($str)
	{
		bzdecompress(unserialize($str));
	}
	
	public static function echo_debug($str)
	{
		echo "<br>echo: " . $str;
	}
	
	/**
	 * 构建用户默认密码
	 *
	 */
	public static function makePassword($di, $phone)
	{
		$key = $di->get('config')['application']['cryptSalt'];
		$now = microtime();
		return md5($phone . "#" . $now . "|" . $key);
	}
	
	/**
	 * 获取短信码有效时间
	 *
	 */
	public static function smsCodeExpire($di)
	{
		return $di->get('config')['application']['smsCodeExpireTme'];
	}
	
	// 验证是否是一个地址
	public static function vaildLink($link)
	{
		if (!preg_match("/^https?:\/\//", $link)) {
			return false;
		}
		Return true;
	}
	
	// 跑出数据库运行异常
	public static function throwDbException($model)
	{
		$message = $model->getMessages()[0];
//		throw new \RuntimeException(__METHOD__.$model->getMessages()[0]);
		throw new \RuntimeException($message);
	}
	
	// 构建异常日志文件
	public static function makeLogMessage($di, \Exception $e)
	{
		$trace = $e->getTrace()[1];
		return "; Method:".$trace['function'].'; File:'.$trace['file'].'; Line:'.$trace['line']."; Message：".$e->getMessage();
	}
	
	// 抛出错误码
	public static function throwErrorCode($ErrorCode) {
		throw new \Exception('ERROR:'.$ErrorCode);
	}
	
	// 实名认证
	public static function realNameValid($name, $idCode)
	{
		$data = [
			'name' => $name,
			'idCard' => $idCode
		];
		// 发送数据
		if (Self::curl_post("http://longqishi.com/authapi", $data) == '200') {
			return true;
		} else {
			return false;
		}
	}
	
	// 验证日期格式
	public static function validBirthDayFormat($birthday)
	{
		return preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $birthday);
	}
	
	// 获取年周标记
	public static function getYearWeek()
	{
		return date('Y').strftime('%U', time());
	}
	
	/**
	 * 10进制转36进制
	 */
	public static function to36($n10) {
		$n10 = intval($n10);
		if ($n10 <= 0)
			return false;
		$charArr = array("0","1","2","3","4","5","6","7","8","9",'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
		$char = '';
		do {
			$key = ($n10 - 1) % 36;
			$char= $charArr[$key] . $char;
			$n10 = floor(($n10 - $key) / 36);
		} while ($n10 > 0);
		return $char;
	}
	
	/**
	 * 十进制转36进制
	 */
	public static function from36($c36){
		$array=array("0","1","2","3","4","5","6","7","8","9","A", "B", "C", "D","E", "F", "G", "H", "I", "J", "K", "L","M", "N", "O","P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y","Z");
		$len=strlen($c36);
		$sum = 0;
		for($i=0;$i<$len;$i++){
			$index=array_search($c36[$i],$array);
			$sum += ($index+1)*pow(36,$len-$i-1);
		}
		return $sum;
	}
	
	/**
	 * 获取毫秒
	 */
	public static function getMillisecond()
	{
		list($t1, $t2) = explode(' ', microtime());
		return (int)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
	}
	
	/**
	 * 获取一年中的第几天
	 *
	 */
	public static function getDayth()
	{
		$dateInfo = explode('-', date('m-d'));
		$strDay = sprintf('%d-%d-%d', date("Y"), $dateInfo[0], $dateInfo[1]);
		return date('z', strtotime($strDay)) + 1;
	}
	
	/**
	 * 创建昵称号码
	 *
	 */
	public static function makeNicknameNo()
	{
		$minNo = Self::from36('A0000');
		$maxNo = Self::from36('FFFFFF');
		return self::to36(rand($minNo, $maxNo));
	}
	
	/**
	 * 构建红包消息
	 *
	 */
	public static function makeRedPackMsg($user, $redpack)
	{
		return $user->nickname."在朋友圈发布了一个(".$redpack->amount.")的红包";
	}
	
	/**
	 * 构建红包推送消息数据
	 *
	 */
	public static function makeRedpackPushMsgCustData($redPack, $user, $pri_url, $pri_thumb)
	{
		$data = array();
		$data['messageType'] = 4;
		$data['id'] = $redPack->id;
		$data['uid'] = $redPack->user_id;
		$data['nickname'] = $user->nickname;
		$data['amount'] = $redPack->amount;
		$data['user_avatar'] = OssApi::procOssPic($user->user_avatar);
		$data['user_thumb'] = OssApi::procOssThumb($user->user_thumb);
		$data['coverPic'] = OssApi::procOssPic($pri_url);
		$data['coverThumb'] = OssApi::procOssThumb($pri_thumb);
//		$data['number'] = $redPack->number;
//		$data['type'] = $redPack->type;
//		$data['balance'] = $redPack->balance;
//		$data['status'] = $redPack->status;
//		$data['invalid'] = $redPack->invalid;
//		$data['des'] = $redPack->des;
//		$data['create_time'] = $redPack->create_time;
//		$data['start_time'] = $redPack->start_time;
		return $data;
	}
	
	/**
	 * 构建悬赏消息
	 *
	 */
	public static function makeRewardMsg($association, $rewardTask)
	{
		return $association->nickname.'在家族中发布了一个('.$rewardTask->reward_amount.')的悬赏任务';
	}
	
	/**
	 * 构建任务推送消息数据
	 *
	 */
//	public static function makeRewardPushMsgCustData($association, $rewardTask)
//	{
//		$data = array();
//		$data['messageType'] = 3;
//		$data['id'] = $rewardTask->id;
//		$data['uid'] = $rewardTask->owner_id;
//		$data['amount'] = $rewardTask->reward_amount;
//		$data['clickReward'] = $rewardTask->click_reward;
////		$data['shareReward'] = $rewardTask->share_reward;
////		$data['link'] = $rewardTask->link;
////		$data['title'] = $rewardTask->title;
////		$data['content'] = $rewardTask->content;
//		$data['end_time'] = $rewardTask->end_time;
////		$data['click_count'] = $rewardTask->click_count;
////		$data['share_count'] = $rewardTask->share_count;
////		$data['status'] = $rewardTask->status;
////		$data['balance'] = $rewardTask->balance;
//		$data['group_id'] = $rewardTask->group_id;
////		$data['create_time'] = $rewardTask->create_time;
////		$data['total_click_count'] = $rewardTask->total_click_count;
////		$data['total_share_count'] = $rewardTask->total_share_count;
//		$data['associationName'] = $association->nickname;
//		$data['coverPic'] = OssApi::procOssPic($rewardTask->cover_pic);
//		$data['coverThumb'] = OssApi::procOssThumb($rewardTask->cover_thumb);
//		$data['assoc_avatar'] = OssApi::procOssPic($association->assoc_avatar);
//		$data['assoc_thumb'] = OssApi::procOssPic($association->assoc_thumb);
//		return $data;
//	}
	
	/**
	 * 随机分配红包
	 * @param:  $amount     红包金额
	 * @param:  $num        红包数量
	 * @param:  $min        红包最小金额
	 * @param:  $sigma      标准差, 用来确定最大值
	 * @return array | null
	 */
	public static function makeRedpackArr($amount, $num, $min = 0.01, $sigma = 2)
	{
		$avg = round($amount / $num, 2);
		$max = round($avg * $sigma, 2);
		$redpackArr = [];
		for ($i = 0;$i < $num; $i++) {
			if ($i == ($num - 1)) {
				self::echo_debug('LastReward:'.$amount);
				$money = Self::avgDistAmount($amount, $max, $redpackArr);
//				if ($amount > $max) {
//					$redpackArrCount = count($redpackArr);
//					$ofMoney = $amount - $max;
//					$ofAvg = round($ofMoney / $redpackArrCount, 2);
//					Utils::echo_debug('OfMoney:'.$ofMoney.' , count:'.$redpackArrCount. ', ofAvg:'.$ofAvg);
//					// 将溢出的金额平均分配给其它项
//
//
//					foreach($redpackArr as $idx => $m) {
//						$om = $m;
////						$m = $m + $ofAvg;
//						if (($m + $ofAvg) > $max) {
//							$m = $max;
//							$divide = $max - $m;
//						} else {
//							$divide = $ofAvg;
////							Utils::echo_debug('add_value:'.$ofAvg.', m:'.$m.' sum:'.($m + $ofAvg));
//							$m += $ofAvg;
//						}
//						$ofMoney = round($ofMoney - $divide, 2);
//						if ($ofMoney < 0) {
//							$m += $ofMoney;
//							$redpackArr[$idx] = $m;
//							Utils::echo_debug('www, amount:'.$amount);
//							break;
//						} else {
//							$redpackArr[$idx] = $m;
//						}
//						Utils::echo_debug('ofMoney:'.$ofMoney. ', nm:'.$m.' , om:'. $om. '<br/>');
//					}
//					$money = $max;
//				} else {
//					$money = $amount;
//				}
				array_push($redpackArr, $money);
			} else {
				$safe_total = round(($amount - ($num - $i) * $min) / ($num - $i), 2);//随机安全上限
				if ($safe_total > $max) {
					$safe_total = $max;
				}
				$money = mt_rand($min * 100, $safe_total * 100) / 100;
				$money = $money > $max ? $max : $money;
				$amount = $amount - $money;
			}
			array_push($redpackArr, $money);
		}
		// 查找数据中金额大于最大值得平均分配给其它值
//		for ($i = 1; $i < $num; $i++) {
//
//		}
		
		shuffle($redpackArr);
		return $redpackArr;
		
//		var_dump($redPackArr);
//		$sum = 0;
//		foreach ($redPackArr as $value) {
//			$sum += $value;
//		}
//		Utils::echo_debug('@sum:'.$sum);
	}
	
	// 平均分配多余的Amount
	private static function avgDistAmount($amount, $max, $redpackArr)
	{
		if ($amount > $max) {
			$redpackArrCount = count($redpackArr);
			$ofMoney = $amount - $max;
			$ofAvg = round($ofMoney / $redpackArrCount, 2);
//			Utils::echo_debug('OfMoney:'.$ofMoney.' , count:'.$redpackArrCount. ', ofAvg:'.$ofAvg);
			// 将溢出的金额平均分配给其它项
			foreach($redpackArr as $idx => $m) {
				$om = $m;
				if (($m + $ofAvg) > $max) {
					$m = $max;
					$divide = $max - $m;
				} else {
					$divide = $ofAvg;
//							Utils::echo_debug('add_value:'.$ofAvg.', m:'.$m.' sum:'.($m + $ofAvg));
					$m += $ofAvg;
				}
				$ofMoney = round($ofMoney - $divide, 2);
				if ($ofMoney < 0) {
					$m += $ofMoney;
					$redpackArr[$idx] = $m;
//					Utils::echo_debug('ofMoney:'.$ofMoney. ', idx:'.$idx. ', nm:'.$m.' , om:'. $om . ', sumRedPack:' . array_sum($redpackArr). '<br/>');
//					Utils::echo_debug('www, amount:'.$amount.', ofMoney:'.$ofMoney. ', sum:'. array_sum($redpackArr));
					break;
				} else {
					$redpackArr[$idx] = $m;
				}
//				Utils::echo_debug('ofMoney:'.$ofMoney. ', idx:'.$idx. ', nm:'.$m.' , om:'. $om. ', sumRedPack:' . array_sum($redpackArr). '<br/>');
			}
			$money = $max;
		} else {
			$money = $amount;
		}
		return $money;
	}
	
	/**
	 * 隐藏用户实名
	 *
	 */
	public static function hiddenRealName($realName)
	{
	    $realNameLength = (int)(strlen($realName)/3);
        $hidden = '';
        for($i = 0; $i < $realNameLength-1; $i++) {
           $hidden .= '*';
        }
		return $hidden.mb_substr($realName, -1, 1, 'utf-8');
	}
	
	/**
	 * 隐藏身份证号码
	 *
	 */
	public static function hiddenIdCode($idCode)
	{
		return substr($idCode, 0, 2)."****************".substr($idCode, 16, 2);
	}
	
	/**
	 * 获取目标数组比它大的索引的ID
	 *
	 */
	public static function getNewIndexInRequest($array, $id)
	{
		$retIndx = -1;
		foreach ($array as $idx => $item) {
			if ($item['request_id'] > $id) {
				$retIndx = $idx;
				break;
			}
		}
		return $retIndx;
	}
	
	public static function sortByKey($arr, $key)
	{
		$len = count($arr);
		for($i=1;$i<$len;$i++)
		{ //该层循环用来控制每轮 冒出一个数 需要比较的次数
			for($k=0;$k<$len-$i;$k++)
			{
				if($arr[$k][$key]<$arr[$k+1][$key])
				{
					$tmp=$arr[$k+1];
					$arr[$k+1]=$arr[$k];
					$arr[$k]=$tmp;
				}
			}
		}
		return $arr;
	}

    /**
     * 相同值的情况下，按索引排序
     *
     * @param $arr
     * @return mixed
     */
	public static function sortByKeyAndSameValue($arr) {
        if(!is_array($arr)) {
            return false;
        }

        if(count($arr) < 1) {
            return false;
        }

        //获取所有的键
        $keys = array_keys($arr);
        //获取所有的值
        $vals = array_values($arr);
        //先对值排序,值相同时再对键排序
        array_multisort($vals, SORT_DESC, $keys);
        //将排序后的键和值重新组合成数组
        $arr = array_combine($keys, $vals);
        return $arr;
    }

	/** 处理异常 */
	public static function processExceptionError($di, $e, $errCode = null)
	{
		
		if ($errCode) {
//			var_dump($e->getMessage());
			return ReturnMessageManager::buildReturnMessage($errCode);
		} else {
			$errorMessage = $e->getMessage();
			$errorInfo = explode(":", $errorMessage);
			if (count($errorInfo) == 2) {
				if ($errorInfo[0] == 'ERROR' && array_key_exists($errorInfo[1], ErrorConstantsManager::$errorMessageList)) {
					return ReturnMessageManager::buildReturnMessage($errorInfo[1]);
				} else {
					$di->get('logger')->debug(self::makeLogMessage($di, $e));
					return ReturnMessageManager::buildReturnMessage(ERROR_LOGIC);
				}
			} else {
				$di->get('logger')->debug(self::makeLogMessage($di, $e));
				return ReturnMessageManager::buildReturnMessage(ERROR_LOGIC);
			}
		}
	}
	
	/** 检查是否是json */
	public static function isJson($jsonData)
	{
		json_decode($jsonData);
		if (json_last_error() == JSON_ERROR_NONE) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 验证家族成员的Perm值
	 * "朋友|关注"
	 */
	public static function verifyFamilyMemPerm($perm)
	{
		$pattern = '/^[0-1]{8}$/';
		preg_match($pattern, $perm, $match);
		if ($match[0]) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 验证家族成员操作权限
	 *
	 */
	public static function verifyFamilyOpPerm($userId, $familyId, $opPermId)
	{
		$familyMember = AssociationMember::findFirst("member_id=".$userId." AND association_id = ".$familyId);
		if ($familyMember) {
			$perm = (int)$familyMember->perm[$opPermId];
			if ($perm) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public static function commitTc($di) {
		$transaction = $di->getShared(SERVICE_TRANSACTION);
		if ($transaction->isValid()) {
			return $transaction->commit();
		}
		return true;
	}
	
	public static function commitTcReturn($di, $returnData, $errCode = null) {
		$transaction = self::getDiTransaction($di);
		if ($transaction->isValid()) {
			if ($transaction->commit()){
				return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
			} else if ($errCode != null) {
				return ReturnMessageManager::buildReturnMessage($errCode);
			} else {
				return ReturnMessageManager::buildReturnMessage('E9999');
			}
		}
		return ReturnMessageManager::buildReturnMessage('E0000', $returnData);
	}
	
	public static function getDiTransaction($di) {
		return $di->getShared(SERVICE_TRANSACTION);
	}
	
	public static function getAppTransaction($app) {
		return $app->getDI()->getShared(SERVICE_TRANSACTION);
	}
	
	public static function getDiRedis($di) {
		return $di->getShared(SERVICE_REDIS);
	}
	
	public static function getAppRedis($app) {
		return $app->getDI()->getShared(SERVICE_REDIS);
	}
	
}
