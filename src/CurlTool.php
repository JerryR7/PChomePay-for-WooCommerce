<?php
/**
 * Created by PhpStorm.
 * User: river
 * Date: 2016/4/21
 * Time: 18:31
 */

namespace PCPayClient\Utils;


use RuntimeException;

class CurlTool
{
    public $ignoreSSL = false;

    private static $instance = null;

    /**
     * @return CurlTool
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new CurlTool();
        }

        return static::$instance;
    }

    public function __construct($ignoreSSL = false)
    {
        $this->ignoreSSL = $ignoreSSL;
    }

    //private $userAgent = 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36';

    /**
     * Http Get 呼叫
     *
     * @param $url
     * @param $params
     * @param array $headers
     * @param array $settings
     * @param int $timeout
     * @return mixed
     * @throws RuntimeException
     */
    public function get($url, $params, array $headers = null, array $settings = [], $timeout = 500)
    {
        $query = "?";

        if ($params !== null) {
            $query .= http_build_query($params);
        }

        $query .= "&xdebug_session_start=PHPSTORM";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        if ($headers !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($this->ignoreSSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        if (!empty($settings)) {
            foreach ($settings as $key => $value) {
                if (defined($key)) {
                    curl_setopt($ch, constant($key), $value);
                }
            }
        }

        $content = curl_exec($ch);

        $err = curl_errno($ch);

        if ($err) {
            $errMessage = "curl error => (" . $err . ")" . curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($errMessage);
        }

        curl_close($ch);
        return $content;
    }

    /**
     * @param $url
     * @param $params
     * @param array $headers
     * @param array $settings
     * @param int $timeout
     * @return mixed
     */
    public function post($url, $params, array $headers = null, array $settings = [], $timeout = 500)
    {
        $reqData = $this->parseReqData($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $reqData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if ($this->ignoreSSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        if ($headers !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($settings)) {
            foreach ($settings as $key => $value) {
                if (defined($key)) {
                    curl_setopt($ch, constant($key), $value);
                }
            }
        }

        $content = curl_exec($ch);

        $err = curl_errno($ch);

        if ($err) {
            $errMessage = "curl error => (" . $err . ")" . curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($errMessage);
        }

        curl_close($ch);
        return $content;
    }

    /**
     * @param $params
     * @return string
     */
    private function parseReqData($params)
    {
        $reqData = '';
        if (is_array($params) && !empty($params)) {
            foreach ($params as $key => $value) {
                $reqData .= "{$key}={$value}&";
            }
            $reqData = rtrim($reqData, '&');
        } else {
            $reqData = $params;
        }

        return $reqData;
    }


    /**
     * @param $url
     * @param $userAuth
     * @return string
     */
    public function postToken($userAuth, $url)
    {
        return $this->post($url, null, [], ["CURLOPT_USERPWD" => $userAuth]);
    }

    public function postAPI($token, $url, $data)
    {
        return $this->post($url, null, ["pcpay-token: {$token}"], ["CURLOPT_POSTFIELDS" => $data]);
    }

    /**
     * @param $data
     * @param $token
     * @param $url
     * @return string
     */
    public function getAPI($token, $url, $data)
    {
        return $this->get($url, $data, ["pcpay-token: $token"]);

    }
}