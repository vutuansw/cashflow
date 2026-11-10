<?php

use App\Http\Controllers\Group\MediaController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// Ảnh trang cá nhân, xem plan/08 mục 4. Khai báo trước /media/{group}; mã nhóm dài ít nhất 3 ký tự nên không trùng "u".
Route::get('/media/u/{user}/{kind}/{name}', [MediaController::class, 'profile'])
    ->whereNumber('user')->whereIn('kind', ['avatar', 'cover'])->where('name', '[a-z0-9-]+');

// Ảnh của nhóm: kiểm quyền rồi mới trả file, xem plan/03 mục 3.
Route::prefix('/media/{group}')->middleware('group')->group(function () {
    Route::get('/p/{photo}/{size}', [MediaController::class, 'photo'])->whereNumber('photo')->whereIn('size', ['sm', 'lg']);
    Route::get('/b/{scan}', [MediaController::class, 'bill'])->whereNumber('scan');
    Route::get('/brand/cover/{name}', [MediaController::class, 'groupCover'])->where('name', '[a-z0-9-]+');
});

// Mọi đường dẫn giao diện do vue-router xử lý; chỉ trừ API và file tĩnh.
Route::get('/{path?}', SpaController::class)
    ->where('path', '^(?!api/|up$|build/|storage/|media/|icons/|sw\.js$|manifest\.webmanifest$|offline\.html$).*$')
    ->name('spa');
