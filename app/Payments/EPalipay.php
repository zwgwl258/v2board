<?php

namespace App\Payments;

class EPalipay {
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'url' => [
                'label' => 'URL',
                'description' => '',
                'type' => 'input',
            ],
            'pid' => [
                'label' => 'PID',
                'description' => '',
                'type' => 'input',
            ],
            'key' => [
                'label' => 'KEY',
                'description' => '',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $params = [
            'device' => 'pc',
            'clientip' => '192.168.1.100',
            'type' => 'alipay',
            'money' => $order['total_amount'] / 100,
            'name' => $order['trade_no'],
            'notify_url' => $order['notify_url'],
            'return_url' => $order['return_url'],
            'out_trade_no' => $order['trade_no'],
            'pid' => $this->config['pid']
        ];
        ksort($params);
        reset($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->config['key'];
        
        $params['sign'] = md5($str);
        $params['sign_type'] = 'MD5';

        $ch = curl_init($this->config['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        // 执行 cURL 请求
        $res = curl_exec($ch);
        $result = json_decode($res, true);

        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $result['payurl']
        ];
    }

    public function notify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        unset($params['sign_type']);
        ksort($params);
        reset($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->config['key'];
        if ($sign !== md5($str)) {
            return false;
        }
        return [
            'trade_no' => $params['out_trade_no'],
            'callback_no' => $params['trade_no']
        ];
    }
}
