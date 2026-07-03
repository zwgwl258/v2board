<?php

namespace App\Payments;

use \Curl\Curl;

class SeaPay {
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'GateWay' => [
                'label' => 'ApiUrl',
                'description' => '请填写下单地址',
                'type' => 'input',
            ],
            'UserID' => [
                'label' => 'UserID',
                'description' => '请填写商户号',
                'type' => 'input',
            ],
            'UserKey' => [
                'label' => 'UserKey',
                'description' => '请填写商户秘钥',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $params = [
            'UserID' => $this->config['UserID'],
            'OrderNo' => $order['trade_no'],
            'TotalFee' => sprintf('%0.2f', ($order['total_amount'] / 100)),
			'RequestTime' => time(),
            'NotifyUrl' => $order['notify_url'],
            'CallBackUrl' => $order['return_url']    //单域名同步回调       与多域名仅可开启一个                        
            //'CallBackUrl' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/#/order/' . $order['trade_no']      //多域名同步回调
        ];
		$params['Sign'] = $this->sign($params, $this->config['UserKey']);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->config['GateWay']);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        $res = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($res, true);
        if (!$result) {
            abort(500, '网络异常');
        }
        if ($result['resultCode']) {
            abort(500, 'ERR: '.$result['message']);
        }
        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $result['data']['checkout']
        ];
    }

    private function sign(array $params, string $key){
        $args = array_filter($params, function ($i, $k){
            if($k != 'Sign' && !empty($i)) return true;
        }, ARRAY_FILTER_USE_BOTH);
        ksort($args);
        $str = urldecode(http_build_query($args));
        return md5($str . "&UserKey={$key}");
    }
    
    public function notify($params)
    {
        $sign = $params['Sign'];
		unset($params['Sign']);
		$str = $this->sign($params, $this->config['UserKey']);
        if ($sign !== $str) {
            return false;
        }
        if($params['Status'] == 'success'){
            return [
                'trade_no' => $params['OriginOrderNo'],
                'callback_no' => $params['OrderNo']
            ];
        }
    }
}