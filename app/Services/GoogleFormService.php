<?php

namespace App\Services;

use App\Models\EDM\Event;
use App\Repositories\Google\GoogleFormRepository;

/**
 * Google 問卷業務服務
 *
 * 將 EventController 內與 Google 問卷相關的流程抽離，集中處理：
 *  - 題目組裝 (standardFields + customQuestions)
 *  - 與 GoogleApiService 的溝通 (建立 / 同步 / 取得詳情)
 *  - 透過 GoogleFormRepository 做資料庫綁定 / 解除綁定
 */
class GoogleFormService
{
    /**
     * 標準欄位對應表 (建立表單時)
     */
    private const STANDARD_FIELDS_CREATE = [
        'name' => ['label' => '姓名', 'type' => 'text', 'required' => true],
        'mobile' => ['label' => '手機', 'type' => 'text', 'required' => true],
        'email' => ['label' => '電子郵件', 'type' => 'text', 'required' => true],
        'company' => ['label' => '公司名稱', 'type' => 'text', 'required' => false],
        'job_title' => ['label' => '職稱', 'type' => 'text', 'required' => false],
    ];

    /**
     * 標準欄位對應表 (更新表單時，多一個 department 欄位)
     */
    private const STANDARD_FIELDS_UPDATE = [
        'name' => ['label' => '姓名', 'type' => 'text', 'required' => true],
        'mobile' => ['label' => '手機', 'type' => 'text', 'required' => true],
        'email' => ['label' => '電子郵件', 'type' => 'text', 'required' => true],
        'company' => ['label' => '公司名稱', 'type' => 'text', 'required' => false],
        'department' => ['label' => '部門', 'type' => 'text', 'required' => false],
        'job_title' => ['label' => '職稱', 'type' => 'text', 'required' => false],
    ];

    public function __construct(
        protected GoogleApiService $googleApi,
        protected GoogleFormRepository $googleFormRepository,
    ) {}

    /**
     * 從 request 參數中解析出題目設定 (title / description / standardFields / customQuestions)
     *
     * 優先順序：最外層參數 > config 內的參數 > 預設
     */
    public function resolveFormConfig(array $input, ?string $defaultTitle = null): array
    {
        $config = $input['config'] ?? [];

        return [
            'title' => $input['title'] ?? ($config['title'] ?? $defaultTitle),
            'description' => $input['description'] ?? ($config['description'] ?? null),
            'standardFields' => $input['standardFields'] ?? ($config['standardFields'] ?? []),
            'customQuestions' => $input['customQuestions'] ?? ($config['customQuestions'] ?? []),
        ];
    }

    /**
     * 依據 standardFields + customQuestions 組出送給 Google API 的題目陣列
     *
     * @param  array  $standardFields  e.g. ['name', 'mobile']
     * @param  array  $customQuestions  自定義題目
     * @param  array  $standardMapping  對應表 (建立 / 更新用不同)
     */
    public function buildQuestions(array $standardFields, array $customQuestions, array $standardMapping): array
    {
        $questions = [];

        foreach ($standardFields as $field) {
            if (isset($standardMapping[$field])) {
                $questions[] = $standardMapping[$field];
            }
        }

        if (! empty($customQuestions)) {
            $questions = array_merge($questions, $customQuestions);
        }

        return $questions;
    }

    /**
     * 為活動建立 Google 表單並綁定
     *
     * 流程：
     *  1. 檢查是否已綁定，已綁定直接回傳錯誤訊息
     *  2. 呼叫 Google API 建立空表單
     *  3. 組題目後批次寫入 Google
     *  4. 將綁定結果寫入 DB
     */
    public function createFormForEvent(Event $event, array $input): array
    {
        $existingForm = $this->googleFormRepository->findByEventId($event->id);
        if ($existingForm) {
            return [
                'status' => false,
                'message' => '此活動已經綁定過 Google 表單，請使用更新功能',
                'data' => $existingForm,
            ];
        }

        $defaultTitle = $event->title ?: '新活動問卷';
        $config = $this->resolveFormConfig($input, $defaultTitle);

        $result = $this->googleApi->createForm($config['title']);
        if ($result['status'] !== true) {
            return [
                'status' => false,
                'message' => 'Google API 服務異常',
                'error' => $result['error'] ?? null,
            ];
        }

        $questions = $this->buildQuestions(
            $config['standardFields'],
            $config['customQuestions'],
            self::STANDARD_FIELDS_CREATE
        );

        $this->googleApi->batchUpdateQuestions($result['form_id'], $questions, $config['description']);

        $googleForm = $this->googleFormRepository->createForm([
            'event_id' => $event->id,
            'form_id' => $result['form_id'],
            'form_url' => $result['responder_uri'],
            'type' => $input['type'] ?? 'google_form',
        ]);

        return [
            'status' => true,
            'message' => 'Google 問卷建立成功',
            'data' => $googleForm,
        ];
    }

    /**
     * 取得 DB 綁定紀錄 + 呼叫 Google API 同步取得目前表單結構
     */
    public function getFormWithGoogleDetails($id): array
    {
        $googleForm = $this->googleFormRepository->findById($id);
        if (! $googleForm) {
            return ['status' => false, 'message' => '找不到該筆 Google 表單紀錄'];
        }

        $googleResult = $this->googleApi->getFormDetails($googleForm->form_id);
        if ($googleResult['status'] !== true) {
            return [
                'status' => false,
                'message' => '無法從 Google API 取得詳情',
                'error' => $googleResult['error'] ?? null,
            ];
        }

        return [
            'status' => true,
            'data' => [
                'record' => $googleForm,
                'google_info' => $googleResult['data'],
            ],
        ];
    }

    /**
     * 同步更新已綁定的 Google 表單結構 (全量覆蓋)
     *
     * 支援以 id 或 event_id 定位綁定紀錄。
     */
    public function syncForm($id, $eventId, array $input): array
    {
        if (! $id && ! $eventId) {
            return ['status' => false, 'message' => '缺少必要參數'];
        }

        $googleForm = $this->googleFormRepository->findByIdOrEventId($id, $eventId);
        if (! $googleForm) {
            return ['status' => false, 'message' => '找不到對應的表單紀錄'];
        }

        $config = $this->resolveFormConfig($input);
        $questions = $this->buildQuestions(
            $config['standardFields'],
            $config['customQuestions'],
            self::STANDARD_FIELDS_UPDATE
        );

        $this->googleApi->syncFormItems(
            $googleForm->form_id,
            $questions,
            $config['title'],
            $config['description']
        );

        if (array_key_exists('type', $input)) {
            $googleForm->type = $input['type'];
            $googleForm->save();
        }

        return [
            'status' => true,
            'message' => 'Google 表單內容已同步更新',
            'data' => $googleForm,
        ];
    }

    /**
     * 解除 Google 表單綁定
     */
    public function deleteForm($id): array
    {
        if (! $id) {
            return ['status' => false, 'message' => '缺少必要參數 id'];
        }

        $googleForm = $this->googleFormRepository->findById($id);
        if (! $googleForm) {
            return ['status' => false, 'message' => '找不到該筆 Google 表單紀錄'];
        }

        $this->googleFormRepository->deleteForm($googleForm);

        return ['status' => true, 'message' => 'Google 表單已成功解除綁定'];
    }

    /**
     * 更新報名回覆的審核狀態
     */
    public function updateResponseStatus($googleResponseId, $status): array
    {
        if (! isset($googleResponseId) || ! isset($status)) {
            return ['status' => false, 'message' => '缺少必要參數'];
        }

        $response = $this->googleFormRepository->findResponseByGoogleId($googleResponseId);
        if (! $response) {
            return ['status' => false, 'message' => '找不到該筆報名紀錄'];
        }

        $this->googleFormRepository->updateResponseStatus($response, $status);

        return [
            'status' => true,
            'message' => '狀態更新成功',
            'data' => $response,
        ];
    }

    /**
     * 當活動由「需審核」切換為「不需審核」時，批次通過尚待審核的回覆
     */
    public function approvePendingByEventId($eventId): int
    {
        return $this->googleFormRepository->approvePendingByEventId($eventId);
    }
}
