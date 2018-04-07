<?php
/**
 * Created by PhpStorm.
 * User: Lejianwen
 * Date: 2018/2/12
 * Time: 10:10
 */

namespace Ljw\Wxxcx\Xcx;

use GuzzleHttp\Client;
use Ljw\Wxxcx\Xcx;

class Order
{
    protected $xcx;
    protected $mch_id;
    protected $key;
    public $handler;
    public $error;

    public function __construct(Xcx $xcx)
    {
        $this->xcx = $xcx;
        $this->handler = new OrderHandler();
    }

    public function setMchID($mch_id)
    {
        $this->mch_id = $mch_id;
        return $this;
    }

    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * 统一下单
     * @param $openid
     * @param $order_no
     * @param $price
     * @param string $notify
     * @param string $body
     * @param string $detail
     * @return array|bool
     * @throws \Exception
     * @author Lejianwen
     */
    public function unifiedOrder($openid, $order_no, $price, $notify, $body = '', $detail = '')
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $xml = $this->makeOrderXml($openid, $order_no, $price, $notify, $body, $detail);
        if (!$xml) {
            throw new \Exception('下单参数有误');
        }
        $response = $this->postXml($url, $xml);
        $re_arr = $this->handler->fromXml($response);
        if ($re_arr['return_code'] == 'FAIL') {
            throw new \Exception($re_arr['return_msg']);
        }
        if (isset($re_arr['err_code'])) {
            throw new \Exception($re_arr['err_code_des']);
        }
        if ($re_arr['return_code'] != 'SUCCESS' || $re_arr['result_code'] != 'SUCCESS') {
            throw new \Exception('微信统一下单失败');
        }
        return $re_arr;
    }

    /**
     * 小程序支付签名计算
     * @param $prepay_id
     * @return array
     * @author Lejianwen
     */
    public function xcxSign($prepay_id)
    {
        $arr = [
            'appId'     => $this->xcx->getAppId(),
            'nonceStr'  => md5(time()),
            'package'   => 'prepay_id=' . $prepay_id,
            'signType'  => 'MD5',
            'timeStamp' => time(),
        ];
        $arr['paySign'] = $this->MakeSign($arr);
        return $arr;
    }

    public function queryOrder($transaction_id)
    {
        $arr = [
            'appid'          => $this->xcx->getAppId(),
            'mch_id'         => $this->mch_id,
            'transaction_id' => $transaction_id,
            'nonce_str'      => md5(time()),
            'sign_type'      => 'MD5'
        ];
        $arr['sign'] = $this->MakeSign($arr);
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        $xml = $this->handler->ToXml($arr);
        $response = $this->postXml($url, $xml);
        $re_arr = $this->handler->fromXml($response);
        if ($re_arr['return_code'] == 'SUCCESS' && $re_arr['result_code'] == 'SUCCESS') {
            return $re_arr;
        }
        return false;
    }

    protected function makeOrderXml($openid, $order_no, $price, $notify, $body = '', $detail = '')
    {
        if ($price <= 0) {
            throw new \Exception('价格错误，小于等于0');
        }
        $arr = [
            'appid'            => $this->xcx->getAppId(),
            'mch_id'           => $this->mch_id,
            'nonce_str'        => md5(time()),
            'notify_url'       => $notify,
            'body'             => $body,
            'detail'           => $detail,
            'out_trade_no'     => $order_no,
            'total_fee'        => $price,
            'spbill_create_ip' => '127.0.0.1',
            'trade_type'       => 'JSAPI',
            'openid'           => $openid
        ];
        $sign = $this->MakeSign($arr);
        $arr['sign'] = $sign;
        $xml = $this->handler->ToXml($arr);
        return $xml;
    }

    protected function postXml($url, $xml)
    {
        $client = new Client();
        $response = $client->post($url, ['timeout' => 30, 'body' => $xml]);
        $body = $response->getBody();
        return $body;
    }

    /**
     * 生成签名
     * @return $result 签名
     */
    public function MakeSign($arr)
    {
        //签名步骤一：按字典序排序参数
        ksort($arr);
        $string = $this->handler->ToUrlParams($arr);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $this->key;

        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
}