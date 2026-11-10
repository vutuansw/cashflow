<?php

use App\Http\Controllers\System\AuthController;
use App\Http\Controllers\System\DashboardController;
use App\Http\Controllers\System\GroupController;
use App\Http\Middleware\EnsureSystemAdmin;
use Illuminate\Support\Facades\Route;

// /api/system: quản trị HIPOTECH, cookie session riêng, xem plan/02 mục 6.
Route::get('/csrf', [AuthController::class, 'csrf']);
Route::get('/me', [AuthController::class, 'me']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware(EnsureSystemAdmin::class)->group(function () {
    Route::put('/me/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1');
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/ai-costs', [DashboardController::class, 'aiCosts']);
    Route::get('/audit-logs', [DashboardController::class, 'auditLogs']);
    Route::get('/admins', [DashboardController::class, 'admins']);
    Route::post('/admins', [DashboardController::class, 'storeAdmin']);
    Route::put('/admins/{admin}', [DashboardController::class, 'updateAdmin'])->whereNumber('admin');

    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{group}', [GroupController::class, 'show'])->whereNumber('group');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->whereNumber('group');
    Route::post('/groups/{group}/review', [GroupController::class, 'review'])->whereNumber('group');
    Route::post('/groups/{group}/lock', [GroupController::class, 'lock'])->whereNumber('group');
    Route::post('/groups/{group}/restore', [GroupController::class, 'restore'])->whereNumber('group');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->whereNumber('group');
});
