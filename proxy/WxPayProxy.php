<?php
namespace Fichat\Proxy;

class WxPayProxy {
	/*
	 * function buildXML 组建xml字符串
	 * @param $out_trade_no 订单号
	 * @param $ip 设备IP 
	 * @param $amount 交易金额
	 * @param $body 订单描述
	 *
	 * return xml 结构字符串
	 */
	public static  function buildXML($out_trade_no, $ip, $amount, $body = null, $type = 0){
		$AppID='wxae46d0de77beb081';
		$AppSecret='2b2679948a88755b7ba16833b783709d';
		$MerchantID='1486539082';
		$key='f318de0eb1e14b57921a979bdda3fecf';

		$nonce_str='123456781024567832165487';
        $notifyUrl= 'https://api.dakaapp.com/_API/_wxPayNotify';
// 		$time =data('YmdHis');
        $body = $body ? $body : '大咖社-充值';
        if ($type) {
            $sign = self::sign2($AppID, $MerchantID, $nonce_str, $key, $ip, $out_trade_no, $notifyUrl, $amount, $body);
            $xml = '<xml>
               <appid>'.$AppID.'</appid>
               <body>' .$body. '</body>
               <mch_id>'.$MerchantID.'</mch_id>
               <nonce_str>'.$nonce_str.'</nonce_str>
               <notify_url>'.$notifyUrl.'</notify_url>
               <out_trade_no>'.$out_trade_no.'</out_trade_no>
               <spbill_create_ip>'.$ip.'</spbill_create_ip>
               <total_fee>'.$amount.'</total_fee>
               <trade_type>MWEB</trade_type>
               <scene_info>{"h5_info":{"type":"IOS","app_name":"dakaapp","bundle_id":"com.yuanshuo.FiChat"}}</scene_info>
               <sign>'.$sign.'</sign>
            </xml>';
        } else {
            $sign=self::sign($AppID, $MerchantID, $nonce_str, $key, $ip, $out_trade_no, $notifyUrl, $amount, $body);
            $xml = '<xml>
               <appid>'.$AppID.'</appid>
               <body>' .$body. '</body>
               <mch_id>'.$MerchantID.'</mch_id>
               <nonce_str>'.$nonce_str.'</nonce_str>
               <notify_url>'.$notifyUrl.'</notify_url>
               <out_trade_no>'.$out_trade_no.'</out_trade_no>
               <spbill_create_ip>'.$ip.'</spbill_create_ip>
               <total_fee>'.$amount.'</total_fee>
               <trade_type>APP</trade_type>
               <sign>'.$sign.'</sign>
            </xml>';
        }
//		$xml = '<xml>
//		   <appid>'.$AppID.'</appid>
//		   <body>' .$body. '</body>
//		   <mch_id>'.$MerchantID.'</mch_id>
//		   <nonce_str>'.$nonce_str.'</nonce_str>
//		   <notify_url>'.$notifyUrl.'</notify_url>
//		   <out_trade_no>'.$out_trade_no.'</out_trade_no>
//		   <spbill_create_ip>'.$ip.'</spbill_create_ip>
//		   <total_fee>'.$amount.'</total_fee>
//		   <trade_type>'.$trade_type.'</trade_type>
//		   <sign>'.$sign.'</sign>
//		</xml>';
		return $xml;
	}

    // 设置公众号支付的请求数据
    public static  function buildPublicXML($out_trade_no, $ip, $openid, $amount, $body = null){
        $AppID='wx0ffe761fad278a0e';
        $AppSecret='2b2679948a88755b7ba16833b783709d';
        $MerchantID='1402741302';
        $key='14703e67beae816ab3787ef18ac5935d';

        $nonce_str='123456781024567832165487';
        $notifyUrl= 'https://api.dakaapp.com/_API/_wxPayPublicNotify';
// 		$time =data('YmdHis');
        $body = $body ? $body : '大咖社-充值';
        $sign = self::sign3($AppID, $MerchantID, $nonce_str, $key, $ip, $openid, $out_trade_no, $notifyUrl, $amount, $body);
        $xml = '<xml>
               <appid>'.$AppID.'</appid>
               <body>' .$body. '</body>
               <mch_id>'.$MerchantID.'</mch_id>
               <nonce_str>'.$nonce_str.'</nonce_str>
               <notify_url>'.$notifyUrl.'</notify_url>
               <out_trade_no>'.$out_trade_no.'</out_trade_no>
               <spbill_create_ip>'.$ip.'</spbill_create_ip>
               <total_fee>'.$amount.'</total_fee>
               <trade_type>JSAPI</trade_type>
               <openid>'.$openid.'</openid>
               <sign>'.$sign.'</sign>
            </xml>';
        return $xml;
    }

    /**
     * 生成签名
     *
     * @param $AppID
     * @param $MerchantID
     * @param $nonce_str
     * @param $key
     * @param $ip
     * @param $out_trade_no
     * @param $notifyUrl
     * @param $amount
     * @return string
     */
	public static function sign($AppID,$MerchantID,$nonce_str,$key,$ip,$out_trade_no,$notifyUrl,$amount, $body=null){
// 		$stringA="appid=$AppID&body=test&mch_id=$MerchantID&nonce_str=$nonce_str&spbill_create_ip=$ip";
        $body = $body ? $body : '大咖社-充值';
		$stringA="appid=$AppID&body=" . $body . "&mch_id=".$MerchantID."&nonce_str=".$nonce_str."&notify_url=".$notifyUrl."&out_trade_no=".$out_trade_no."&spbill_create_ip=".$ip."&total_fee=".$amount."&trade_type=APP";
		$stringSignTemp="$stringA&key=$key";

		$sign = strtoupper(MD5($stringSignTemp));
		return $sign;
	}

    public static function sign2($AppID,$MerchantID,$nonce_str,$key,$ip,$out_trade_no,$notifyUrl,$amount, $body=null){
// 		$stringA="appid=$AppID&body=test&mch_id=$MerchantID&nonce_str=$nonce_str&spbill_create_ip=$ip";
        $body = $body ? $body : '大咖社-充值';
        $scene_info = '&scene_info={"h5_info":{"type":"IOS","app_name":"dakaapp","bundle_id":"com.yuanshuo.FiChat"}}';
        $stringA="appid=$AppID&body=" . $body . "&mch_id=".$MerchantID."&nonce_str=".$nonce_str."&notify_url=".$notifyUrl."&out_trade_no=".$out_trade_no.$scene_info."&spbill_create_ip=".$ip."&total_fee=".$amount."&trade_type=MWEB";
        $stringSignTemp="$stringA&key=$key";
        $sign = strtoupper(MD5($stringSignTemp));
        return $sign;
    }

    // 生成公众号支付的签名
    public static function sign3($AppID, $MerchantID, $nonce_str, $key, $ip, $openid, $out_trade_no, $notifyUrl, $amount, $body=null){
        $body = $body ? $body : '大咖社-充值';
        $stringA="appid=$AppID&body=" . $body . "&mch_id=".$MerchantID."&nonce_str=".$nonce_str."&notify_url=".$notifyUrl."&openid=".$openid."&out_trade_no=".$out_trade_no."&spbill_create_ip=".$ip."&total_fee=".$amount."&trade_type=JSAPI";
        $stringSignTemp="$stringA&key=$key";

        $sign = strtoupper(MD5($stringSignTemp));
        return $sign;
    }


    /**
     * 生成app端参数
     *
     * @param $returnInfo
     * @return array
     */
	public static function mobileSign($returnInfo, $type = 0){
		$key='f318de0eb1e14b57921a979bdda3fecf';
		$appid =$returnInfo['appid'];
		$partnerid =$returnInfo['mch_id']; //商户号
		$prepayid =$returnInfo['prepay_id'];//预支付交易会话ID
		$package ='Sign=WXPay';//扩展字段（固定为Sign=WXPay）
		$noncestr =$returnInfo['nonce_str'];//随机字符串
		$timestamp =$returnInfo['timestamp'];
		//生成字符串
		$str="appid=$appid&noncestr=$noncestr&package=$package&partnerid=$partnerid&prepayid=$prepayid&timestamp=$timestamp";
		//链接商户KEY
		$strKey="appid=$appid&noncestr=$noncestr&package=$package&partnerid=$partnerid&prepayid=$prepayid&timestamp=$timestamp&key=$key";
		//生成签名
		$sign = strtoupper(MD5($strKey));
		$data = array('appid'=>$appid,'partnerid'=>$partnerid,'prepayid'=>$prepayid,'package'=>$package,'noncestr'=>$noncestr,'timestamp'=>$timestamp,'sign'=>$sign,);
		if ($type) {
            $data['mweb_url'] = $returnInfo['mweb_url'];
        }
		return $data;
	}

    /**
     * 获取参数签名；
     * @param  Array  // 要传递的参数数组
     * @return String 通过计算得到的签名；
     */
    public static function getSign($params) {
        unset($params['sign']);
        ksort($params);        //将参数数组按照参数名ASCII码从小到大排序
        foreach ($params as $key => $item) {
            if (!empty($item)) {         //剔除参数值为空的参数
                $newArr[] = $key.'='.$item;     // 整合新的参数数组
            }
        }
        $stringA = implode("&", $newArr);         //使用 & 符号连接参数
        $stringSignTemp = $stringA."&key=f318de0eb1e14b57921a979bdda3fecf";        //拼接key
        // key是在商户平台API安全里自己设置的
        $stringSignTemp = MD5($stringSignTemp);       //将字符串进行MD5加密
        $sign = strtoupper($stringSignTemp);      //将所有字符转换为大写
        return $sign;
    }

    public static function getWxPublicSign($params) {
        unset($params['sign']);
        ksort($params);        //将参数数组按照参数名ASCII码从小到大排序
        foreach ($params as $key => $item) {
            if (!empty($item)) {         //剔除参数值为空的参数
                $newArr[] = $key.'='.$item;     // 整合新的参数数组
            }
        }
        $stringA = implode("&", $newArr);         //使用 & 符号连接参数
        $stringSignTemp = $stringA."&key=14703e67beae816ab3787ef18ac5935d";        //拼接key
        // key是在商户平台API安全里自己设置的
        $stringSignTemp = MD5($stringSignTemp);       //将字符串进行MD5加密
        $sign = strtoupper($stringSignTemp);      //将所有字符转换为大写
        return $sign;
    }

}
