<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MailController extends Controller
{
    /**
     * 發送邀請信件
     */
    public function inviteMail(Request $request)
    {
        return $request->all();
    }
}
