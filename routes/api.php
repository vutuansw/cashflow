<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Group\CampaignPageController;
use App\Http\Controllers\Group\InteractionController;
use App\Http\Controllers\Group\JoinController;
use App\Http\Controllers\Group\MemberProfileController;
use App\Http\Controllers\Group\TimelineController;
use App\Http\Controllers\GroupAdmin\BillScanController;
use App\Http\Controllers\GroupAdmin\CampaignController;
use App\Http\Controllers\GroupAdmin\CategoryController;
use App\Http\Controllers\GroupAdmin\ContactController;
use App\Http\Controllers\GroupAdmin\JoinRequestController;
use App\Http\Controllers\GroupAdmin\MemberController;
use App\Http\Controllers\GroupAdmin\OverviewController;
use App\Http\Controllers\GroupAdmin\PaymentClaimController;
use App\Http\Controllers\GroupAdmin\ReportController;
use App\Http\Controllers\GroupAdmin\SepayQueueController;
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
// throttle:x,y không tên thì mọi route dùng CHUNG một bộ đếm cho mỗi người dùng (Laravel đếm theo user id),
// nên tham số thứ ba đặt tên riêng cho từng route: gõ tên nhóm (group-code) không làm hết lượt tạo nhóm.
Route::get('/me', [SessionController::class, 'show']);
Route::get('/group-code', [GroupController::class, 'checkCode'])->middleware('throttle:60,1,group-code');

Route::middleware('guest')->group(function () {
    Route::post('/register', RegisterController::class)->middleware('throttle:register');
    Route::post('/login', [SessionController::class, 'store']);
    Route::post('/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:5,1,forgot-password');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1,reset-password');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);
    Route::put('/me/password', [PasswordController::class, 'update']);

    Route::middleware('password.fresh')->group(function () {
        // Trang cá nhân, xem plan/08 mục 3-4.
        Route::put('/me', [ProfileController::class, 'update']);
        Route::post('/me/images', [ProfileController::class, 'uploadImage'])->middleware('throttle:10,1,profile-image');
        Route::delete('/me/images/{kind}', [ProfileController::class, 'deleteImage'])->whereIn('kind', ['avatar', 'cover']);
        Route::post('/groups', [GroupController::class, 'store'])->middleware('throttle:10,60,group-create');
    });
});

// Trang nhóm /{ma-nhom}: phần đầu luôn trả (nhóm private chỉ tên và ảnh),
// còn lại theo chế độ xem public/private. Xem plan/03 mục 4-6.
Route::prefix('/g/{group}')->middleware('group')->group(function () {
    Route::get('/', [TimelineController::class, 'show']);

    // Link đăng ký vào nhóm /{ma-nhom}/register, xem plan/13.
    Route::get('/join', [JoinController::class, 'show']);
    Route::post('/join/register', [JoinController::class, 'register'])->middleware(['guest', 'throttle:register']);
    Route::post('/join', [JoinController::class, 'store'])->middleware(['auth', 'password.fresh', 'throttle:10,1,join-group']);

    Route::middleware('group.view')->group(function () {
        Route::get('/summary', [TimelineController::class, 'summary']);
        Route::get('/timeline', [TimelineController::class, 'feed']);
        Route::get('/posts/{transaction}', [TimelineController::class, 'post'])->whereNumber('transaction');
        Route::get('/posts/{transaction}/comments', [InteractionController::class, 'comments'])->whereNumber('transaction');
        Route::get('/members', [MemberProfileController::class, 'index']);
        Route::get('/members/{member}', [MemberProfileController::class, 'show'])->whereNumber('member');
        Route::get('/members/{member}/timeline', [MemberProfileController::class, 'timeline'])->whereNumber('member');
        Route::get('/campaigns/{slug}', [CampaignPageController::class, 'show'])->where('slug', '[a-z0-9-]+');
        Route::post('/campaigns/{slug}/claims', [CampaignPageController::class, 'claim'])->where('slug', '[a-z0-9-]+');
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
        Route::delete('/settings/images/cover', [SettingsController::class, 'deleteImage']);

        Route::post('/settings/sepay', [SettingsController::class, 'sepay']);
        Route::put('/settings/notifications', [SettingsController::class, 'notifications']);
        Route::put('/settings/join-link', [SettingsController::class, 'joinLink']);

        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::get('/campaigns/options', [CampaignController::class, 'options']);
        Route::post('/campaigns', [CampaignController::class, 'store']);
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->whereNumber('campaign');
        Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->whereNumber('campaign');
        Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->whereNumber('campaign');
        Route::post('/campaigns/{campaign}/cash', [CampaignController::class, 'cash'])->whereNumber('campaign');

        Route::get('/sepay', [SepayQueueController::class, 'index']);
        Route::get('/sepay/count', [SepayQueueController::class, 'count']);
        Route::post('/sepay/approve', [SepayQueueController::class, 'approveMany']);
        Route::get('/sepay/{sepay}', [SepayQueueController::class, 'show'])->whereNumber('sepay');
        Route::post('/sepay/{sepay}/approve', [SepayQueueController::class, 'approve'])->whereNumber('sepay');
        Route::post('/sepay/{sepay}/ignore', [SepayQueueController::class, 'ignore'])->whereNumber('sepay');
        Route::post('/sepay/{sepay}/restore', [SepayQueueController::class, 'restore'])->whereNumber('sepay');

        Route::get('/claims', [PaymentClaimController::class, 'index']);
        Route::get('/claims/{claim}', [PaymentClaimController::class, 'show'])->whereNumber('claim');
        Route::post('/claims/{claim}/approve', [PaymentClaimController::class, 'approve'])->whereNumber('claim');
        Route::post('/claims/{claim}/ignore', [PaymentClaimController::class, 'ignore'])->whereNumber('claim');
        Route::post('/claims/{claim}/restore', [PaymentClaimController::class, 'restore'])->whereNumber('claim');

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
        Route::post('/contacts/import/file', [ContactController::class, 'importFile']);
        Route::get('/contacts/import/template', [ContactController::class, 'importTemplate']);

        Route::get('/reports/{type}', [ReportController::class, 'show'])->whereIn('type', ['ledger', 'by-category', 'by-month', 'campaign']);
        Route::put('/contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);

        Route::get('/join-requests', [JoinRequestController::class, 'index']);
        Route::post('/join-requests/approve', [JoinRequestController::class, 'approveMany']);
        Route::post('/join-requests/{joinRequest}/approve', [JoinRequestController::class, 'approve'])->whereNumber('joinRequest');
        Route::post('/join-requests/{joinRequest}/reject', [JoinRequestController::class, 'reject'])->whereNumber('joinRequest');

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
