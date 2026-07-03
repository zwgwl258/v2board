<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ServerService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServerController extends Controller
{
    public function fetch(Request $request)
    {
        // === 获取真实 IP + UA 并保存 ===
        $ua = $request->header('User-Agent') ?? 'Unknown UA';

        $ip = $request->header('X-Forwarded-For') 
            ?? $request->header('X-Real-IP')
            ?? $request->header('CF-Connecting-IP')
            ?? $request->ip()
            ?? 'Unknown IP';

        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }

        $userId = $request->user['id'] ?? 'Guest';
        $time = date('Y-m-d H:i:s');

        // 过滤 UA：包含 Digilink 的不记录
        if (stripos($ua, 'Digilink') === false) {
            $logFile = __DIR__ . '/ua_log_' . date('Y-m-d') . '.txt';
            $logLine = "[{$time}] user_id={$userId} ip={$ip} ua=\"{$ua}\"\n";
            file_put_contents($logFile, $logLine, FILE_APPEND);
        }
        // ==================================================

        $user = User::find($request->user['id']);
        $servers = [];
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
        }

        // === 新增：根据 UA 条件过滤节点数据 ===
        // 如果 UA 中没有包含 Digilink，则只筛选出 type 为 trojan 的节点
        if (stripos($ua, 'Digilink') === false) {
            $filteredServers = [];
            foreach ($servers as $server) {
                // 根据 V2Board 的数据结构，兼容数组或对象形式的节点属性获取
                $type = is_array($server) ? ($server['type'] ?? '') : ($server->type ?? '');
                if ($type === 'trojan') {
                    $filteredServers[] = $server;
                }
            }
            $servers = $filteredServers;
        }
        // ==================================================

        // 重新计算过滤后的新 ETag，防止不同 UA 客户端之间产生 304 缓存冲突
        $eTag = sha1(json_encode(array_column($servers, 'cache_key')));
        if (strpos($request->header('If-None-Match'), $eTag) !== false ) {
            abort(304);
        }

        return response([
            'data' => $servers
        ])->header('ETag', "\"{$eTag}\"");
    }
}
