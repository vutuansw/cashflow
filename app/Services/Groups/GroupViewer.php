<?php

namespace App\Services\Groups;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Người đang xem một nhóm và họ được làm gì: xem timeline, thả tim/bình luận,
 * quản trị. Khách (chưa đăng nhập hoặc không thuộc nhóm) chỉ xem được nhóm
 * public. Xem bảng quyền ở plan/02 mục 3.
 */
class GroupViewer
{
    public function __construct(
        public readonly Group $group,
        public readonly ?User $user,
        public readonly ?GroupMember $member,
    ) {}

    /** Một lần cho mỗi request, dùng chung giữa middleware và controller. */
    public static function of(Request $request, Group $group): self
    {
        $cached = $request->attributes->get('group_viewer');
        if ($cached instanceof self && $cached->group->is($group)) {
            return $cached;
        }

        // Luôn là tài khoản người dùng nhóm (guard web), không bao giờ là quản trị /system.
        $user = $request->user('web');
        $member = $user === null ? null : GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $viewer = new self($group, $user, $member);
        $request->attributes->set('group_viewer', $viewer);

        return $viewer;
    }

    public function isMember(): bool
    {
        return $this->member !== null;
    }

    public function canView(): bool
    {
        return $this->group->isPublic() || $this->isMember();
    }

    /** Thả tim, bình luận: tài khoản của nhóm đã đổi mật khẩu tạm. */
    public function canInteract(): bool
    {
        return $this->isMember() && ! $this->user->must_change_password;
    }

    public function canManage(): bool
    {
        return $this->canInteract() && $this->member->role->canManage();
    }

    public function role(): ?GroupRole
    {
        return $this->member?->role;
    }

    public function toArray(): array
    {
        return [
            'logged_in' => $this->user !== null,
            'is_member' => $this->isMember(),
            'role' => $this->role()?->value,
            'role_label' => $this->role()?->label(),
            'can_interact' => $this->canInteract(),
            'can_manage' => $this->canManage(),
        ];
    }
}
