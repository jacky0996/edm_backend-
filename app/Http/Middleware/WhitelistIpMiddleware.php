<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WhitelistIpMiddleware
{
    /**
     * 處理傳入的中繼伺服器請求。
     * 阻擋非白名單來源的外部呼叫，擔任核心系統防火牆。
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $allowedIps = config('sso.allowed_edm_ips');
        $clientIp = $request->ip();

        // 支援星號代表不限制 IP (因為前端可能是預設的瀏覽器打 API)
        if (in_array('*', $allowedIps)) {
            return $next($request);
        }

        // 以逗號分隔比對是否為允許的主機 IP
        if (! in_array($clientIp, $allowedIps)) {
            return response()->json([
                'message' => 'Forbidden: API access from '.$clientIp.' is restricted.',
                'error' => 'IP Validation Failed',
            ], 403);
        }

        return $next($request);
    }
}
