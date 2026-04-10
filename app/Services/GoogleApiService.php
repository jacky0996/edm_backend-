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
use Google\Service\Forms\DeleteItemRequest;
use Google\Service\Forms\UpdateFormInfoRequest;
use Google\Service\Forms\Location;
use Google\Service\Forms\Request as GoogleRequest;
use Google\Service\Forms\BatchUpdateFormRequest;
use Illuminate\Support\Facades\Log;

/**
 * Google API 服務
 */
class GoogleApiService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setApplicationName('EDM Google API');
        $this->client->setScopes([
            Forms::FORMS_RESPONSES_READONLY, 
            Forms::FORMS_BODY_READONLY,
            Forms::FORMS_BODY,
        ]);

        $credentials = [
            'type'                        => env('GOOGLE_CLOUD_ACCOUNT_TYPE'),
            'project_id'                  => env('GOOGLE_CLOUD_PROJECT_ID'),
            'private_key_id'              => env('GOOGLE_CLOUD_PRIVATE_KEY_ID'),
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

    public function extractFormId($url)
    {
        if (preg_match('/forms\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            if (str_starts_with($matches[1], '1FAIp') || str_starts_with($matches[1], 'e/1FAIp')) {
                return null;
            }
            return preg_replace('/^e\//', '', $matches[1]);
        }
        return null;
    }

    public function getFormDetails($formId)
    {
        try {
            $service = new Forms($this->client);
            $form = $service->forms->get($formId);
            return [
                'status' => true,
                'data'   => $form
            ];
        } catch (\Exception $e) {
            Log::error('Google Forms Get Details Failed: ' . $e->getMessage());
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }

    public function getFormSummary($formId)
    {
        try {
            $service = new Forms($this->client);
            $responses = $service->forms_responses->listFormsResponses($formId);
            $responsesList = $responses->getResponses() ?? [];
            $formBody = $service->forms->get($formId);

            return [
                'status' => true,
                'title' => $formBody->getInfo()->getTitle(),
                'response_count' => count($responsesList),
                'responses' => $responsesList
            ];
        } catch (\Exception $e) {
            Log::error('Google Forms API Failed: ' . $e->getMessage());
            return ['status' => false, 'error' => "無法讀取表單，這通常是因為服務帳戶沒有權限。"];
        }
    }

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
            return ['status' => false, 'error' => '建立表單失敗：' . $e->getMessage()];
        }
    }

    public function syncFormItems($formId, $questions, $title = null, $description = null)
    {
        try {
            $service = new Forms($this->client);
            $form = $service->forms->get($formId);
            $currentItems = $form->getItems() ?? [];
            $requests = [];

            foreach ($currentItems as $item) {
                $deleteRequest = new GoogleRequest();
                $deleteItem = new DeleteItemRequest();
                $location = new Location();
                $location->setIndex(0);
                $deleteItem->setLocation($location);
                $deleteRequest->setDeleteItem($deleteItem);
                $requests[] = $deleteRequest;
            }

            if ($title || $description) {
                $updateInfo = new UpdateFormInfoRequest();
                $info = new Info();
                if ($title) $info->setTitle($title);
                if ($description) $info->setDescription($description);
                $updateInfo->setInfo($info);
                $mask = [];
                if ($title) $mask[] = 'title';
                if ($description) $mask[] = 'description';
                $updateInfo->setUpdateMask(implode(',', $mask));
                $req = new GoogleRequest();
                $req->setUpdateFormInfo($updateInfo);
                $requests[] = $req;
            }

            $index = 0;
            foreach ($questions as $q) {
                $requests[] = $this->buildCreateItemRequest($q, $index);
                $index++;
            }

            if (!empty($requests)) {
                $batchRequest = new BatchUpdateFormRequest();
                $batchRequest->setRequests($requests);
                $service->forms->batchUpdate($formId, $batchRequest);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Google Forms Sync Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function buildCreateItemRequest($q, $index)
    {
        $questionData = new Question();
        $questionData->setRequired(isset($q['required']) ? (bool)$q['required'] : false);
        $type = $q['type'] ?? 'text';

        switch (strtolower($type)) {
            case 'radio':
            case 'checkbox':
            case 'drop_down':
            case 'dropdown':
                $options = [];
                foreach ($q['options'] ?? [] as $opt) {
                    $val = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                    if ($val === null || $val === '') continue;
                    $optionItem = new Option();
                    $optionItem->setValue((string)$val);
                    $options[] = $optionItem;
                }
                $mappedType = strtoupper($type === 'dropdown' || $type === 'drop_down' ? 'DROP_DOWN' : $type);
                $choiceQuestion = new ChoiceQuestion();
                $choiceQuestion->setType($mappedType);
                $choiceQuestion->setOptions($options);
                $questionData->setChoiceQuestion($choiceQuestion);
                break;
            case 'date':
                $questionData->setDateQuestion(new DateQuestion());
                break;
            case 'time':
                $questionData->setTimeQuestion(new TimeQuestion());
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
        return $req;
    }

    public function batchUpdateQuestions($formId, $questions, $description = null)
    {
        try {
            $service = new Forms($this->client);
            $requests = [];
            if ($description) {
                $updateInfo = new UpdateFormInfoRequest();
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
                $requests[] = $this->buildCreateItemRequest($q, $index);
                $index++;
            }
            if (!empty($requests)) {
                $batchRequest = new BatchUpdateFormRequest();
                $batchRequest->setRequests($requests);
                $service->forms->batchUpdate($formId, $batchRequest);
            }
            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
