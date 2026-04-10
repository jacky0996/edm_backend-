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
/**
 * 活動管理控制器 (Event Management Controller)
 * 負責處理 EDM 系統中所有的活動流程，包括 CRUD、圖片上傳及 Google 問卷整合。
 */
class EventController extends Controller{
    /**
     * 建構子：注入相依服務與儲存庫
     * 
     * @param EventRepository $eventRepository 活動資料儲存庫
     * @param EventRelationRepository $eventRelationRepository 活動關聯(群組)儲存庫
     * @param UserService $userService 使用者相關服務
     */
    public function __construct(
        protected EventRepository $eventRepository,
        protected EventRelationRepository $eventRelationRepository,
        protected UserService $userService,
        protected GoogleApiService $googleApi,
    ) {
        $this->data = $eventRelationRepository;
    }

    /**
     * 取得活動列表 (分頁)
     * 
     * @param Request $request 包含 page (頁碼), pageSize (每頁筆數) 與過濾條件
     * @return \Illuminate\Http\JsonResponse 包含分頁後的活動資料與總數
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
     * 檢視特定活動詳細資訊
     * 
     * @param Request $request 包含 id (活動 ID)
     * @return \Illuminate\Http\JsonResponse 回傳活動資料
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
     * @param Request $request 包含標題、摘要、時間、地點等資訊
     * @return \Illuminate\Http\JsonResponse
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
            if ($request->input('is_approval') == 1) {
                $is_approve = 1;
            } else {
                $is_approve = 1;
            }
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
     * 更新活動資訊
     * 
     * @param Request $request 包含活動的修改資料及活動 ID
     * @return \Illuminate\Http\JsonResponse
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
            if ($request->input('is_approval') == 1) {
                $is_approve = 1;
            } else {
                $is_approve = 0;
            }
        } else {
            $is_approve = 0;
            $is_display = 0;
        }

        $event             = Event::find($request->input('id'));
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

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $event,
        ]);
    }

    /**
     * 圖片上傳介面
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function imageUpload(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['code' => 1, 'status' => false, 'message' => 'No file uploaded'], 400);
        }

        $result = $this->eventRepository->uploadImage($request->all());
        if ($result) {
            $image = Image::create([
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
     * 取得上傳的圖片清單與存取網址
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImage(Request $request)
    {
        $images = Image::all();

        $data = $images->map(function ($image) {
            // 由於 Image 模型已新增 getPathAttribute()，此處可正常運作
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
     * 取得活動邀請名單
     * 
     * @param Request $request 包含分頁資訊及活動過濾條件
     * @return \Illuminate\Http\JsonResponse 回傳群組資料與獲邀會員列表
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
     * 匯入活動關聯群組 (邀請群組至活動)
     * 
     * @param Request $request 包含 event_id (活動 ID) 及 group_id (群組 ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function importGroup(Request $request)
    {
        // 預載入 members 及其手機與 Email 關聯
        $group = Group::with('members.mobiles', 'members.emails', 'members.organizations')->where('id', $request->input('group_id'))->first();

        if (!$group) {
            return response()->json([
                'code'   => 1,
                'status' => false,
            ]);
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

        return response()->json([
            'code'   => 0,
            'status' => true,
        ]);
    }
    /**
     * 取得該活動的顯示狀態與 Google 表單綁定資訊
     * 
     * @param Request $request 包含 event_id
     * @return \Illuminate\Http\JsonResponse 綁定資料或未綁定的相關訊息
     */
    public function getDisplayList(Request $request)
    {
        $event = Event::find($request->input('event_id'));
        
        if(!$event) {
            return response()->json([
                'code'   => 1,
                'status' => false,
                'message' => '找不到對應的活動',
            ]);
        }

        if($event->is_display == 0){
            return response()->json([
                'code'   => 1,
                'status' => false,
                'message' => '活動不需填寫報名表',
            ]);
        }else{
            $googleForm = GoogleForm::where('event_id', $request->input('event_id'))->first();
            return response()->json([
                'code'   => 0,
                'status' => true,
                'data'   => $googleForm,
            ]);
        }
    }

    /**
     * 更新活動顯示狀態
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
    public function createGoogleForm(Request $request)
    {
        $eventId = $request->input('event_id');
        
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到對應的活動']);
        }

        // 參數解析：優先從 Root 抓取，若無則從 config 抓取
        $config     = $request->input('config', []);
        $title      = $request->input('title') ?? ($config['title'] ?? ($event->title ?: '新活動問卷'));
        $description = $request->input('description') ?? ($config['description'] ?? null);
        
        // 取得固定欄位清單 (從 Root 或 config)
        $standardFields = $request->input('standardFields') ?? ($config['standardFields'] ?? []);
        
        // 取得自定義題目 (通常在 Root)
        $customQuestions = $request->input('customQuestions') ?? ($config['customQuestions'] ?? []);

        // 檢查是否已經綁定過 Google 表單
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
            // 階段一：建立表單 (Google 限制此時僅能設定標題)
            $result = $this->googleApi->createForm($title);

            if ($result['status'] === true) {
                // 階段二：整理題目並寫入
                $questions = [];
                
                // 1. 映射固定欄位 (Standard Fields)
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

                // 2. 合併自定義題目 (這會讓 questions 陣列繼續增長)
                if (!empty($customQuestions)) {
                    $questions = array_merge($questions, $customQuestions);
                }

                // 3. 執行 Batch Update (包含寫入所有累積的題目與更新問卷描述)
                $this->googleApi->batchUpdateQuestions($result['form_id'], $questions, $description);

                // 更新資料庫回報 (對齊新欄位：form_id, form_url, type)
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
     * 取得已綁定的 Google 表單資訊 (包含 Google API 原始結構)
     * 
     * @param Request $request 僅包含 id (GoogleForm 主鍵)
     */
    public function getGoogleForm(Request $request)
    {
        $id = $request->input('id');
        
        if (!$id) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '缺少必要參數 id']);
        }

        // 1. 從資料庫查找紀錄
        $googleForm = GoogleForm::find($id);
        if (!$googleForm) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到該筆 Google 表單紀錄']);
        }

        try {
            // 2. 透過 Service 向 Google API 請求最新的表單細節
            $googleResult = $this->googleApi->getFormDetails($googleForm->form_id);
            if ($googleResult['status'] === true) {
                // 將資料庫紀錄與 Google API 原始資料合併回傳
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
     * 更新已綁定的 Google 表單內容（同步題目與資訊）
     * 
     * @param Request $request 包含 id 或 event_id, 以及 config, customQuestions
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

        // 解析參數 (與 create 邏輯一致)
        $config          = $request->input('config', []);
        $title           = $request->input('title') ?? ($config['title'] ?? null);
        $description     = $request->input('description') ?? ($config['description'] ?? null);
        $standardFields  = $request->input('standardFields') ?? ($config['standardFields'] ?? []);
        $customQuestions = $request->input('customQuestions') ?? ($config['customQuestions'] ?? []);

        try {
            // 整理所有題目
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

            // 呼叫 Service 進行全量同步 (刪除舊的再新增)
            $this->googleApi->syncFormItems($googleForm->form_id, $questions, $title, $description);

            // 如果有傳入 type，同步更新資料庫
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
     * 刪除已綁定的 Google 表單紀錄 (解除綁定)
     * 
     * @param Request $request 僅包含 id (GoogleForm 主鍵)
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
}
