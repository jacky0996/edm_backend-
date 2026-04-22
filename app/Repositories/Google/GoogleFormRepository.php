<?php

namespace App\Repositories\Google;

use App\Models\Google\GoogleForm;
use App\Models\Google\GoogleFormResponse;
use App\Repositories\Repository;
use App\Repositories\RepositoryTrait;

/**
 * Google 表單資料儲存庫
 *
 * 負責 GoogleForm / GoogleFormResponse 的持久化與查詢，
 * 讓 Service 層可專注於業務邏輯 (與 Google API 互動)。
 */
class GoogleFormRepository extends Repository
{
    use RepositoryTrait;

    public function model(): string
    {
        return GoogleForm::class;
    }

    /**
     * 依 event_id 取得綁定的 Google 表單
     */
    public function findByEventId($eventId): ?GoogleForm
    {
        return GoogleForm::where('event_id', $eventId)->first();
    }

    /**
     * 依主鍵取得 Google 表單
     */
    public function findById($id): ?GoogleForm
    {
        return GoogleForm::find($id);
    }

    /**
     * 依 id 或 event_id 取得 Google 表單 (優先使用 id)
     */
    public function findByIdOrEventId($id, $eventId): ?GoogleForm
    {
        if ($id) {
            return GoogleForm::find($id);
        }

        if ($eventId) {
            return $this->findByEventId($eventId);
        }

        return null;
    }

    /**
     * 建立 Google 表單綁定紀錄
     */
    public function createForm(array $data): GoogleForm
    {
        return GoogleForm::create($data);
    }

    /**
     * 刪除 (軟刪除) 指定的 Google 表單綁定
     */
    public function deleteForm(GoogleForm $googleForm): bool
    {
        return (bool) $googleForm->delete();
    }

    /**
     * 依 Google 回覆 ID 取得回覆紀錄
     */
    public function findResponseByGoogleId(string $googleResponseId): ?GoogleFormResponse
    {
        return GoogleFormResponse::where('google_response_id', $googleResponseId)->first();
    }

    /**
     * 更新回覆審核狀態
     */
    public function updateResponseStatus(GoogleFormResponse $response, $status): GoogleFormResponse
    {
        $response->status = $status;
        $response->save();

        return $response;
    }

    /**
     * 將活動內所有「待審」的回覆批次設為通過
     *
     * 使用情境：活動由「需審核」切換為「不需審核」時自動放行。
     */
    public function approvePendingByEventId($eventId): int
    {
        return GoogleFormResponse::where('event_id', $eventId)
            ->where('status', 0)
            ->update(['status' => 1]);
    }
}
