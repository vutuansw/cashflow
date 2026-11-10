<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Đã đăng nhập /system bằng tài khoản quản trị đang hoạt động. */
class EnsureSystemAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('system')->user();
        if ($admin === null) {
            return response()->json(['message' => 'Bạn cần đăng nhập trang quản trị.'], 401);
        }
        if ($admin->status !== 'active') {
            Auth::guard('system')->logout();

            return response()->json(['message' => 'Tài khoản quản trị đã bị khóa.'], 403);
        }

        return $next($request);
    }
}
