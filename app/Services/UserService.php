<?php

namespace App\Services;

use Illuminate\Http\Request;

class UserService
{
    /**
     * 從 Request Header 的 X-User-Info 欄位獲取經 Base64 解碼後的使用者資料
     */
    public function getUserFromHeader(Request $request): ?array
    {
        $encodedUserInfo = $request->header('X-User-Info');

        if (! $encodedUserInfo) {
            return null;
        }

        try {
            // 1. Base64 解碼
            $decodedJson = base64_decode($encodedUserInfo);

            // 2. JSON 轉回陣列
            $userInfo = json_decode($decodedJson, true);

            // 3. 確保解碼成功且結構正確
            if (is_array($userInfo)) {
                return $userInfo;
            }
        } catch (\Exception $e) {
            // 解析失敗時回傳 null
            return null;
        }

        return null;
    }

    /**
     * 快速取得當前呼叫者的 UID
     *
     * @return mixed|null
     */
    public function getUserId(Request $request)
    {
        $user = $this->getUserFromHeader($request);

        return $user['uid'] ?? $user['userId'] ?? null;
    }

    /**
     * 快速取得當前呼叫者的姓名
     */
    public function getUserName(Request $request): string
    {
        $user = $this->getUserFromHeader($request);

        return $user['realName'] ?? $user['name'] ?? 'Guest';
    }
}
