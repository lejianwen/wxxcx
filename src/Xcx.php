<?php
/**
 * Created by PhpStorm.
 * User: Lejianwen
 * Date: 2018/2/3
 * Time: 14:19
 */

namespace Ljw\Wxxcx;

use GuzzleHttp\Client;

class Xcx
{
    protected $appid;
    protected $secret;
    protected $error;
    protected $session;
    protected $decrypted_data;

    public function __construct($key, $secret)
    {
        $this->appid = $key;
        $this->secret = $secret;
    }

    /**
     * 使用code换取session_key
     * @param $code
     * @return bool|mixed
     * @author Lejianwen
     * {
     * "openid": "OPENID",
     * "session_key": "SESSIONKEY",
     * "unionid": "UNIONID"   只有绑定开放平台并且关注了公众号才能这样得到，不然需要授权并解密加密数据
     * }
     */
    public function session($code)
    {
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->appid}&secret={$this->secret}&js_code={$code}&grant_type=authorization_code";
        $client = new Client();
        $response = $client->get($url, ['timeout' => 30]);
        $body = $response->getBody();
        $result = json_decode($body, true);
        if (isset($result['errcode'])) {
            $this->error = $result['errmsg'];
            return false;
        }
        //如果不存在unionid， 赋值为null，在外面就可以直接判断，不然会提示index不存在
        if (!isset($result['unionid'])) {
            $result['unionid'] = null;
        }
        $this->session = $result;
        return $result;
    }

    /**
     * 解密小程序中的加密信息
     * @param $encryptedData
     * @param $iv
     * @return bool|mixed
     * @author Lejianwen
     * 返回数据示例
     * {
     * "openId": "OPENID",
     * "nickName": "NICKNAME",
     * "gender": GENDER,
     * "city": "CITY",
     * "province": "PROVINCE",
     * "country": "COUNTRY",
     * "avatarUrl": "AVATARURL",
     * "unionId": "UNIONID",
     * "watermark":
     * {
     * "appid":"APPID",
     * "timestamp":TIMESTAMP
     * }
     * }
     */
    public function decryptData($encryptedData, $iv)
    {
        $session_key = $this->session['session_key'];
        if (strlen($session_key) != 24) {
            $this->error = 'sessionKey length error!';
            return false;
        }
        $aesKey = base64_decode($session_key);
        if (strlen($iv) != 24) {
            $this->error = 'iv length error!';
            return false;
        }
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $dataObj = json_decode($result);
        if ($dataObj == null) {
            $this->error = 'decode data null!';
            return false;
        }
        if ($dataObj->watermark->appid != $this->appid) {
            $this->error = 'appid error!';
            return false;
        }
        $data = json_decode($result, true);
        $this->decrypted_data = $data;
        return $data;
    }
}