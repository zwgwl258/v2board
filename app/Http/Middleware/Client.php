<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Facades\Cache;

class Client
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // === 1. 优先提取真实 IP（支持 CDN/多级反代） ===
        $realIp = $request->header('X-Forwarded-For') 
            ?? $request->header('X-Real-IP')
            ?? $request->header('CF-Connecting-IP')
            ?? $request->ip()
            ?? 'Unknown IP';

        if (strpos($realIp, ',') !== false) {
            $realIp = explode(',', $realIp)[0];
        }

        // === 2. UA 基础检查 ===
        $ua = $request->header('User-Agent') ?? '';

        // ❌ 空 UA 拦截并记录
        if (empty($ua)) {
            $this->logRejectedUA('EMPTY UA', $realIp, $request); 
            abort(403, 'User-Agent is empty');
        }

        // ✅ UA 白名单
        $allowedUAs = [
            'NetFlow/v2.1.6 clash-verge Platform/android',
            'NetFlow/v2.1.6 clash-verge Platform/macos',
            'NetFlow/v2.1.6 clash-verge Platform/windows',
            'NetFlow/v2.1.6 clash-verge Platform/linux',
            
            'NetFlow/v2.1.7 clash-verge Platform/android',
            'NetFlow/v2.1.7 clash-verge Platform/macos',
            'NetFlow/v2.1.7 clash-verge Platform/windows',
            'NetFlow/v2.1.7 clash-verge Platform/linux'
        ];

        $isAllowed = false;
        foreach ($allowedUAs as $allowed) {
            if (stripos($ua, $allowed) !== false) {
                $isAllowed = true;
                break;
            }
        }

        // 💡 【核心修改】不在白名单的 UA，只记录日志，不再执行 abort(403) 阻断
        // 这样请求就可以流转到后面的控制器进行节点清洗
        if (!$isAllowed) {
            $this->logRejectedUA($ua, $realIp, $request); 
        }

        // 原系统 Token 验证逻辑保持不变
        $token = $request->input('token');
        if (empty($token)) {
            abort(403, 'token is null');
        }
        $submethod = (int)config('v2board.show_subscribe_method', 0);
        switch ($submethod) {
            case 0:
                break;
            case 1:
                if (!Cache::has("otpn_{$token}")) {
                    abort(403, 'token is error');
                }
                $usertoken = Cache::pull("otpn_{$token}");
                Cache::forget("otp_{$usertoken}");
                $token = $usertoken;
                break;
            case 2:
                $usertoken = Cache::get("totp_{$token}");
                if (!$usertoken) {
                    $timestep = (int)config('v2board.show_subscribe_expire', 5) * 60;
                    $counter = floor(time() / $timestep);
                    $counterBytes = pack('N*', 0) . pack('N*', $counter);
                    $idhash = Helper::base64DecodeUrlSafe($token);
                    if (strpos($idhash, ':') === false) {
                        abort(403, 'token is error');
                    }
                    $parts = explode(':', $idhash, 2);
                    [$userid, $clienthash] = $parts;
                    if (!$userid || !$clienthash) {
                        abort(403, 'token is error');
                    }
                    $user = User::where('id', $userid)->select('token')->first();
                    if (!$user) {
                        abort(403, 'token is error');
                    }
                    $usertoken = $user->token;
                    $hash = hash_hmac('sha1', $counterBytes, $usertoken, false);
                    if ($clienthash !== $hash) {
                        abort(403, 'token is error');
                    }
                    Cache::put("totp_{$token}", $usertoken, $timestep);
                }
                $token = $usertoken;
                break;
            default:
                break;
        }
        $user = User::where('token', $token)->first();
        if (!$user) {
            abort(403, 'token is error');
        }

        $request->merge([
            'user' => $user
        ]);
        return $next($request);
    }

    /**
     * 记录被拒绝的 UA 日志（统一存入单个文件，直接记录 token）
     */
    private function logRejectedUA($ua, $ip, $request)
    {
        // 直接获取请求中的 token，如果没有则显示 No Token
        $token = $request->input('token') ?? 'No Token';

        $logFile = __DIR__ . '/ua_block.txt';
        $time = date('Y-m-d H:i:s');
        $logLine = "[{$time}] token={$token} ip={$ip} ua=\"{$ua}\"\n";
        file_put_contents($logFile, $logLine, FILE_APPEND);
    }   
}