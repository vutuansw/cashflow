<?php

namespace App\Http\Middleware;

use App\Models\Group;
use App\Services\GroupServiceStatus;
use App\Support\CurrentGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lấy nhóm từ tham số {group} (mã nhóm) trên route, chặn nhóm không tồn tại,
 * đã xóa hoặc đang bị khóa, rồi đặt làm nhóm hiện tại cho global scope của
 * BelongsToGroup. Kiểm tra quyền xem (public/private) và vai trò là việc của
 * middleware/policy chạy sau. Xem plan/plan.md, nguyên tắc 1.
 */
class ResolveGroup
{
    public function __construct(
        private readonly CurrentGroup $current,
        private readonly GroupServiceStatus $status,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->route('group');
        $code = $code instanceof Group ? $code->code : strtolower((string) $code);

        $group = Group::query()->where('code', $code)->first();

        if ($group === null) {
            abort(404, 'Không tìm thấy nhóm.');
        }

        if ($this->status->isLocked($group)) {
            abort(423, 'Nhóm đang tạm khóa.');
        }

        $this->current->set($group);
        $request->route()->setParameter('group', $group);

        return $next($request);
    }
}
