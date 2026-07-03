<?php

namespace App\Payments;

class HtPay {
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
            ],
            'type' => [
                'label' => 'TYPE(alipay/wxpay/usdt)',
                'description' => '三选一',
                'type' => 'input',
            ],
        ];
    }

public function pay($order)
{
    $request_time = time();

    // 1. 组装参数（严格对照文档）
    $params = [
        'appid' => $this->config['pid'],
        'merchant_order_number' => $order['trade_no'],
        'payment_amount' => number_format($order['total_amount'] / 100, 2, '.', ''), // 保留两位小数
        'payment_channel' => $this->config['type'], // 必须有值
        'notify_url' => $order['notify_url'],
        'return_url' => $order['return_url'] ?? '', // 可选
        'request_time' => $request_time,
    ];

    // 2. 按文档指定的顺序拼接字符串
    $signStr =
        $params['appid'] .
        $params['merchant_order_number'] .
        $params['payment_amount'] .
        $params['payment_channel'] .
        $params['notify_url'] .
        $params['return_url'] .
        $params['request_time'] .
        $this->config['key'];

    // 3. 生成签名
    $params['sign'] = md5($signStr);

    // 4. 发起请求
    $url = rtrim($this->config['url'], '/') . '/v1/alipay/order/create';
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded",
            'content' => http_build_query($params)
        ]
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    // 5. 解析返回结果
    $result = json_decode($response, true);

   /* // 6. 写日志（签名字符串、本地签名、请求参数、返回内容）
    $log  = date('Y-m-d H:i:s') . PHP_EOL;
    $log .= "签名字符串: " . $signStr . PHP_EOL;
    $log .= "本地签名: " . $params['sign'] . PHP_EOL;
    $log .= "请求参数: " . print_r($params, true) . PHP_EOL;
    $log .= "原始响应: " . $response . PHP_EOL;
    $log .= "解析结果: " . print_r($result, true) . PHP_EOL;
    $log .= "----------------------------------------" . PHP_EOL;

    file_put_contents(__DIR__ . '/htpay_debug.log', $log, FILE_APPEND);  
    */
    // 7. 返回 pay_url（或保持旧风格）
    return [
        'type' => 1, // 0:qrcode 1:url
        'data' => $result['data']['pay_url'] ?? ''
    ];
}




    public function notify($params)
{
    // 1. 取出签名
    $sign = $params['sign'] ?? '';
    unset($params['sign']);

    // 2. 按文档规则拼接签名字符串
    // ⚠️ 这里要和下单时的签名规则保持一致
    $signStr =
    $this->config['pid'].
    $params['merchant_order_number'] .
    $params['payment_amount'] .
    $params['payment_status'] .
    $params['created_at'] .
    $params['order_success_time'] .
    $params['order_expiry_time'] .
    $this->config['key'];

$localSign = md5($signStr);
// 或者 strtoupper(md5($signStr));


    // 3. 验签失败
    if ($sign !== $localSign) {
        // 写日志方便排查
        file_put_contents(__DIR__ . '/htnotify_debug.log',
            date('Y-m-d H:i:s') . PHP_EOL .
            "签名字符串: " . $signStr . PHP_EOL .
            "本地签名: " . $localSign . PHP_EOL .
            "平台签名: " . $sign . PHP_EOL .
            "参数: " . print_r($params, true) . PHP_EOL .
            "----------------------------------------" . PHP_EOL,
            FILE_APPEND
        );
        return false;
    }
        
    // 4. 验签通过，返回处理结果（商户系统可用字段）
    if (isset($params['payment_status']) && $params['payment_status'] == '3') {
        
        /*        // 写日志方便排查
        file_put_contents(__DIR__ . '/htnotify_debug.log',
            date('Y-m-d H:i:s') . PHP_EOL .
            "签名字符串: " . $signStr . PHP_EOL .
            "本地签名: " . $localSign . PHP_EOL .
            "平台签名: " . $sign . PHP_EOL .
            "参数: " . print_r($params, true) . PHP_EOL .
            "----------------------------------------" . PHP_EOL,
            FILE_APPEND
            );
        */
    return [
        'trade_no'    => $params['merchant_order_number'],  // 本地订单号
        'callback_no' => $params['payment_order_number'],  // 平台回调订单号（如果平台有单独的 payment_order_number，这里最好用那个）
    ];
    }

}

}
