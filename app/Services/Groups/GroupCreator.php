<?php

namespace App\Services\Groups;

use App\Enums\GroupRole;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\System\GroupService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lập nhóm mới: dòng groups, loại thu chi mặc định, trưởng nhóm vào danh bạ và
 * group_members với vai trò owner, rồi dòng group_services (chờ duyệt nhưng
 * hoạt động ngay) ở cashflow_system. Xem plan/02 mục 2.
 */
class GroupCreator
{
    /**
     * @param  array{name: string, code: string, visibility?: string, opening_balance?: int|null, description?: string|null}  $data
     */
    public function create(User $owner, array $data): Group
    {
        $group = DB::connection('groups')->transaction(function () use ($owner, $data) {
            $group = Group::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'owner_id' => $owner->id,
                'opening_balance' => (int) ($data['opening_balance'] ?? 0),
                'opening_balance_date' => now()->toDateString(),
            ]);

            $this->seedCategories($group);

            $contact = Contact::forceCreate([
                'group_id' => $group->id,
                'full_name' => $owner->name,
                'phone' => $owner->phone,
                'user_id' => $owner->id,
            ]);

            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $owner->id,
                'role' => GroupRole::Owner,
                'contact_id' => $contact->id,
            ]);

            return $group;
        });

        // Khác database nên không chung transaction. Thiếu dòng này thì nhóm vẫn được
        // coi là đang hoạt động (GroupServiceStatus), /system tạo bù khi mở chi tiết.
        try {
            GroupService::create(['group_id' => $group->id, 'review_status' => 'pending_review']);
        } catch (Throwable $e) {
            Log::error('Không tạo được group_services cho nhóm mới', ['group_id' => $group->id, 'error' => $e->getMessage()]);
        }

        return $group;
    }

    private function seedCategories(Group $group): void
    {
        foreach (config('cashflow.default_categories') as $type => $items) {
            foreach (array_values($items) as $i => $item) {
                Category::forceCreate([
                    'group_id' => $group->id,
                    'type' => $type,
                    'name' => $item['name'],
                    'icon' => $item['icon'],
                    'color' => $item['color'],
                    'is_system' => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
