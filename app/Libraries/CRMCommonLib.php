<?php

namespace App\Libraries;

use App\Models\CRM\Common\CRMUser;
use App\Models\CRM\Common\Detect;
use App\Models\CRM\Device\Device;
use App\Models\CRM\Obstacle\Obstacle;
use App\Models\CRM\Relation\HasBind;
use App\Models\CRM\Relation\HasTrack;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;

class CRMCommonLib
{
    public static function PhoneMask($phone)
    {
        $decrypted = $phone;

        if (is_numeric(trim($decrypted))) {
            $str = mb_substr($decrypted, 0, -1, 'utf-8');

            return substr_replace($str, '***', 4, 3);
        }

            return $decrypted;
    }

    public static function MailMask($email)
    {
        $x = strpos($email, '@');

        if ($x == 1) {
            $z = substr_replace($email, '*', 0, 1);
        } else {
            $str = '';
            $y   = round(($x - 1) / 2);
            for ($i = 0; $i < $y; ++$i) {
                $str .= '*';
            }

            $z = substr_replace($email, $str, ($x - $y), $y);
        }

        return $z;
    }

    public static function NameMask($name)
    {
        $str = $name;
        $len = mb_strlen($str, 'utf-8');
        if ($len >= 6) {
            $str1 = mb_substr($str, 0, 2, 'utf-8');
            $str2 = mb_substr($str, $len - 2, 2, 'utf-8');
        } else {
            $str1 = mb_substr($str, 0, 1, 'utf-8');
            $str2 = mb_substr($str, $len - 1, 1, 'utf-8');
        }
        $res = $str1 . '***' . $str2;

        return $res;
    }

    public function PhoneAreaCode($phone)
    {
        $res = '';

        if (strlen($phone) == 10 && substr($phone, 0, 1) == 0) {
            $res = substr($phone, 0, 2);
        } elseif (strlen($phone) == 11) {
            $res = substr($phone, 0, strpos($phone, '-'));
        }

        return $res;
    }

    public function PhoneCode($phone)
    {
        $res = trim(strrchr($phone, '-'), '-');

        return $res;
    }

    public function EnumberToName($enumber)
    {
        $user = CRMUser::where('enumber', $enumber)->first();
        if ($user) {
            return $user->name;
        }

            return Lang::get('alert.no_data');
    }

    public function LabelRounded($count)
    {
        if ($count == 0) {
            return '';
        }

            return "<span class='label label-rounded label-danger'>{$count}</span>";
    }

    public static function StarButton($id, $type)
    {
        $res   = null;
        $uid   = Auth::id();
        $track = HasTrack::where('user_id', $uid)->where('trackable_id', $id)->where('trackable_type', $type)->first();

        if ($track) {
            $res = "<i class='fa fa-star text-star star' style='cursor: pointer' onclick='popTrack({$id},\"{$type}\")'></i>";
        } else {
            $res = "<i class='far fa-star text-star star' style='cursor: pointer' onclick='pushTrack({$id},\"{$type}\")'></i>";
        }

        return $res;
    }

    public static function StatusValid($bid)
    {
        $res   = false;
        $datas = HasBind::where('bind_id', $bid)->get();

        foreach ($datas as $data) {
            $res = true;

            switch ($data->bindable_type) {
                case 'App\Models\CRM\Obstacle': //客訴
                    $data   = Obstacle::withTrashed()->find($data->bindable_id);
                    $status = $data->status;

                    if ($status < 10) { // 結案或觀察後異常
                        return false;
                    }
                    break;

                case \App\Models\CRM\Common\Detect::class: //檢測or維修
                    $data = Detect::withTrashed()->find($data->bindable_id);

                    if ($data->detect_number) {
                        $status = $data->status;

                        if ($status != 3) { // 檢測完畢
                            return false;
                        }
                    }

                    if ($data->repair_number) {
                        $status = $data->repair_status;

                        if ($status != 3) { // 維修完畢
                            return false;
                        }
                    }
                    break;
            }
        }

        return $res;
    }

    public static function DeviceWarranty($device_id, $device = null) // 判斷設備保固狀態
    {
        /*
        輸入device_custom的id，判斷保固狀態
        返回值:
            0:預設值，無進入判斷
            1:合約過保
            2:合約保固中，原廠過保
            3:合約保固中，原廠保固中
        */
        try {
            $warranty_status = 0;

            if (!$device) {
                $device = Device::find($device_id);
            }

            $project         = $device->project;
            $project_custom  = $project->project_custom;
            $device_warranty = $device->warranty_last;
            $todate          = date('Y-m-d');
            if ($project) { // 判斷合約保固 'ERP'
                $project_warranty = $project->contract_end_date;

                if ($project_warranty) { // 若有合約保固日期
                    if (strtotime($project_warranty) >= strtotime($todate)) { // 合約保固中
                        $warranty_status = 3;
                    } else {
                        $warranty_status = 1;
                    }
                }
            }

            if ($project_custom) { // 判斷合約保固 'CUSTOM'
                $project_custom_warranty = $project_custom->contract_end_date;
                if ($project_custom_warranty) { // 若有合約保固日期
                    if (strtotime($project_custom_warranty) >= strtotime($todate)) { // 合約保固中
                        $warranty_status = 3;
                    } else {
                        $warranty_status = 1;
                    }
                }
            }
            if ($device_warranty && $warranty_status != 1 && strtotime($device_warranty) != '-62170013160') { // 判斷設備原廠保固 'CUSTOM'
                if (strtotime($device_warranty) < strtotime($todate)) { // 設備原廠過保
                    $warranty_status = 2;
                }
            }
        } catch (\Exception $e) {
        }

        return $warranty_status;
    }

    public static function ContactLink($data, $type)
    {
        $tooltip  = Lang::get('tooltip/contact.contact_link');
        $text     = Lang::get('CRM/contact.no_binding');
        $res      = "<span style='font-size: 10pt;' class='text-muted' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link mr-1'></i>{$text}</span>";
        $contacts = $data->contact_record;

        if ($contacts) {
            $total = $contacts->count();

            if ($total == 1) {
                $contact = $contacts->first();
                $res     = "<a style='font-size: 10pt;' class='font-weight-bolder' href='/contact_records/view/{$contact->id}' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link text-primary mr-1'></i>{$contact->contact_number}</a>";
            } elseif ($total > 1) {
                switch ($type) {
                    case 'obstacle':
                        $binding_number = $data->obstacle_number;
                        break;

                    case 'detect':
                        $binding_number = $data->detect_number;
                        break;

                    case 'repair':
                        $binding_number = $data->repair_number;
                        break;
                }
                $text = Lang::get('CRM/contact.many_binding');
                $res  = "<a style='font-size: 10pt;' class='font-weight-bolder' href='/contact_records/list' id='many_contact_record' link='{$binding_number}' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link text-primary mr-1'></i>{$text}</a>";
            }
        }

        return $res;
    }

    public static function CreateLabel($word, $color)
    {
        return "<span class='label label-lg font-weight-bolder label-light-{$color} label-inline mx-1'>{$word}</span>";
    }

    // Mail 格式驗證
    public static function verityMail($mail): bool
    {
        if (preg_match('/^[-A-Za-z0-9_]+[-A-Za-z0-9_.]*[@]{1}[-A-Za-z0-9_]+[-A-Za-z0-9_.]*[.]{1}[A-Za-z]{2,5}$/', $mail)) {
            return true;
        }

            return false;
    }

}
