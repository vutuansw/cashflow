<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/system dùng cookie session riêng (cashflow_system_session), tách hẳn
 * phiên của người dùng nhóm, xem plan/02 mục 1. Phải chạy trước StartSession.
 */
class UseSystemSession
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.cookie' => 'cashflow_system_session']);

        return $next($request);
    }
}
