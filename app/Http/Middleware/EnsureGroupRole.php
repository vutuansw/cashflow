<?php

namespace App\Http\Middleware;

use App\Enums\GroupRole;
use App\Models\GroupMember;
use App\Support\CurrentGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * group.role:member | group.role:admin | group.role:owner
 *
 * Người đăng nhập phải đang thuộc nhóm hiện tại với vai trò tối thiểu như
 * tham số. Trưởng nhóm đạt mọi mức, quản trị viên đạt admin và member. Chạy
 * sau middleware group. Bản ghi thành viên gắn vào request để controller dùng
 * lại. Xem bảng quyền ở plan/02 mục 3.
 */
class EnsureGroupRole
{
    public function __construct(private readonly CurrentGroup $current) {}

    public function handle(Request $request, Closure $next, string $minimum = 'member'): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401, 'Bạn cần đăng nhập.');
        }

        $member = GroupMember::query()
            ->where('group_id', $this->current->require()->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($member === null || ! self::reaches($member->role, GroupRole::from($minimum))) {
            abort(403, 'Bạn không có quyền làm việc này trong nhóm.');
        }

        $request->attributes->set('group_member', $member);

        return $next($request);
    }

    public static function reaches(GroupRole $role, GroupRole $minimum): bool
    {
        $rank = [GroupRole::Member->value => 1, GroupRole::Admin->value => 2, GroupRole::Owner->value => 3];

        return $rank[$role->value] >= $rank[$minimum->value];
    }
}
