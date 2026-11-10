<?php

namespace App\Services\Accounts;

use App\Enums\GroupRole;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Cấp tài khoản" cho một người trong danh bạ, xem plan/02 mục 4.
 *
 * - SĐT đã có tài khoản WePo: chỉ gắn tài khoản đó vào nhóm, không đụng
 *   mật khẩu của họ.
 * - Chưa có: tạo tài khoản với mật khẩu tạm, bắt đổi khi đăng nhập lần đầu.
 */
class AccountGranter
{
    /** @return array{member: GroupMember, temp_password: ?string} */
    public function grant(Group $group, Contact $contact, GroupRole $role): array
    {
        if ($role === GroupRole::Owner) {
            throw ValidationException::withMessages(['role' => 'Dùng chức năng chuyển trưởng nhóm để đổi trưởng nhóm.']);
        }

        if ($contact->phone === null) {
            throw ValidationException::withMessages(['contact_id' => 'Người này chưa có số điện thoại trong danh bạ. Thêm SĐT rồi cấp tài khoản.']);
        }

        return DB::connection('groups')->transaction(function () use ($group, $contact, $role) {
            $tempPassword = null;
            $user = User::wherePhone($contact->phone)->first();

            if ($user === null) {
                $tempPassword = TempPassword::make();
                $user = User::create([
                    'name' => $contact->full_name,
                    'phone' => $contact->phone,
                    'password' => $tempPassword,
                    'must_change_password' => true,
                ]);
            }

            $member = GroupMember::query()->firstOrNew(['group_id' => $group->id, 'user_id' => $user->id]);

            if ($member->exists && $member->status === 'active') {
                throw ValidationException::withMessages(['contact_id' => 'Người này đã có tài khoản trong nhóm.']);
            }

            $member->fill(['role' => $role, 'contact_id' => $contact->id, 'status' => 'active'])->save();
            $contact->forceFill(['user_id' => $user->id])->save();

            return ['member' => $member->load('user'), 'temp_password' => $tempPassword];
        });
    }

    public function resetPassword(User $user): string
    {
        $tempPassword = TempPassword::make();
        $user->forceFill(['password' => $tempPassword, 'must_change_password' => true])->save();

        return $tempPassword;
    }

    /** Lời nhắn để QTV dán gửi qua Zalo. */
    public function shareMessage(Group $group, User $user, ?string $tempPassword): string
    {
        $lines = [
            "WePo - {$group->name}",
            'Đăng nhập: '.rtrim((string) config('app.url'), '/').'/login',
            "Số điện thoại: {$user->phone}",
        ];

        $lines[] = $tempPassword !== null
            ? "Mật khẩu tạm: {$tempPassword}\nBạn sẽ được yêu cầu đổi mật khẩu khi đăng nhập lần đầu."
            : 'Dùng mật khẩu WePo bạn đang có.';

        return implode("\n", $lines);
    }
}
