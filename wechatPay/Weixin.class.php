<?php
/*
 * 小程序微信支付
 */
class WeixinPay{
    protected $appid;
    protected $mch_id;
    protected $key;
    protected $openid;
    protected $data;
    function __construct($appid,$openid,$mch_id,$key,$data){
        $this->appid=$appid;        //小程序id
        $this->openid=$openid;      //用户openid
        $this->mch_id=$mch_id;      //商户号
        $this->key=$key;
        $this->data = $data;
    }
    public function pay(){
        //统一下单接口
        $return=$this->weixinapp();
        return $return;
    }

    //统一下单接口
    private function unifiedorder(){
        $datas = $this->data;
        $url='https://api.mch.weixin.qq.com/pay/unifiedorder';

        $parameters=array(
            'appid'=>$this->appid,//小程序ID
            'mch_id'=>$this->mch_id,//商户号
            'nonce_str'=>$this->createNoncestr(),//随机字符串
            'body'=>$datas['itemdesc'],//商品描述
            'out_trade_no'=>$datas['ordernum'],//商户订单号
            'total_fee'=>floatval(0.01*100),//总金额 单位 分//测试成功后将0.0.1换成$datas['totalcost']
            'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],//终端IP
            //'notify_url'=>'http://www.weixin.qq.com/wxpay/pay.php',//支付完成回调地址url,不能带参数
            'notify_url'=>'https://'.$_SERVER['HTTP_HOST'].'/index.php/api/houseorder/wxnotify', //支付完成回调地址url,不能带参数
            'openid'=>$this->openid,//用户id
            'trade_type'=>'JSAPI'//交易类型
        );
        //统一下单签名
        $parameters['sign']=$this->getSign($parameters);
        $xmlData=$this->arrayToXml($parameters);      //数组转换成xml
        $xmlDataurl = $this->postXmlSSLCurl($xmlData,$url,60);
        $return= $this->xmlToArray($xmlDataurl);     //xml转换成数组

        /////可注释掉()
        //echo json_encode($return);die;
        return $return;
    }

    //微信小程序接口
    private function weixinapp(){
        //统一下单接口
        $unifiedorder=$this->unifiedorder();
        $parameters=array(
            'appId'=>$this->appid,//小程序ID
            'timeStamp'=>''.time().'',//时间戳
            'nonceStr'=>$this->createNoncestr(),//随机串
            'package'=>'prepay_id='.$unifiedorder['prepay_id'],//数据包
            'signType'=>'MD5'//签名方式
        );
        //签名
        $parameters['paySign']=$this->getSign($parameters);
        return $parameters;
    }

    //作用：产生随机字符串，不长于32位
    private function createNoncestr($length = 32 ){
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ ) {
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }
    //作用：生成签名
    private function getSign($Obj){
        foreach ($Obj as $k => $v){
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String."&key=".$this->key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }
    //作用：格式化参数，签名过程需要使用
    private function formatBizQueryParaMap($paraMap, $urlencode){
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v){
            if($urlencode)
            {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0){
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }


    private static function postXmlSSLCurl($xml, $url, $second)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);


        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }

    //数组转换成xml
    private function arrayToXml($arr) {
        $xml = "<root>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
        }
        $xml .= "</root>";
        return $xml;
    }

    //xml转换成数组
    private function xmlToArray($xml) {

        //禁止引用外部xml实体

        libxml_disable_entity_loader(true);


        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);


        $val = json_decode(json_encode($xmlstring), true);

        return $val;
    }


}