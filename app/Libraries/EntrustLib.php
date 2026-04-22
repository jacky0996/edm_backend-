<?php

namespace App\Libraries;

use App\Models\DocumentCount;
use App\Models\Files;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;

class EntrustLib
{
    public static function DeleteButton($id, $name = '', $type = '')
    {
        $del = Lang::get('button.delete');
        $res = "<div class='row justify-content-center'>
                    <button class='btn btn-icon btn-circle btn-light-danger font-weight-bolder' link='{$id}' data-type='{$type}' onclick='Delete{$name}(this)' data-toggle='tooltip' title='{$del}'>
                        <i class='fas fa-trash-alt'></i>
                    </button>
                </div>";

        return $res;
    }

    public static function RestoreButton($id, $name = '')
    {
        $del = Lang::get('button.restore');
        $res = "<div class='row justify-content-center'>
                    <button class='btn btn-icon btn-circle btn-light-info font-weight-bolder' link='{$id}' onclick='Restore{$name}(this)' data-toggle='tooltip' title='{$del}'>
                        <i class='fas fa-box-open'></i>
                    </button>
                </div>";

        return $res;
    }

    public static function DocumentNumber($status, $sn = null)
    {
        $date = date('ymd');
        $date_ym = date('Ym');
        $document = DocumentCount::find(1);

        switch ($status) {
            case 'event':
                $count = $document->event + 1;
                $document->event = $count;
                $document_number = 'E'.$date.sprintf('%03d', $count);
                break;
        }

        $document->save();

        return $document_number;
    }

    public static function Progress($progress)
    {
        $bg = 'warning';
        switch ($progress) {
            case $progress < 50:
                $bg = 'warning';
                break;

            case $progress < 100:
                $bg = 'success';
                break;

            case 100:
                $bg = 'primary';
                break;
        }

        $res = "<div class='progress'><div class='progress-bar progress-bar-striped progress-bar-animated bg-{$bg}' role='progressbar' style='width:{$progress}%' aria-valuenow='10' aria-valuemin='0' aria-valuemax='100'></div></div>";

        return $res;
    }

    public static function CheckButton($id, $status = 0, $function_name = 'check')
    {
        $color = 'btn-outline-danger';
        $button = '<i class="fas fa-times"></i>';
        if ($status == 0) {
            $color = 'btn-outline-danger';
            $button = '<i class="fas fa-times"></i>';
        } elseif ($status == 1) {
            $color = 'btn-light-primary';
            $button = '<i class="fas fa-check"></i>';
        }
        // elseif ($status == 2) {
        //     $color = "btn-light-warning";
        //     $button = "<i class='fas fa-exclamation'></i>";
        // }

        $res = "<div class='row justify-content-center'><button class='btn btn-icon btn-circle btn-sm {$color}' id='{$function_name}button{$id}' onclick='{$function_name}({$id})'>{$button}</button></div>";

        return $res;
    }

    public static function FileButton($id, $status, $name = '')
    {
        $res = '';
        $delete = Lang::get('button.delete');
        $handle = Lang::get('button.handle');
        $download = Lang::get('button.download');
        $error = Lang::get('common.error');
        $file = Files::find($id);
        $id = Crypt::encryptString($id);

        if ($status == 0) { // 處理中
            $res .= "<div class='row justify-content-center'>
                        <button disabled class='btn btn-light-success font-weight-bolder'>
                            <div class='spinner-border spinner-border-sm text-success mr-2' role='status'>
                                <span class='sr-only'>Loading...</span>
                            </div>
                            {$handle} . . .
                        </button>
                    </div>";
        } elseif ($status == 1) { // 下載
            $res .= "<div class='row justify-content-center'>
                        <a class='btn btn-light-primary font-weight-bolder' link_id='{$id}' onclick='{$name}downLoad(this)'>
                            <i class='fas fa-download'></i>{$download}
                        </a>
                        <button class='btn btn-light-danger font-weight-bolder ml-2' link_id='{$id}' onclick='{$name}FileDelete(this)'>
                            <i class='fas fa-trash-alt'></i>{$delete}
                        </button>
                    </div>";
        } elseif ($status == 2) { // 錯誤
            $res .= "<div class='row justify-content-center'>
                        <span class='label label-lg font-weight-bolder label-light-warning label-inline justify-content-center' style='margin-top: 8px;'>
                            <i class='fas fa-error'></i>{$error}
                        </span>
                        <button class='btn btn-light-danger font-weight-bolder ml-1' link_id='{$id}' onclick='{$name}FileDelete(this)'>
                            <i class='fas fa-trash-alt'></i>{$delete}
                        </button>
                    </div>";
        }

        return $res;
    }

    /**
     * 一次建立多筆單號
     *
     * @param  string  $status  單號類型
     * @param  int  $num  建立筆數
     * @return array
     */
    public static function createMultipleDocumentNumber($status, $num = null)
    {
        $date = date('ymd');
        $date_ym = date('Ym');
        $document = DocumentCount::lockForUpdate()->find(1);
        $document_number = [];
        switch ($status) {
            case 'clock': // 工時母單
                for ($i = 1; $i <= $num; $i++) {
                    $count = $document->clock + $i;
                    $document_number[] = 'H'.$date.sprintf('%04d', $count);
                }
                $document->clock = $document->clock + $num;
                break;
        }

        $document->save();

        return $document_number;
    }
}
