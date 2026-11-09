<?php

namespace Tests\Concerns;

use App\Enums\GroupRole;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\Groups\GroupCreator;

trait CreatesGroups
{
    /** Nhóm lập đúng như khi trưởng nhóm đăng ký: có loại thu chi mặc định, owner trong danh bạ. */
    protected function makeGroup(array $attributes = [], ?User $owner = null): Group
    {
        $owner ??= User::factory()->create();

        return app(GroupCreator::class)->create($owner, $attributes + [
            'name' => 'Chi đoàn A12',
            'code' => 'nhom-'.fake()->unique()->numerify('####'),
        ]);
    }

    protected function ownerOf(Group $group): User
    {
        return $group->owner;
    }

    /** Thêm một tài khoản vào nhóm với vai trò cho trước, kèm dòng danh bạ. */
    protected function memberOf(Group $group, GroupRole $role = GroupRole::Member, array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);
        $contact = Contact::forceCreate(['group_id' => $group->id, 'full_name' => $user->name, 'phone' => $user->phone, 'user_id' => $user->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role, 'contact_id' => $contact->id]);

        return $user;
    }

    protected function adminUrl(Group $group, string $path = ''): string
    {
        return "/api/g/{$group->code}/admin".($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}
