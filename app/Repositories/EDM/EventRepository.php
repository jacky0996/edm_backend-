<?php

namespace App\Repositories\EDM;

use App\Models\EDM\Event;
use App\Models\Google\GoogleForm;
use App\Models\Google\GoogleFormResponse;
use App\Repositories\RepositoryTrait;

class EventRepository
{
    use RepositoryTrait;

    protected Event $model;

    public function __construct(Event $event)
    {
        $this->model = $event;
    }

    public function GetList(array $params)
    {
        return Event::query()
            ->when(!empty($params['name']), function ($query) use ($params) {
                $query->where('name', 'like', '%' . $params['name'] . '%');
            })
            ->when(isset($params['status']) && in_array($params['status'], [0, 1, '0', '1'], true), function ($query) use ($params) {
                $query->where('status', $params['status']);
            })
            ->get()
            ->toArray();
    }

    /**
     * 處理圖片上傳
     *
     * @param array $params 包含 'file' (UploadedFile) 與 'type'
     * @return array
     */
    public function uploadImage(array $params): array
    {
        try {
            $file = $params['file'];
            $type = $params['type'] ?? 'default';

            $dir  = ($type == 'ckeditor') ? 'edm/uat/ckeditor' : 'edm/uat';
            $path = $file->store($dir, 'sftp');

            return [
                'status' => true,
                'path'   => $path,
                'name'   => $path,
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 取得特定 Google 表單的審核名單
     *
     * 確認活動是否開啟審核機制（is_approve == 1），若否則提早回傳錯誤結構。
     * 成功時回傳所有填寫紀錄，包含完整的 status 狀態欄位供前端篩選顯示。
     *
     * @param int $googleFormId GoogleForm 資料表的主鍵
     * @return array{found: bool, is_approve: bool, data: mixed, message?: string}
     */
    public function getApproveList(int $googleFormId): array
    {
        $googleForm = GoogleForm::with('event')->find($googleFormId);

        if (!$googleForm) {
            return ['found' => false, 'message' => '找不到對應的 Google 表單紀錄'];
        }

        if (!$googleForm->event) {
            return ['found' => false, 'message' => '找不到對應的活動'];
        }

        if ($googleForm->event->is_approve != 1) {
            return [
                'found'      => true,
                'is_approve' => false,
                'message'    => '此活動未開啟審核機制',
                'data'       => [],
            ];
        }

        $responses = GoogleFormResponse::where('google_form_id', $googleFormId)->get();

        return [
            'found'      => true,
            'is_approve' => true,
            'data'       => $responses,
        ];
    }
}
