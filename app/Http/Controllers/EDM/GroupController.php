<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Models\EDM\Group;
use App\Repositories\EDM\GroupRepository;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 群組管理控制器 (Group Management Controller)
 *
 * 負責處理 EDM 系統中會員群組的所有相關操作，包括清單查詢、詳細資料檢視、
 * 群組狀態修改、新群組建立，以及查詢特定群組所參與的活動列表。
 */
class GroupController extends Controller
{
    /**
     * 群組資料儲存庫
     */
    protected GroupRepository $groupRepository;

    /**
     * 使用者資訊服務
     */
    protected UserService $userService;

    /**
     * GroupController 建構子
     *
     * @param  GroupRepository  $groupRepository  注入群組相關資料處理邏輯
     * @param  UserService  $userService  注入使用者資訊處理服務
     */
    public function __construct(GroupRepository $groupRepository, UserService $userService)
    {
        $this->userService = $userService;
        $this->groupRepository = $groupRepository;
    }

    /**
     * 取得群組清單 (分頁)
     *
     * 根據請求參數過濾並回傳分頁後的群組列表。
     *
     * @param  Request  $request  包含 page (頁碼, 預設 1), pageSize (每頁筆數, 預設 20)
     * @return JsonResponse 包含分頁群組資料及總筆數
     */
    public function list(Request $request)
    {

        $page = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('pageSize', 20);
        $data = $this->groupRepository->GetList($request->all());
        $offset = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => [
                'items' => array_values($pagedData), // array_values 重新索引
                'total' => count($data),
            ],
        ]);
    }

    /**
     * 取得特定群組的詳細資料
     *
     * 預載入該群組旗下的所有會員資料。
     *
     * @param  Request  $request  包含 id (群組 ID)
     * @return JsonResponse 回傳群組與會員關聯資料
     */
    public function view(Request $request)
    {
        $group = Group::with(['members'])->find($request->input('id'));

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $group,
        ]);
    }

    /**
     * 更新群組的使用狀態
     *
     * @param  Request  $request  包含 group_id (群組 ID) 與 status (欲變更的狀態值)
     * @return JsonResponse
     */
    public function editStatus(Request $request)
    {
        $group = Group::find($request->input('group_id'));
        $group->status = $request->input('status');
        $group->save();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $group,
        ]);
    }

    /**
     * 建立新群組
     *
     * 根據目前登入者的資訊建立新群組，預設狀態為 0。
     *
     * @param  Request  $request  包含 group_name (群組名稱) 與 note (備註)
     * @return JsonResponse 回傳新建好的群組物件
     */
    public function create(Request $request)
    {
        $user = $this->userService->getUserFromHeader($request);
        $group = new Group;
        $group->name = $request->input('group_name');
        $group->note = $request->input('note');
        $group->creator_enumber = $user['enumber'];
        $group->status = 0;
        $group->save();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $group,
        ]);
    }

    /**
     * 取得特定群組所參與的活動清單
     *
     * 透過事件關聯表 (EventRelation) 找出有使用到該群組的所有活動 ID。
     *
     * @param  Request  $request  包含 group_id (群組 ID)
     * @return JsonResponse 回傳活動物件列表
     */
    public function getEventList(Request $request)
    {
        $eventRelation = EventRelation::where('group_id', $request->input('group_id'))->distinct()->pluck('event_id')->toArray();
        $data = Event::whereIn('id', $eventRelation)->get();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $data,
        ]);
    }
}
