<?php

use Illuminate\Support\Facades\Route;

// Thông tin chung cho SPA: tên sản phẩm, mô tả. Không cần đăng nhập.
Route::get('/app', fn () => [
    'name' => config('cashflow.brand.name'),
    'description' => config('cashflow.brand.description'),
]);
