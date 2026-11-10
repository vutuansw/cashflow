<?php

use App\Http\Controllers\Webhooks\SepayWebhookController;
use Illuminate\Support\Facades\Route;

// SePay báo biến động số dư, xem plan/05 mục 3. Không session, không CSRF; tự kiểm API key của nhóm.
Route::post('/sepay/{group}', SepayWebhookController::class)
    ->where('group', '[a-z0-9-]+')
    ->middleware('throttle:sepay');
