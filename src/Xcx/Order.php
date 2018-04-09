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
    protected $ssl_key;
    protected $cert;
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

    /**
     * postXml
     * @param $url
     * @param $xml
     * @param bool $need_cert 是否需要证书
     * @return \Psr\Http\Message\StreamInterface
     * @throws \Exception
     * @author Lejianwen
     */
    protected function postXml($url, $xml, $need_cert = false)
    {
        $client = new Client();
        $options = ['timeout' => 30, 'body' => $xml, 'verify' => false];
        if ($need_cert) {
            if (!$this->cert || !$this->ssl_key) {
                throw new \Exception('请设置微信证书和ssl_key');
            }
            $options['cert'] = $this->cert;
            $options['ssl_key'] = $this->ssl_key;
        }
        $response = $client->post($url, $options);
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

    /**
     * 申请退款
     * @param String $transaction_id 微信生成的订单号
     * @param String $out_refund_no 商户系统内部的退款单号
     * @param int $total_fee 订单总金额
     * @param int $refund_fee 退款总金额
     * @param string $refund_desc 退款理由
     * @param string $refund_fee_type 退款货币种类，默认人名币
     * @return array|bool
     * @throws \Exception
     * @author Lejianwen
     */
    public function refuseOrder($transaction_id, $out_refund_no, $total_fee, $refund_fee, $refund_desc = '', $refund_fee_type = 'CNY')
    {
        if (!$transaction_id) {
            return false;
        }
        $arr = [
            'appid'           => $this->xcx->getAppId(),
            'mch_id'          => $this->mch_id,
            'sign_type'       => 'MD5',
            'nonce_str'       => md5(time()),
            'transaction_id'  => $transaction_id,
            'out_refund_no'   => $out_refund_no,
            'total_fee'       => $total_fee,
            'refund_fee'      => $refund_fee,
            'refund_desc'     => $refund_desc,
            'refund_fee_type' => $refund_fee_type,
        ];
        $sign = $this->MakeSign($arr);
        $arr['sign'] = $sign;
        $xml = $this->handler->ToXml($arr);
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
        $response = $this->postXml($url, $xml, true);
        $re_arr = $this->handler->fromXml($response);
        if ($re_arr['return_code'] == 'FAIL') {
            throw new \Exception($re_arr['return_msg']);
        }
        if (isset($re_arr['err_code'])) {
            throw new \Exception($re_arr['err_code_des']);
        }
        if ($re_arr['return_code'] != 'SUCCESS' || $re_arr['result_code'] != 'SUCCESS') {
            throw new \Exception('微信申请退款失败');
        }
        return $re_arr;
    }

    public function setSslKey($file_path)
    {
        $this->ssl_key = $file_path;
        return $this;
    }

    public function setCert($file_path)
    {
        $this->cert = $file_path;
        return $this;
    }
}