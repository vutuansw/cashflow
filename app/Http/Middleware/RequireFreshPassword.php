<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tài khoản đang dùng mật khẩu tạm (QTV vừa cấp hoặc đặt lại) phải đổi mật
 * khẩu trước khi làm bất cứ việc gì khác. Xem plan/02 mục 1.
 */
class RequireFreshPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Bạn cần đổi mật khẩu tạm trước khi tiếp tục.',
                'code' => 'must_change_password',
            ], 403);
        }

        return $next($request);
    }
}
