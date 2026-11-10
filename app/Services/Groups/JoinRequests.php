<?php

namespace App\Services\Groups;

use App\Enums\GroupRole;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Xin vào nhóm qua link /{ma-nhom}/register và QTV duyệt trong Danh bạ (plan/13).
 * Chưa duyệt thì người đó không có trong danh bạ, group_members hay chiến dịch.
 */
class JoinRequests
{
    public function isMember(Group $group, User $user): bool
    {
        return GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function pendingFor(Group $group, User $user): ?GroupJoinRequest
    {
        return GroupJoinRequest::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();
    }

    /** member | pending | none */
    public function statusOf(Group $group, ?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return match (true) {
            $this->isMember($group, $user) => 'member',
            $this->pendingFor($group, $user) !== null => 'pending',
            default => 'none',
        };
    }

    /** Gửi yêu cầu. Đã có yêu cầu chờ thì trả lại yêu cầu đó; bị từ chối trước đây thì mở lại. */
    public function request(Group $group, User $user): GroupJoinRequest
    {
        if (! $group->join_link_enabled) {
            abort(403, 'Nhóm này không nhận đăng ký qua link.');
        }

        if ($this->isMember($group, $user)) {
            throw ValidationException::withMessages(['group' => 'Bạn đã ở trong nhóm này.']);
        }

        $request = GroupJoinRequest::query()->firstOrNew(['group_id' => $group->id, 'user_id' => $user->id]);

        if (! $request->exists || $request->status !== 'pending') {
            $request->fill(['status' => 'pending', 'contact_id' => null, 'decided_by' => null, 'decided_at' => null]);
            $request->created_at = now();
            $request->save();
        }

        return $request;
    }

    /** Người trong danh bạ có cùng SĐT với người xin vào (nếu có). */
    public function matchingContact(Group $group, User $user): ?Contact
    {
        return Contact::acrossGroups()->where('group_id', $group->id)->wherePhone($user->phone)->first();
    }

    /**
     * Duyệt: vào nhóm với vai trò Thành viên. SĐT đã có trong danh bạ thì gắn
     * vào dòng đó, chưa có thì tạo dòng mới (tự vào các chiến dịch đang mở).
     */
    public function approve(GroupJoinRequest $request, User $admin): GroupMember
    {
        $this->ensurePending($request);
        $group = $request->group;
        $user = $request->user;

        return DB::connection('groups')->transaction(function () use ($request, $group, $user, $admin) {
            $contact = $this->matchingContact($group, $user);

            if ($contact === null) {
                $contact = new Contact([
                    'full_name' => $user->name,
                    'phone' => $user->phone,
                    'sort_order' => (int) Contact::acrossGroups()->where('group_id', $group->id)->max('sort_order') + 1,
                ]);
                $contact->group_id = $group->id;
            } elseif ($contact->status !== 'active') {
                $contact->status = 'active';
            }
            $contact->forceFill(['user_id' => $user->id])->save();

            $member = GroupMember::query()->firstOrNew(['group_id' => $group->id, 'user_id' => $user->id]);
            if (! $member->exists || $member->status !== 'active') {
                $member->fill(['role' => GroupRole::Member, 'contact_id' => $contact->id, 'status' => 'active'])->save();
            }

            $request->update([
                'status' => 'approved',
                'contact_id' => $contact->id,
                'decided_by' => $admin->id,
                'decided_at' => now(),
            ]);

            return $member;
        });
    }

    public function reject(GroupJoinRequest $request, User $admin): void
    {
        $this->ensurePending($request);
        $request->update(['status' => 'rejected', 'decided_by' => $admin->id, 'decided_at' => now()]);
    }

    private function ensurePending(GroupJoinRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'Yêu cầu này đã được xử lý.']);
        }
    }
}
