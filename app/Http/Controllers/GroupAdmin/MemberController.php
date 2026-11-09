<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Enums\GroupRole;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Services\Accounts\AccountGranter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Tài khoản và vai trò trong nhóm, xem plan/02 mục 3-4.
 *
 * Quy tắc: quản trị viên cấp được tài khoản Thành viên, đặt lại mật khẩu và
 * thu hồi tài khoản của Thành viên. Chỉ trưởng nhóm mới phong/hạ/thu hồi/đặt
 * lại mật khẩu của Quản trị viên. Không ai tự thao tác lên chính mình ở đây.
 */
class MemberController extends Controller
{
    public function index(Group $group)
    {
        return GroupMember::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->with(['user', 'contact'])
            ->get()
            ->sortBy(fn (GroupMember $m) => [$this->rank($m->role), $m->user->name])
            ->values()
            ->map(fn (GroupMember $m) => $this->present($m));
    }

    public function store(Request $request, Group $group, AccountGranter $granter)
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer'],
            'role' => ['required', 'in:member,admin'],
        ]);

        $role = GroupRole::from($data['role']);
        $this->ensureCanAssign($request, $role);

        $contact = Contact::find($data['contact_id'])
            ?? throw ValidationException::withMessages(['contact_id' => 'Không tìm thấy người này trong danh bạ.']);

        $result = $granter->grant($group, $contact, $role);
        $member = $result['member'];

        return response()->json([
            'member' => $this->present($member->load('contact')),
            'temp_password' => $result['temp_password'],
            'share_message' => $granter->shareMessage($group, $member->user, $result['temp_password']),
        ], 201);
    }

    public function update(Request $request, Group $group, int $memberId)
    {
        $data = $request->validate(['role' => ['required', 'in:member,admin']]);
        $member = $this->target($request, $group, $memberId);

        $this->ensureCanAssign($request, GroupRole::from($data['role']));
        $member->update(['role' => GroupRole::from($data['role'])]);

        return $this->present($member->load(['user', 'contact']));
    }

    public function resetPassword(Request $request, Group $group, int $memberId, AccountGranter $granter)
    {
        $member = $this->target($request, $group, $memberId);
        $password = $granter->resetPassword($member->user);

        return [
            'temp_password' => $password,
            'share_message' => $granter->shareMessage($group, $member->user, $password),
        ];
    }

    public function destroy(Request $request, Group $group, int $memberId)
    {
        $member = $this->target($request, $group, $memberId);
        $member->update(['status' => 'removed']);

        return response()->noContent();
    }

    /** Tài khoản đích: thuộc nhóm này, không phải mình, không phải trưởng nhóm, và mình đủ quyền với nó. */
    private function target(Request $request, Group $group, int $memberId): GroupMember
    {
        $me = $request->attributes->get('group_member');
        $member = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->with('user')
            ->findOrFail($memberId);

        if ($member->id === $me->id) {
            abort(403, 'Bạn không tự thay đổi tài khoản của mình ở đây. Dùng trang Cá nhân.');
        }
        if ($member->role === GroupRole::Owner) {
            abort(403, 'Không thay đổi được tài khoản trưởng nhóm.');
        }
        if ($member->role === GroupRole::Admin && $me->role !== GroupRole::Owner) {
            abort(403, 'Chỉ trưởng nhóm mới thay đổi được tài khoản quản trị viên.');
        }

        return $member;
    }

    private function ensureCanAssign(Request $request, GroupRole $role): void
    {
        $me = $request->attributes->get('group_member');
        if ($role === GroupRole::Admin && $me->role !== GroupRole::Owner) {
            abort(403, 'Chỉ trưởng nhóm mới cấp được quyền quản trị viên.');
        }
    }

    private function rank(GroupRole $role): int
    {
        return match ($role) {
            GroupRole::Owner => 0,
            GroupRole::Admin => 1,
            GroupRole::Member => 2,
        };
    }

    private function present(GroupMember $m): array
    {
        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'name' => $m->user->name,
            'phone' => $m->user->phone,
            'email' => $m->user->email,
            'role' => $m->role->value,
            'role_label' => $m->role->label(),
            'contact_id' => $m->contact_id,
            'must_change_password' => $m->user->must_change_password,
            'last_login_at' => $m->user->last_login_at?->toIso8601String(),
        ];
    }
}
