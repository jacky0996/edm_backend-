<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Libraries\EntrustLib;
use App\Libraries\DataLib;
use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Models\EDM\Group;
use App\Models\EDM\Image;
use App\Repositories\EDM\EventRelationRepository;
use App\Repositories\EDM\EventRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\UserService;
use App\Services\GoogleApiService;
use App\Models\Google\GoogleForm;
use App\Models\Google\GoogleFormResponse;
use App\Models\Google\GoogleFormStat;
/**
 * 活動管理控制器 (Event Management Controller)
 *
 * 負責處理 EDM 系統中所有的活動流程，包括活動的增刪改查 (CRUD)、
 * 圖片上傳管理、邀請名單導入，以及與 Google Forms API 的深度整合（建立、更新、同步問卷）。
 */
class EventController extends Controller
{
    /**
     * 建構子：注入相依服務與儲存庫
     *
     * @param EventRepository $eventRepository 活動資料儲存庫
     * @param EventRelationRepository $eventRelationRepository 活動關聯(群組)儲存庫
     * @param UserService $userService 使用者權限與資訊相關服務
     * @param GoogleApiService $googleApi Google Forms API 串接服務
     */
    public function __construct(
        protected EventRepository $eventRepository,
        protected EventRelationRepository $eventRelationRepository,
        protected UserService $userService,
        protected GoogleApiService $googleApi,
    ) {
    }

    /**
     * 取得活動列表 (分頁)
     *
     * 根據篩選條件取得所有活動，並回傳指定分頁的資料。
     *
     * @param Request $request 包含 page (頁碼), pageSize (每頁筆數) 與各式過濾參數
     * @return \Illuminate\Http\JsonResponse 包含分頁後的活動項目及總筆數
     */
    public function list(Request $request)
    {
        $page      = (int) $request->input('page', 1);
        $pageSize  = (int) $request->input('pageSize', 20);
        $data      = $this->eventRepository->GetList($request->all());
        $offset    = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => [
                'items' => array_values($pagedData),
                'total' => count($data),
            ],
        ]);
    }

    /**
     * 取得特定活動的詳細資訊
     *
     * @param Request $request 包含 id (活動 ID)
     * @return \Illuminate\Http\JsonResponse 成功時回傳紀錄物件
     */
    public function view(Request $request)
    {
        $event = Event::find($request->input('id'));

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $event,
        ]);
    }

    /**
     * 建立新活動
     *
     * 處理基本活動資訊儲存，並根據報名設定決定活動的顯示與審核旗標。
     *
     * @param Request $request 包含 title, summary, start_time, end_time, is_registration 等
     * @return \Illuminate\Http\JsonResponse 建立成功的活動物件，若驗證失敗則回傳 422
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|max:255',
            'summary'    => 'nullable|string',
            'content'    => 'nullable|string',
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after_or_equal:start_time',
            'landmark'   => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:255',
            'img_url'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '欄位驗證失敗',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->input('is_registration') == 1) {
            $is_display = 1;
            $is_approve = ($request->input('is_approval') == 1) ? 1 : 1; 
        } else {
            $is_approve = 0;
            $is_display = 0;
        }

        try {
            $user = $this->userService->getUserFromHeader($request);
            $event               = new Event();
            $event->event_number = EntrustLib::DocumentNumber('event');
            $event->title        = $request->input('title');
            $event->summary      = $request->input('summary');
            $event->content      = $request->input('content');
            $event->start_time   = $request->input('start_time');
            $event->end_time     = $request->input('end_time');
            $event->landmark     = $request->input('landmark');
            $event->address      = $request->input('address');
            $event->img_url      = $request->input('img_url');
            $event->type         = $request->input('type');
            $event->status       = 0;
            $event->is_approve   = $is_approve;
            $event->is_display   = $is_display;
            $event->is_qrcode    = 0;
            $event->creator_enumber = $user['enumber'];
            $event->save();

            return response()->json([
                'code'   => 0,
                'status' => true,
                'data'   => $event,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '建立活動失敗',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新現有活動資訊
     *
     * 支援審核旗標變更時的資料連動。若切換為不需要審核，系統會自動核准所有待審紀錄。
     *
     * @param Request $request 包含 id (活動 ID) 及欲修改的內容
     * @return \Illuminate\Http\JsonResponse 修改後的活動物件
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|max:255',
            'summary'    => 'nullable|string',
            'content'    => 'nullable|string',
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after_or_equal:start_time',
            'landmark'   => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:255',
            'img_url'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '欄位驗證失敗',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->input('is_registration') == 1) {
            $is_display = 1;
            $is_approve = ($request->input('is_approve') == 1) ? 1 : 0;
        } else {
            $is_approve = 0;
            $is_display = 0;
        }

        $event             = Event::find($request->input('id'));
        
        // 檢查審核設定是否從「需審核」變更為「不需審核」
        $shouldAutoApproveExisting = ($event->is_approve == 1 && $is_approve == 0);

        $event->title        = $request->input('title');
        $event->summary      = $request->input('summary');
        $event->content      = $request->input('content');
        $event->start_time   = $request->input('start_time');
        $event->end_time     = $request->input('end_time');
        $event->landmark     = $request->input('landmark');
        $event->address      = $request->input('address');
        $event->img_url      = $request->input('img_url');
        $event->type         = $request->input('type');
        $event->status       = 0;
        $event->is_approve   = $is_approve;
        $event->is_display   = $is_display;
        $event->is_qrcode    = 0;
        $event->save();

        // 處理由「需審核」切換為「不需審核」的連動：將所有待審中的紀錄設為通過
        if ($shouldAutoApproveExisting) {
            GoogleFormResponse::where('event_id', $event->id)
                ->where('status', 0)
                ->update(['status' => 1]);
        }

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $event,
        ]);
    }

    /**
     * 上傳活動相關圖片
     *
     * 使用 SFTP 儲存實體檔案，並在資料庫紀錄檔名資訊。
     *
     * @param Request $request 包含檔案物件 (file)
     * @return \Illuminate\Http\JsonResponse 包含上傳後的路徑與名稱
     */
    public function imageUpload(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['code' => 1, 'status' => false, 'message' => 'No file uploaded'], 400);
        }

        $result = $this->eventRepository->uploadImage($request->all());
        if ($result && $result['status']) {
            Image::create([
                'name' => $result['name'],
            ]);
        }

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $result,
        ]);
    }

    /**
     * 取得系統中所有上傳圖片的存取網址
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse 圖片物件清單
     */
    public function getImage(Request $request)
    {
        $images = Image::all();

        $data = $images->map(function ($image) {
            $image->url = DataLib::CheckFilePath($image);
            return $image;
        });

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $data,
        ]);
    }

    /**
     * 取得特定活動的獲邀名單
     *
     * @param Request $request 包含 event_id, page, pageSize 等
     * @return \Illuminate\Http\JsonResponse 回傳群組選項與已邀請的會員清單
     */
    public function getInviteList(Request $request)
    {
        $group     = Group::where('status', 1)->get()->toArray();
        $page      = (int) $request->input('page', 1);
        $pageSize  = (int) $request->input('pageSize', 20);
        $data      = $this->eventRelationRepository->inviteMembersList($request->all());
        $offset    = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => [
                'group'  => $group,
                'member' => array_values($pagedData),
                'total'  => count($data),
            ],
        ]);
    }

    /**
     * 批次匯入群組成員至活動邀請名單
     *
     * @param Request $request 包含 event_id (活動 ID) 與 group_id (群組 ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function importGroup(Request $request)
    {
        $group = Group::with('members.mobiles', 'members.emails', 'members.organizations')->where('id', $request->input('group_id'))->first();

        if (!$group) {
            return response()->json(['code' => 1, 'status' => false]);
        }

        $members = $group->members;
        foreach ($members as $value) {
            $mobileId = $value->mobiles->first() ? $value->mobiles->first()->id : null;
            $emailId  = $value->emails->first() ? $value->emails->first()->id : null;
            $orgId    = $value->organizations->first() ? $value->organizations->first()->id : null;

            EventRelation::firstOrCreate(
                [
                    'event_id'  => $request->input('event_id'),
                    'member_id' => $value->id,
                    'group_id'  => $request->input('group_id'),
                ],
                [
                    'mobile_id'       => $mobileId,
                    'email_id'        => $emailId,
                    'organization_id' => $orgId,
                ]
            );
        }

        return response()->json(['code' => 0, 'status' => true]);
    }

    /**
     * 取得該活動的表單顯示狀態與所有相關統計/回覆內容
     *
     * 為後台問卷管理的主入口，自動預載活動、表單設定、統計數據及所有填寫紀錄。
     *
     * @param Request $request 包含 event_id (活動 ID)
     * @return \Illuminate\Http\JsonResponse 包含三階段判定 (是否需表單、是否綁定、完整內容) 的結果
     */
    public function getDisplayList(Request $request)
    {
        $eventId = $request->input('event_id');
        if (!$eventId) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '缺少必要參數 event_id']);
        }

        $event = $this->eventRelationRepository->getFormDisplay($eventId);
        
        if(!$event) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到對應的活動']);
        }

        if($event->is_display == 0){
            return response()->json([
                'code'   => 0, 
                'status' => true,
                'message' => '活動不需填寫報名表',
                'data'   => [
                    'event' => $event,
                    'requires_registration' => false
                ]
            ]);
        }

        if (!$event->googleForm) {
            return response()->json([
                'code'   => 0,
                'status' => true,
                'message' => '活動需要報名表，但尚未綁定 Google 表單',
                'data'   => [
                    'event' => $event,
                    'requires_registration' => true,
                    'google_form_bound' => false
                ]
            ]);
        }

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => [
                'event' => $event,
                'requires_registration' => true,
                'google_form_bound' => true,
                'form_details' => $event->googleForm
            ]
        ]);
    }

    /**
     * 更新活動顯示狀態
     *
     * @param Request $request 包含 event_id 與 is_display (0 或 1)
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDisplay(Request $request)
    {
        $event = Event::find($request->input('event_id'));
        if (!$event) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到活動']);
        }

        $event->is_display = $request->input('is_display', 0);
        $event->save();

        return response()->json(['code' => 0, 'status' => true, 'message' => '顯示狀態更新成功']);
    }

    /**
     * 向 Google API 請求建立全新的表單並綁定至活動
     *
     * 包含兩個階段：1. 建立空表單取得 ID。 2. 批次更新寫入固定欄位與自定義題目。
     *
     * @param Request $request 包含 event_id, title, description, standardFields, customQuestions
     * @return \Illuminate\Http\JsonResponse 建立成功回傳綁定的 GoogleForm 資料庫紀錄
     */
    public function createGoogleForm(Request $request)
    {
        $eventId = $request->input('event_id');
        
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到對應的活動']);
        }

        $config     = $request->input('config', []);
        $title      = $request->input('title') ?? ($config['title'] ?? ($event->title ?: '新活動問卷'));
        $description = $request->input('description') ?? ($config['description'] ?? null);
        $standardFields = $request->input('standardFields') ?? ($config['standardFields'] ?? []);
        $customQuestions = $request->input('customQuestions') ?? ($config['customQuestions'] ?? []);

        $existingForm = GoogleForm::where('event_id', $eventId)->first();
        if ($existingForm) {
            return response()->json([
                'code'    => 1, 
                'status'  => false, 
                'message' => '此活動已經綁定過 Google 表單，請使用更新功能',
                'data'    => $existingForm
            ]);
        }

        try {
            $result = $this->googleApi->createForm($title);

            if ($result['status'] === true) {
                $questions = [];
                $standardMapping = [
                    'name'       => ['label' => '姓名', 'type' => 'text', 'required' => true],
                    'mobile'     => ['label' => '手機', 'type' => 'text', 'required' => true],
                    'email'      => ['label' => '電子郵件', 'type' => 'text', 'required' => true],
                    'company'    => ['label' => '公司名稱', 'type' => 'text', 'required' => false],
                    'job_title'  => ['label' => '職稱', 'type' => 'text', 'required' => false],
                ];

                foreach ($standardFields as $field) {
                    if (isset($standardMapping[$field])) {
                        $questions[] = $standardMapping[$field];
                    }
                }

                if (!empty($customQuestions)) {
                    $questions = array_merge($questions, $customQuestions);
                }

                $this->googleApi->batchUpdateQuestions($result['form_id'], $questions, $description);

                $googleForm = GoogleForm::create([
                    'event_id'  => $eventId,
                    'form_id'   => $result['form_id'],
                    'form_url'  => $result['responder_uri'],
                    'type'      => $request->input('type', 'google_form'),
                ]);

                return response()->json([
                    'code'    => 0,
                    'status'  => true,
                    'message' => 'Google 問卷建立成功',
                    'data'    => $googleForm
                ]);
            } else {
                return response()->json([
                    'code'    => 1,
                    'status'  => false,
                    'message' => 'Google API 服務異常',
                    'error'   => $result['error']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '表單建立流程發生錯誤',
                'error'   => $e->getMessage()
            ]);
        }
    }

    /**
     * 取得已綁定的 Google 表單當前結構資訊 (與 Google API 即時同步)
     *
     * @param Request $request 包含 id (GoogleForm 紀錄主鍵)
     * @return \Illuminate\Http\JsonResponse 包含 DB 紀錄與 Google 段的原始 JSON
     */
    public function getGoogleForm(Request $request)
    {
        $id = $request->input('id');
        
        if (!$id) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '缺少必要參數 id']);
        }

        $googleForm = GoogleForm::find($id);
        if (!$googleForm) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到該筆 Google 表單紀錄']);
        }

        try {
            $googleResult = $this->googleApi->getFormDetails($googleForm->form_id);
            if ($googleResult['status'] === true) {
                return response()->json([
                    'code'   => 0,
                    'status' => true,
                    'data'   => [
                        'record'      => $googleForm,
                        'google_info' => $googleResult['data']
                    ]
                ]);
            } else {
                return response()->json([
                    'code'    => 1,
                    'status'  => false,
                    'message' => '無法從 Google API 取得詳情',
                    'error'   => $googleResult['error']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '取得表單詳情失敗：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 更新已綁定的 Google 表單架構
     *
     * 此操作會採用全量覆蓋 (Sync) 策略：先刪除 Google 問卷中的所有舊題目，再根據新的 config 重新寫入。
     *
     * @param Request $request 包含 id (或 event_id), config, customQuestions 等
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateGoogleForm(Request $request)
    {
        $id = $request->input('id');
        $eventId = $request->input('event_id');
        
        $query = GoogleForm::query();
        if ($id) {
            $query->where('id', $id);
        } elseif ($eventId) {
            $query->where('event_id', $eventId);
        } else {
            return response()->json(['code' => 1, 'status' => false, 'message' => '缺少必要參數']);
        }

        $googleForm = $query->first();
        if (!$googleForm) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到對應的表單紀錄']);
        }

        $config          = $request->input('config', []);
        $title           = $request->input('title') ?? ($config['title'] ?? null);
        $description     = $request->input('description') ?? ($config['description'] ?? null);
        $standardFields  = $request->input('standardFields') ?? ($config['standardFields'] ?? []);
        $customQuestions = $request->input('customQuestions') ?? ($config['customQuestions'] ?? []);

        try {
            $questions = [];
            $standardMapping = [
                'name'       => ['label' => '姓名', 'type' => 'text', 'required' => true],
                'mobile'     => ['label' => '手機', 'type' => 'text', 'required' => true],
                'email'      => ['label' => '電子郵件', 'type' => 'text', 'required' => true],
                'company'    => ['label' => '公司名稱', 'type' => 'text', 'required' => false],
                'department' => ['label' => '部門', 'type' => 'text', 'required' => false],
                'job_title'  => ['label' => '職稱', 'type' => 'text', 'required' => false],
            ];

            foreach ($standardFields as $field) {
                if (isset($standardMapping[$field])) {
                    $questions[] = $standardMapping[$field];
                }
            }
            if (!empty($customQuestions)) {
                $questions = array_merge($questions, $customQuestions);
            }

            $this->googleApi->syncFormItems($googleForm->form_id, $questions, $title, $description);

            if ($request->has('type')) {
                $googleForm->type = $request->input('type');
                $googleForm->save();
            }

            return response()->json([
                'code'   => 0,
                'status' => true,
                'message' => 'Google 表單內容已同步更新',
                'data'   => $googleForm
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '更新流程失敗：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 刪除特定 Google 表單的綁定紀錄 (解除綁定)
     *
     * @param Request $request 包含 id (GoogleForm 主鍵)
     * @return \Illuminate\Http\JsonResponse
     */
    public function delGoogleForm(Request $request)
    {
        $id = $request->input('id');
        
        if (!$id) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '缺少必要參數 id']);
        }

        $googleForm = GoogleForm::find($id);
        
        if (!$googleForm) {
            return response()->json([
                'code'   => 1,
                'status' => false,
                'message' => '找不到該筆 Google 表單紀錄'
            ]);
        }

        $googleForm->delete();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'message' => 'Google 表單已成功解除綁定'
        ]);
    }

    /**
     * 更新報名紀錄的審核狀態
     *
     * @param Request $request 包含 response_id (Google 端的 Response ID) 與 status (0:待審, 1:通過, 2:退件)
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateResponseStatus(Request $request)
    {
        $responseId = $request->input('response_id'); 
        $status = $request->input('status');
        
        if (!isset($responseId) || !isset($status)) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '缺少必要參數']);
        }

        $response = GoogleFormResponse::where('google_response_id', $responseId)->first();
        
        if (!$response) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到該筆報名紀錄']);
        }

        $response->status = $status;
        $response->save();

        return response()->json([
            'code' => 0,
            'status' => true,
            'message' => '狀態更新成功',
            'data' => $response
        ]);
    }
}

