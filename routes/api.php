<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Group\InteractionController;
use App\Http\Controllers\Group\TimelineController;
use App\Http\Controllers\GroupAdmin\BillScanController;
use App\Http\Controllers\GroupAdmin\CategoryController;
use App\Http\Controllers\GroupAdmin\ContactController;
use App\Http\Controllers\GroupAdmin\MemberController;
use App\Http\Controllers\GroupAdmin\OverviewController;
use App\Http\Controllers\GroupAdmin\SettingsController;
use App\Http\Controllers\GroupAdmin\TransactionController;
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

// Trang nhóm /{ma-nhom}: phần đầu luôn trả (nhóm private chỉ tên và ảnh),
// còn lại theo chế độ xem public/private. Xem plan/03 mục 4-6.
Route::prefix('/g/{group}')->middleware('group')->group(function () {
    Route::get('/', [TimelineController::class, 'show']);

    Route::middleware('group.view')->group(function () {
        Route::get('/summary', [TimelineController::class, 'summary']);
        Route::get('/timeline', [TimelineController::class, 'feed']);
        Route::get('/posts/{transaction}', [TimelineController::class, 'post'])->whereNumber('transaction');
        Route::get('/posts/{transaction}/comments', [InteractionController::class, 'comments'])->whereNumber('transaction');
    });

    Route::middleware(['auth', 'password.fresh', 'group.role:member'])->group(function () {
        Route::post('/posts/{transaction}/heart', [InteractionController::class, 'heart'])->whereNumber('transaction');
        Route::post('/posts/{transaction}/comments', [InteractionController::class, 'storeComment'])->whereNumber('transaction');
        Route::put('/comments/{comment}', [InteractionController::class, 'updateComment'])->whereNumber('comment');
        Route::delete('/comments/{comment}', [InteractionController::class, 'destroyComment'])->whereNumber('comment');
        Route::post('/comments/{comment}/hide', [InteractionController::class, 'hideComment'])->whereNumber('comment');
    });
});

// Quản trị nhóm: trưởng nhóm và quản trị viên, xem plan/02 mục 3.
Route::prefix('/g/{group}/admin')
    ->middleware(['auth', 'password.fresh', 'group', 'group.role:admin'])
    ->group(function () {
        Route::get('/overview', OverviewController::class);

        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/images', [SettingsController::class, 'uploadImage']);
        Route::delete('/settings/images/{kind}', [SettingsController::class, 'deleteImage'])->whereIn('kind', ['avatar', 'cover']);

        Route::post('/bill-scans', [BillScanController::class, 'store']);
        Route::get('/bill-scans/{scan}', [BillScanController::class, 'show'])->whereNumber('scan');

        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/options', [TransactionController::class, 'options']);
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->withTrashed();
        Route::post('/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);
        Route::post('/transactions/{transaction}/restore', [TransactionController::class, 'restore'])->withTrashed();

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
