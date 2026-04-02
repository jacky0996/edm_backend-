<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthorizeJwt
{
    /**
     * 驗證請求標頭中的 JWT Token。
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. 從標頭獲取 Authorization: Bearer <token>
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            Log::debug('JWT Middleware: Token missing or format wrong in header');
            return response()->json(['message' => 'Authorization Token not found'], 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            // 2. 準備解密金鑰 (使用專案 APP_KEY)
            $key = config('app.key');
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            // 3. 嘗試解碼驗證 (HS256 演算法)
            $decoded = JWT::decode($token, new Key($key, 'HS256'));

            // 4. 將解碼後的使用者資訊暫存進 Request
            $request->merge(['auth' => (array) $decoded]);

            return $next($request);

        } catch (Exception $e) {
            // 印出最詳細的失敗原因，幫助我們判斷金鑰是否匹配
            Log::error('JWT Middleware FAILURE: ' . $e->getMessage(), [
                'token_sample' => substr($token, 0, 15) . '...',
                'secret_length' => strlen($key),
            ]);

            return response()->json([
                'message' => 'Unauthorized: ' . $e->getMessage(),
                'error_type' => get_class($e)
            ], 401);
        }
    }
}
