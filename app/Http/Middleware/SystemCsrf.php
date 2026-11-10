<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * CSRF cho /api/system: SPA lấy token từ /api/system/csrf và gửi header
 * X-CSRF-TOKEN. Không đặt cookie XSRF-TOKEN để khỏi ghi đè cookie của phiên
 * người dùng nhóm khi cùng trình duyệt.
 */
class SystemCsrf extends ValidateCsrfToken
{
    protected $addHttpCookie = false;
}
