<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EDM\MemberController;
use App\Http\Controllers\EDM\GroupController;
use App\Http\Controllers\EDM\EventController;
use App\Http\Controllers\EDM\MailController;
use App\Http\Controllers\EDM\SSOController;
use App\Http\Middleware\AuthorizeJwt;

// Route::middleware([AuthorizeJwt::class])->prefix('edm')->group(function () {
Route::prefix('edm')->group(function () {
    Route::prefix('member')->group(function () {
        Route::post('/list', [MemberController::class, 'list']);
        Route::post('/view', [MemberController::class, 'view']);
        Route::post('/add', [MemberController::class, 'add']);
        Route::post('/editStatus', [MemberController::class, 'editStatus']);
        Route::post('/editEmail', [MemberController::class, 'editEmail']);
        Route::post('/editMobile', [MemberController::class, 'editMobile']);
        Route::post('/editSales', [MemberController::class, 'editSales']);
    });

    Route::prefix('group')->group(function () {
        Route::post('/list', [GroupController::class, 'list']);
        Route::post('/view', [GroupController::class, 'view']);
        Route::post('/editStatus', [GroupController::class, 'editStatus']);
        Route::post('/create', [GroupController::class, 'create']);
        Route::post('/getEventList', [GroupController::class, 'getEventList']);
    });

    Route::prefix('event')->group(function () {
        Route::post('/list', [EventController::class, 'list']);
        Route::post('/view', [EventController::class, 'view']);
        Route::post('/create', [EventController::class, 'create']);
        Route::post('/update', [EventController::class, 'update']);
        Route::post('/editStatus', [EventController::class, 'editStatus']);
        Route::post('/imageUpload', [EventController::class, 'imageUpload']);
        Route::post('/getImage', [EventController::class, 'getImage']);
        Route::post('/getInviteList', [EventController::class, 'getInviteList']);
        Route::post('/importGroup', [EventController::class, 'importGroup']);
        Route::post('/getDisplayList', [EventController::class, 'getDisplayList']);
        Route::post('/updateDisplay', [EventController::class, 'updateDisplay']);
        Route::post('/updateGoogleForm', [EventController::class, 'updateGoogleForm']);
        Route::post('/createGoogleForm', [EventController::class, 'createGoogleForm']);
        Route::post('/delGoogleForm', [EventController::class, 'delGoogleForm']);
        Route::post('/getGoogleForm', [EventController::class, 'getGoogleForm']);
        Route::post('/updateResponseStatus', [EventController::class, 'updateResponseStatus']);
    });
    Route::prefix('mail')->group(function () {
        Route::post('/inviteMail', [MailController::class, 'inviteMail']);
    });

});
