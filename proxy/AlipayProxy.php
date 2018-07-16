<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-7-24
 * Time: 下午4:21
 */
namespace Fichat\Proxy;

class AlipayProxy
{

    /**
     * 支付宝创建订单
     *
     * @param $orderId
     * @param $amount
     * @return string
     */
    public static function request($orderId, $amount, $body=null)
    {
        require_once "lib/alipay/AopSdk.php";

        $aop = new \AopClient;

        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2017072407874850";
        $aop->rsaPrivateKey =  "MIIEpAIBAAKCAQEAyZFQ6Ik2klb/HfiB4TWhq21fnrB0z3Ss8WnOV4qIql1jLcG8c/hz3ERixMuPi9lKIwwsS2dGZLXS2TT1cOyn3wtyva4oldW5lbsyFOQzuE741MLFY0EfkcsanlGRKm0sKOSgLpp2iA0SZZIYyepm+5X4ymGLpXS0EjVTyupoC6waHbbI5kZcOkN3KcJ07bybt1cudPvtIDdfj/NLkHBWXE8SHOHQ+lXoIkQr4EspxaMOReQ+kI3YJqDI424NbFwRjSu4DpLolPGCeIHfbUX4jLh1WzifCsys5JsDHHriB5Rk1QBrROUEFhB+DcgcU9xr/SeqHYDzE0a+iJ/VwhiFiwIDAQABAoIBAQCGbsmUXTctQKJjncMZrchGaerVDoDJf9p8JAH/dqCRZzlnYgfLHNBA/scU02LIdcIxP8QnAhj7wByAywZLKzsG0j7TbN0amXFuAGx1bIFmEh144PH+sYdZmCkHoAT+U9OY7mo6yr/1GyrC3T51JEVM1AgbChW14vDaGADrm4yLAy0V8seuk2Ae+JBOUwr+n++dxOrbFUT9gu3klCw/3VB/EsxTs5AxYVCseHmLG+2gYd5SKUoZCJ0/E1fR6UH1ZC/NKS00tBD7kZfouRgIk/T4ZJagCQAwc6/hOPQnUtAfsjsVvP8dmCp07B1SGezDo+tVk9PiOE5zdlCyiAo0jmhBAoGBAO12haOfSoOkU5KNgYpsm+mWZhsYAKf01PoHFBkUVDzySbtKj6uNtFnul11EiYZzVuc+psH/Syd31fRCWzNL1CPfmntvhIid7S23AVrnLKvO6cZrLTp4iPtIlEOyWAJcSIidOdA0YljJyc3bgnii/B4jjykbc/oc6BPY2+/A9UQhAoGBANlNdXx9lOUZkrm0B9aoQhit3iL1zipnlozTY5gFJYhcBWa23UKjk+QWxTB33kkkBOGhJomk3wP4T4yHb/1RRvldylbv6Mf5ZDY9TjbK8OtBwSvcsIh+ufpn15pqMerh0/hsysnVmZNCudt1zQRZpSY+GvbNU1nlTdnA8HKdI5QrAoGBAMIGvulT5YGsynCL2RXu6nIxZpqqwRZ7UW4+TGi1mKL34dD7+mpVbdCxx9H4h1ppcc+e/Ii0/YZxP0vG0FgYlGz/bm1/UE6Eo+BfkObizzhO5+stdZY6GMshoauy1ICRQN8HgM6jjtw3fQIMYw4JNnG14mrXOKtb5TEaV5MOGhmhAoGAAbvvgxXReV6R4C+CnIDtPhstGaRSh94ZwnfxZIYt/X+Wf3JYI68AgCJ6Yl+ig3zpGQ/bKeAJ85Mot1thVmkuotDPy4/QkL/5v8EPfbpy372/l+UD96LjQw9PFilgypoQwfvERoYQ2q7+orS29SDuA3cdafjLgH0m+OTkRlXgsc8CgYBMHBcN93kLhQf8JUv5/184/+jf7P0oLwpVl+BE+AiPiVy/96tDoiQQoZS/yOdkr7KxohRV8d8UcKdpAasAXY5fA3f5R5t53ppFPgPfHu2QvjapJ/ChoSA+xXQ12ZnxaXxG0FZ17a76Q9Yi11PaBfqepmiXrp17/k/7IVl0/lHo0Q==";
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        $aop->alipayrsaPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4czu3avrl9mEBJsgOqNQhWL2I0BM3WLG0b8t16TFAN4UqmwgNSkgk5z4Ls8xhqHDHMaXCOHdOkupy1TR7mDhQ83juNlao/ER+hjaCbpaaEZewARM/nV2c9WL9nccSarMMZ7k5fNkZYYnKyM5YOxjpdhI5joMA1/2Qzhdqhl2UNCAsJMvKrIA1MwYUsFx9FqLZbw8g7JVlfj9ifI2N3IsGJcTIdovin5Ebmz+b+vcq2QsMK2aJmfumec1unnOWdMadAaFqAKBuZxWlaCxywYWcPZRln5QJ9RZ5i2tGUeeS9qvPasc6+fP/hwIjY1WRYgf5JjvoI8TkmZn+5KIeb4xFQIDAQAB";

        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new \AlipayTradeAppPayRequest;
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent = "{\"body\":\"充值\","
            . "\"subject\":\"大咖-充值\","
            . "\"out_trade_no\":\"$orderId\","
            . "\"timeout_express\":\"30m\","
            . "\"total_amount\":\"$amount\","
            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
            . "}";

        $request->setNotifyUrl("https://api.dakaapp.com/_API/_aliPayNotify");
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);

        return $response;
    }

    /**
     * 支付宝提现
     *
     * @param $orderId
     * @param $account
     * @param $amount
     * @return bool
     */
    public static function transfer($orderId, $account, $amount)
    {
        require_once "lib/alipay/AopSdk.php";

        $aop = new \AopClient;
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = '2017072407874850';
        $aop->rsaPrivateKey =  "MIIEpAIBAAKCAQEAyZFQ6Ik2klb/HfiB4TWhq21fnrB0z3Ss8WnOV4qIql1jLcG8c/hz3ERixMuPi9lKIwwsS2dGZLXS2TT1cOyn3wtyva4oldW5lbsyFOQzuE741MLFY0EfkcsanlGRKm0sKOSgLpp2iA0SZZIYyepm+5X4ymGLpXS0EjVTyupoC6waHbbI5kZcOkN3KcJ07bybt1cudPvtIDdfj/NLkHBWXE8SHOHQ+lXoIkQr4EspxaMOReQ+kI3YJqDI424NbFwRjSu4DpLolPGCeIHfbUX4jLh1WzifCsys5JsDHHriB5Rk1QBrROUEFhB+DcgcU9xr/SeqHYDzE0a+iJ/VwhiFiwIDAQABAoIBAQCGbsmUXTctQKJjncMZrchGaerVDoDJf9p8JAH/dqCRZzlnYgfLHNBA/scU02LIdcIxP8QnAhj7wByAywZLKzsG0j7TbN0amXFuAGx1bIFmEh144PH+sYdZmCkHoAT+U9OY7mo6yr/1GyrC3T51JEVM1AgbChW14vDaGADrm4yLAy0V8seuk2Ae+JBOUwr+n++dxOrbFUT9gu3klCw/3VB/EsxTs5AxYVCseHmLG+2gYd5SKUoZCJ0/E1fR6UH1ZC/NKS00tBD7kZfouRgIk/T4ZJagCQAwc6/hOPQnUtAfsjsVvP8dmCp07B1SGezDo+tVk9PiOE5zdlCyiAo0jmhBAoGBAO12haOfSoOkU5KNgYpsm+mWZhsYAKf01PoHFBkUVDzySbtKj6uNtFnul11EiYZzVuc+psH/Syd31fRCWzNL1CPfmntvhIid7S23AVrnLKvO6cZrLTp4iPtIlEOyWAJcSIidOdA0YljJyc3bgnii/B4jjykbc/oc6BPY2+/A9UQhAoGBANlNdXx9lOUZkrm0B9aoQhit3iL1zipnlozTY5gFJYhcBWa23UKjk+QWxTB33kkkBOGhJomk3wP4T4yHb/1RRvldylbv6Mf5ZDY9TjbK8OtBwSvcsIh+ufpn15pqMerh0/hsysnVmZNCudt1zQRZpSY+GvbNU1nlTdnA8HKdI5QrAoGBAMIGvulT5YGsynCL2RXu6nIxZpqqwRZ7UW4+TGi1mKL34dD7+mpVbdCxx9H4h1ppcc+e/Ii0/YZxP0vG0FgYlGz/bm1/UE6Eo+BfkObizzhO5+stdZY6GMshoauy1ICRQN8HgM6jjtw3fQIMYw4JNnG14mrXOKtb5TEaV5MOGhmhAoGAAbvvgxXReV6R4C+CnIDtPhstGaRSh94ZwnfxZIYt/X+Wf3JYI68AgCJ6Yl+ig3zpGQ/bKeAJ85Mot1thVmkuotDPy4/QkL/5v8EPfbpy372/l+UD96LjQw9PFilgypoQwfvERoYQ2q7+orS29SDuA3cdafjLgH0m+OTkRlXgsc8CgYBMHBcN93kLhQf8JUv5/184/+jf7P0oLwpVl+BE+AiPiVy/96tDoiQQoZS/yOdkr7KxohRV8d8UcKdpAasAXY5fA3f5R5t53ppFPgPfHu2QvjapJ/ChoSA+xXQ12ZnxaXxG0FZ17a76Q9Yi11PaBfqepmiXrp17/k/7IVl0/lHo0Q==";
        $aop->alipayrsaPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4czu3avrl9mEBJsgOqNQhWL2I0BM3WLG0b8t16TFAN4UqmwgNSkgk5z4Ls8xhqHDHMaXCOHdOkupy1TR7mDhQ83juNlao/ER+hjaCbpaaEZewARM/nV2c9WL9nccSarMMZ7k5fNkZYYnKyM5YOxjpdhI5joMA1/2Qzhdqhl2UNCAsJMvKrIA1MwYUsFx9FqLZbw8g7JVlfj9ifI2N3IsGJcTIdovin5Ebmz+b+vcq2QsMK2aJmfumec1unnOWdMadAaFqAKBuZxWlaCxywYWcPZRln5QJ9RZ5i2tGUeeS9qvPasc6+fP/hwIjY1WRYgf5JjvoI8TkmZn+5KIeb4xFQIDAQAB";
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new \AlipayFundTransToaccountTransferRequest;
        $request->setBizContent("{" .
            "\"out_biz_no\":\"$orderId\"," .
            "\"payee_type\":\"ALIPAY_LOGONID\"," .
            "\"payee_account\":\"$account\"," .
            "\"amount\":\"$amount\"," .
            "\"remark\":\"提现\"" .
            "  }");
        $result = $aop->execute ( $request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode) && $resultCode == 10000){
            return $result->$responseNode;
        } else {
            return $result->$responseNode;
        }
    }
}
