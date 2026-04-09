<?php

namespace App\Services;

use Google\Client;
use Google\Service\Forms;
use Google\Service\Forms\Form;
use Google\Service\Forms\Info;
use Google\Service\Forms\Item;
use Google\Service\Forms\Question;
use Google\Service\Forms\QuestionGroup;
use Google\Service\Forms\TextQuestion;
use Google\Service\Forms\Option;
use Google\Service\Forms\ChoiceQuestion;
use Google\Service\Forms\DateQuestion;
use Google\Service\Forms\TimeQuestion;
use Google\Service\Forms\QuestionItem;
use Google\Service\Forms\CreateItemRequest;
use Google\Service\Forms\Location;
use Google\Service\Forms\Request as GoogleRequest;
use Google\Service\Forms\BatchUpdateFormRequest;
use Illuminate\Support\Facades\Log;

/**
 * Google API 服務
 * 
 * 負責處理與 Google Cloud 相關的 API 請求，主要用於 Google Forms (問卷) 的
 * 建立、讀取、修改及回覆收集等功能。
 */
class GoogleApiService
{
    /**
     * @var Client Google API 客戶端實例
     */
    protected $client;

    /**
     * 建構子：初始化 Google API 客戶端並設定身份驗證
     * 讀取 .env 中的 GOOGLE_CLOUD_* 變數進行 Service Account 授權
     */
    public function __construct()
    {
        $this->client = new Client();
        $this->client->setApplicationName('EDM Google API');
        $this->client->setScopes([
            Forms::FORMS_RESPONSES_READONLY, 
            Forms::FORMS_BODY_READONLY,
            Forms::FORMS_BODY, // 建立與修改表單需要此權限
        ]);

        // 直接讀取 .env 中的 Google Cloud 設定檔
        $credentials = [
            'type'                        => env('GOOGLE_CLOUD_ACCOUNT_TYPE'),
            'project_id'                  => env('GOOGLE_CLOUD_PROJECT_ID'),
            'private_key_id'              => env('GOOGLE_CLOUD_PRIVATE_KEY_ID'),
            // 非常重要：將字串中的 \n 轉換為真實的換行符號，才能正確解析金鑰
            'private_key'                 => str_replace('\\n', "\n", env('GOOGLE_CLOUD_PRIVATE_KEY', '')),
            'client_email'                => env('GOOGLE_CLOUD_CLIENT_EMAIL'),
            'client_id'                   => env('GOOGLE_CLOUD_CLIENT_ID'),
            'auth_uri'                    => env('GOOGLE_CLOUD_AUTH_URI'),
            'token_uri'                   => env('GOOGLE_CLOUD_TOKEN_URI'),
            'auth_provider_x509_cert_url' => env('GOOGLE_CLOUD_AUTH_PROVIDER_CERT_URL'),
            'client_x509_cert_url'        => env('GOOGLE_CLOUD_CLIENT_CERT_URL'),
        ];

        if (!empty($credentials['client_email']) && !empty($credentials['private_key'])) {
            $this->client->setAuthConfig($credentials);
        }
    }

    /**
     * 解析網址並萃取出真實的 Google Form ID
     * 
     * @param string $url 傳入的 Google Forms 網址
     * @return string|null 成功則回傳 Form ID，萃取失敗或網址不合法則回傳 null
     */
    public function extractFormId($url)
    {
        // 匹配 https://docs.google.com/forms/d/{FORM_ID}/edit
        if (preg_match('/forms\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            // 如果擷取到的是 1FAIp... 代表這是填表網址，這無法用在 API
            if (str_starts_with($matches[1], '1FAIp') || str_starts_with($matches[1], 'e/1FAIp')) {
                return null;
            }
            return preg_replace('/^e\//', '', $matches[1]);
        }
        return null;
    }

    /**
     * 取得指定問卷的基本資訊與總填寫人數（包含完整回覆清單）
     * 
     * @param string $formId 表單的 Google ID
     * @return array 包含狀態、標題、回覆人數及回覆詳細內容的陣列
     */
    public function getFormSummary($formId)
    {
        try {
            $service = new Forms($this->client);
            
            // 取得表單的所有回覆名單
            $responses = $service->forms_responses->listFormsResponses($formId);
            $responsesList = $responses->getResponses() ?? [];

            // 取得表單標題
            $formBody = $service->forms->get($formId);

            return [
                'status' => true,
                'title' => $formBody->getInfo()->getTitle(),
                'response_count' => count($responsesList),
                'responses' => $responsesList
            ];

        } catch (\Exception $e) {
            Log::error('Google Forms API Failed: ' . $e->getMessage());
            return [
                'status' => false,
                'error' => "無法讀取表單，這通常是因為服務帳戶沒有權限，或 Form ID 錯誤。"
            ];
        }
    }

    /**
     * 透過 Google Forms API 建立新的空問卷
     * (注意：Google API 規定在 Create 階段僅能設定標題)
     * 
     * @param string $title 問卷標題
     * @return array 包含建立狀態與表單相關網址 (編輯用 edit_url, 填寫用 responder_uri)
     */
    public function createForm($title)
    {
        try {
            $service = new Forms($this->client);
            $formBody = new Form();
            $info = new Info();
            $info->setTitle($title);
            $formBody->setInfo($info);

            $createdForm = $service->forms->create($formBody);

            return [
                'status'        => true,
                'form_id'       => $createdForm->getFormId(),
                'responder_uri' => $createdForm->getResponderUri(),
                'edit_url'      => "https://docs.google.com/forms/d/{$createdForm->getFormId()}/edit",
                'title'         => $createdForm->getInfo()->getTitle(),
            ];
        } catch (\Exception $e) {
            Log::error('Google Forms Create Failed: ' . $e->getMessage());
            return [
                'status' => false,
                'error'  => '建立表單失敗：' . $e->getMessage()
            ];
        }
    }

    /**
     * 批次更新（建立）問卷之下的題目項目與問卷詳情
     *
     * @param string $formId 表單的 Google ID
     * @param array $questions 來自前端的問題陣列
     * @param string|null $description 問卷描述
     * @return bool
     */
    public function batchUpdateQuestions($formId, $questions, $description = null)
    {
        try {
            $service = new Forms($this->client);
            $requests = [];

            // 1. 如果有描述，加入更新問卷資訊的請求
            if ($description) {
                $updateInfo = new \Google\Service\Forms\UpdateFormInfoRequest();
                $info = new Info();
                $info->setDescription($description);
                $updateInfo->setInfo($info);
                $updateInfo->setUpdateMask('description');

                $req = new GoogleRequest();
                $req->setUpdateFormInfo($updateInfo);
                $requests[] = $req;
            }

            $index = 0;
            foreach ($questions as $q) {
                $questionData = new Question();
                $questionData->setRequired(isset($q['required']) ? (bool)$q['required'] : false);
                
                $type = $q['type'] ?? 'text';

                // 根據前端題型轉換成對應的 Google Type
                switch (strtolower($type)) {
                    case 'radio':
                    case 'checkbox':
                    case 'drop_down':
                    case 'dropdown':
                        $options = [];
                        foreach ($q['options'] ?? [] as $opt) {
                            $val = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                            
                            // 過濾掉 null 或空值，避免 Google API 報錯
                            if ($val === null || $val === '') {
                                continue;
                            }

                            $optionItem = new Option();
                            $optionItem->setValue((string)$val);
                            $options[] = $optionItem;
                        }
                        
                        $mappedType = 'RADIO';
                        if (strtolower($type) === 'checkbox') $mappedType = 'CHECKBOX';
                        if (strtolower($type) === 'dropdown' || strtolower($type) === 'drop_down') $mappedType = 'DROP_DOWN';
                        
                        $choiceQuestion = new ChoiceQuestion();
                        $choiceQuestion->setType($mappedType);
                        $choiceQuestion->setOptions($options);
                        
                        $questionData->setChoiceQuestion($choiceQuestion);
                        break;
                        
                    case 'date':
                        $dateQuestion = new DateQuestion();
                        $dateQuestion->setIncludeTime(false);
                        $questionData->setDateQuestion($dateQuestion);
                        break;
                        
                    case 'time':
                        $timeQuestion = new TimeQuestion();
                        $timeQuestion->setDuration(false);
                        $questionData->setTimeQuestion($timeQuestion);
                        break;
                        
                    case 'short text':
                    case 'long text':
                    case 'text':
                    default:
                        $textQuestion = new TextQuestion();
                        $textQuestion->setParagraph(strtolower($type) === 'long text');
                        $questionData->setTextQuestion($textQuestion);
                        break;
                }

                $qi = new QuestionItem();
                $qi->setQuestion($questionData);

                $item = new Item();
                $item->setTitle($q['label'] ?? '新問題');
                $item->setQuestionItem($qi);

                $createItem = new CreateItemRequest();
                $createItem->setItem($item);
                
                $location = new Location();
                $location->setIndex($index);
                $createItem->setLocation($location);

                $req = new GoogleRequest();
                $req->setCreateItem($createItem);

                $requests[] = $req;
                $index++;
            }

            if (!empty($requests)) {
                $batchRequest = new BatchUpdateFormRequest();
                $batchRequest->setRequests($requests);
                $service->forms->batchUpdate($formId, $batchRequest);
            }

            return true;

        } catch (\Exception $e) {
            $errorMsg = method_exists($e, 'getErrors') ? json_encode($e->getErrors(), JSON_UNESCAPED_UNICODE) : $e->getMessage();
            Log::error('Google Forms Batch Update Failed: ' . $errorMsg);
            throw $e;
        }
    }
}
