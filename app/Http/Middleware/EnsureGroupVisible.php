<?php

namespace App\Http\Middleware;

use App\Services\Groups\GroupViewer;
use App\Support\CurrentGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * group.view: nhóm public ai có link cũng xem được; nhóm private chỉ thành
 * viên đã đăng nhập. Chạy sau middleware group. Xem plan/03 mục 4.
 */
class EnsureGroupVisible
{
    public function __construct(private readonly CurrentGroup $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $viewer = GroupViewer::of($request, $this->current->require());

        if (! $viewer->canView()) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                abort(403);
            }

            return response()->json([
                'message' => $viewer->user === null
                    ? 'Nhóm riêng tư, đăng nhập để xem.'
                    : 'Nhóm riêng tư, chỉ thành viên của nhóm xem được.',
                'code' => 'private_group',
            ], 403);
        }

        return $next($request);
    }
}
