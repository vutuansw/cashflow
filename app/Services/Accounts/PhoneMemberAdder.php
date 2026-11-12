<?php

namespace App\Services\Accounts;

use App\Enums\GroupRole;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\Groups\JoinRequests;
use App\Support\MediaUrl;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * QTV thêm thành viên bằng SĐT ở trang Danh bạ (plan/30 mục 2).
 *
 * Tra SĐT: đã ở nhóm / đã có tài khoản WePo / chưa có. Thêm: lấy dòng danh bạ
 * cùng SĐT (chưa có thì tạo) rồi cấp tài khoản vai trò Thành viên qua
 * AccountGranter, nên SĐT đã có tài khoản thì giữ nguyên mật khẩu, chưa có
 * thì ra mật khẩu tạm.
 */
class PhoneMemberAdder
{
    public function __construct(
        private readonly AccountGranter $granter,
        private readonly JoinRequests $joins,
    ) {}

    /** @return array{status: string, phone: string, name: ?string, avatar_url: ?string, contact: ?array} */
    public function lookup(Group $group, string $phone): array
    {
        $phone = $this->normalize($phone);
        $user = User::wherePhone($phone)->first();
        $contact = $this->contactOf($group, $phone);

        return [
            'status' => match (true) {
                $user !== null && $this->joins->isMember($group, $user) => 'member',
                $user !== null => 'account',
                default => 'none',
            },
            'phone' => $phone,
            'name' => $user?->name,
            'avatar_url' => $user ? MediaUrl::profile($user, 'avatar') : null,
            'contact' => $contact ? ['id' => $contact->id, 'full_name' => $contact->full_name, 'status' => $contact->status] : null,
        ];
    }

    /** @return array{member: GroupMember, temp_password: ?string} */
    public function add(Group $group, string $phone, ?string $fullName, User $admin): array
    {
        $phone = $this->normalize($phone);

        return DB::connection('groups')->transaction(function () use ($group, $phone, $fullName, $admin) {
            $user = User::wherePhone($phone)->first();

            if ($user !== null && $this->joins->isMember($group, $user)) {
                throw ValidationException::withMessages(['phone' => "{$user->name} đã ở trong nhóm."]);
            }

            // Chưa có tài khoản thì cần họ tên, trừ khi SĐT đã có trong danh bạ (dùng tên ở đó).
            $contact = $this->contactOf($group, $phone);
            $fullName = trim((string) $fullName);
            if ($user === null && $contact === null && $fullName === '') {
                throw ValidationException::withMessages(['full_name' => 'Nhập họ tên để cấp tài khoản mới.']);
            }

            if ($contact === null) {
                $contact = new Contact([
                    'full_name' => $user?->name ?? $fullName,
                    'phone' => $phone,
                    'sort_order' => (int) Contact::acrossGroups()->where('group_id', $group->id)->max('sort_order') + 1,
                ]);
                $contact->group_id = $group->id;
                $contact->save();
            } elseif ($contact->status !== 'active') {
                $contact->update(['status' => 'active']);
            }

            $result = $this->granter->grant($group, $contact, GroupRole::Member);
            $this->joins->approvePending($group, $result['member']->user, $contact, $admin);

            return $result;
        });
    }

    private function normalize(string $phone): string
    {
        return PhoneNumber::normalize($phone)
            ?? throw ValidationException::withMessages(['phone' => 'Số điện thoại không hợp lệ.']);
    }

    private function contactOf(Group $group, string $phone): ?Contact
    {
        return Contact::acrossGroups()->where('group_id', $group->id)->wherePhone($phone)->first();
    }
}
