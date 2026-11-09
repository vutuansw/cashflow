<?php

use Illuminate\Support\Facades\Route;

// Mọi đường dẫn giao diện do vue-router xử lý; chỉ trừ API và file tĩnh.
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api/|up$|build/|storage/).*$')
    ->name('spa');
