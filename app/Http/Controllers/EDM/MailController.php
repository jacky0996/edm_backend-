<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Jobs\Common\SendAwsMailJob;
use App\Models\EDM\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MailController extends Controller
{
    /**
     * 發送邀請信件
     *
     * @param  Request  $request  包含 event_id
     */
    public function inviteMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:event,id',
            'emails' => 'required|array|min:1',
            'emails.*' => 'email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = Event::find($request->input('event_id'));
        $emails = $request->input('emails');

        // 準備寄信清單 (Validator 已過濾掉非法 email，這裡再保險過濾一次)
        $mailChunk = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mailChunk[] = [
                    'email' => $email,
                    'subject' => $event->title,
                    'body' => $event->content,
                ];
            }
        }

        if (empty($mailChunk)) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到有效的電子郵件地址']);
        }

        SendAwsMailJob::dispatch($mailChunk);

        return response()->json([
            'code' => 0,
            'status' => true,
            'message' => '邀請信件已成功加入發送隊列',
            'count' => count($mailChunk),
        ]);
    }
}
