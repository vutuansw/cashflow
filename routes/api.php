<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\GroupAdmin\CategoryController;
use App\Http\Controllers\GroupAdmin\ContactController;
use App\Http\Controllers\GroupAdmin\MemberController;
use App\Http\Controllers\GroupAdmin\SettingsController;
use App\Http\Controllers\GroupController;
use Illuminate\Support\Facades\Route;

// Thông tin chung cho SPA: tên sản phẩm, mô tả. Không cần đăng nhập.
Route::get('/app', fn () => [
    'name' => config('cashflow.brand.name'),
    'description' => config('cashflow.brand.description'),
]);

// Tài khoản, xem plan/02 mục 1-2.
Route::get('/me', [SessionController::class, 'show']);
Route::get('/group-code', [GroupController::class, 'checkCode'])->middleware('throttle:60,1');

Route::middleware('guest')->group(function () {
    Route::post('/register', RegisterController::class)->middleware('throttle:register');
    Route::post('/login', [SessionController::class, 'store']);
    Route::post('/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);
    Route::put('/me/password', [PasswordController::class, 'update']);

    Route::middleware('password.fresh')->group(function () {
        Route::put('/me', [PasswordController::class, 'profile']);
        Route::post('/groups', [GroupController::class, 'store'])->middleware('throttle:10,60');
    });
});

// Quản trị nhóm: trưởng nhóm và quản trị viên, xem plan/02 mục 3.
Route::prefix('/g/{group}/admin')
    ->middleware(['auth', 'password.fresh', 'group', 'group.role:admin'])
    ->group(function () {
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/contacts', [ContactController::class, 'index']);
        Route::post('/contacts', [ContactController::class, 'store']);
        Route::post('/contacts/import/preview', [ContactController::class, 'importPreview']);
        Route::post('/contacts/import', [ContactController::class, 'import']);
        Route::put('/contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::put('/members/{memberId}', [MemberController::class, 'update'])->whereNumber('memberId');
        Route::post('/members/{memberId}/reset-password', [MemberController::class, 'resetPassword'])->whereNumber('memberId');
        Route::delete('/members/{memberId}', [MemberController::class, 'destroy'])->whereNumber('memberId');

        Route::middleware('group.role:owner')->group(function () {
            Route::post('/transfer-owner', [SettingsController::class, 'transferOwner']);
            Route::delete('/', [SettingsController::class, 'destroy']);
        });
    });
