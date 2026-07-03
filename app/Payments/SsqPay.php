<?php

namespace App\Payments;

class SsqPay {
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
                'label' => 'TYPE(通道代码)',
                'description' => '三选一',
                'type' => 'input',
            ],
        ];
    }

public function pay($order)
{
    $request_time = (int) (microtime(true) * 1000);
    $nonce = bin2hex(random_bytes(8)); // 16位随机字符串

    // 1. 组装参数（严格对照文档）
    $params = [
        'mchKey' => $this->config['pid'],
        'mchOrderNo' => $order['trade_no'],
        'amount' => $order['total_amount'], // 单位分
        'product' => $this->config['type'], // 必须有值
        'nonce' => $nonce, // 必须有值
        'notifyUrl' => $order['notify_url'],
        'returnUrl' => $order['return_url'] ?? '', // 可选
        'timestamp' => $request_time,
    ];

    // 2. 按文档指定的顺序拼接字符串
    unset($params['sign']);
    foreach ($params as $k => $v) {
    if ($v === '' || $v === null) unset($params[$k]);
    }

    // 2️⃣ ASCII 升序排序
    ksort($params, SORT_STRING);

    // 3️⃣ 拼接参数字符串
    $signStr = '';
    foreach ($params as $k => $v) {
        $signStr .= $k . '=' . $v . '&';
    }
    $signStr = rtrim($signStr, '&');

    // 4️⃣ 拼接商户秘钥（⚠️ 按你文档是“直接拼接”）
    $signStr .= $this->config['key']; // 商户密钥
    

    // 5️⃣ MD5 生成 sign（不区分大小写）
    $params['sign'] = md5($signStr);


    // 4. 发起请求
    $url = rtrim($this->config['url']);
    $options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json;charset=UTF-8",
        'content' => json_encode($params, JSON_UNESCAPED_UNICODE),
        'timeout' => 30
        ]
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    // 5. 解析返回结果
    $result = json_decode($response, true);

    /*// 6. 写日志（签名字符串、本地签名、请求参数、返回内容）
    $log  = date('Y-m-d H:i:s') . PHP_EOL;
    $log .= "签名字符串: " . $signStr . PHP_EOL;
    $log .= "本地签名: " . $params['sign'] . PHP_EOL;
    $log .= "请求参数: " . print_r($params, true) . PHP_EOL;
    $log .= "原始响应: " . $response . PHP_EOL;
    $log .= "解析结果: " . print_r($result, true) . PHP_EOL;
    $log .= "----------------------------------------" . PHP_EOL;

    file_put_contents(__DIR__ . '/pay_debug.log', $log, FILE_APPEND);
    */
    // 7. 返回 pay_url（或保持旧风格）
    return [
        'type' => 1, // 0:qrcode 1:url
        'data' => $result['data']['url']['payUrl'] ?? ''
    ];
}




    public function notify($params)
{
    // 1. 取出签名
    $sign = $params['sign'] ?? '';


    // 2. 按文档规则拼接签名字符串
    unset($params['sign']);
    foreach ($params as $k => $v) {
    if ($v === '' || $v === null) unset($params[$k]);
    }

    // 2️⃣ ASCII 升序排序
    ksort($params, SORT_STRING);

    // 3️⃣ 拼接参数字符串
    $signStr = '';
    foreach ($params as $k => $v) {
        $signStr .= $k . '=' . $v . '&';
    }
    $signStr = rtrim($signStr, '&');

    // 4️⃣ 拼接商户秘钥（⚠️ 按你文档是“直接拼接”）
    $signStr .= $this->config['key']; // 商户密钥
    

    // 5️⃣ MD5 生成 sign（不区分大小写）
    $localSign = md5($signStr);


// 或者 strtoupper(md5($signStr));


    // 3. 验签失败
    if ($sign !== $localSign) {
        /*// 写日志方便排查
        file_put_contents(__DIR__ . '/shibainotify_debug.log',
            date('Y-m-d H:i:s') . PHP_EOL .
            "签名字符串: " . $signStr . PHP_EOL .
            "本地签名: " . $localSign . PHP_EOL .
            "平台签名: " . $sign . PHP_EOL .
            "参数: " . print_r($params, true) . PHP_EOL .
            "----------------------------------------" . PHP_EOL,
            FILE_APPEND
        );
        */
        return false;
    }
        
    // 4. 验签通过，返回处理结果（商户系统可用字段）
    if (isset($params['payStatus']) && $params['payStatus'] == 'SUCCESS') {
        
         /*       // 写日志方便排查
        file_put_contents(__DIR__ . '/notify_debug.log',
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
        'trade_no'    => $params['mchOrderNo'],  // 本地订单号
        'callback_no' => $params['serialOrderNo'],  // 平台回调订单号（如果平台有单独的 serialOrderNo，这里最好用那个）
    ];
    }

}

}
