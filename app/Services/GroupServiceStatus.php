<?php

namespace App\Services;

use App\Models\Group;
use App\Models\System\GroupService;
use Illuminate\Support\Facades\Cache;

/**
 * Đọc trạng thái dịch vụ của nhóm từ cashflow_system, có cache ngắn vì mọi
 * request vào trang nhóm và mọi webhook đều hỏi. /system đổi trạng thái thì
 * gọi forget(). Xem plan/01 mục 2.
 */
class GroupServiceStatus
{
    public function isLocked(Group $group): bool
    {
        return $this->status($group)['service_status'] === 'locked';
    }

    public function lockReason(Group $group): ?string
    {
        return $this->status($group)['lock_reason'];
    }

    /** @return array{service_status: string, lock_reason: ?string} */
    public function status(Group $group): array
    {
        return Cache::remember(
            $this->key($group->id),
            (int) config('cashflow.service_status_cache_seconds', 60),
            function () use ($group) {
                $service = GroupService::query()->where('group_id', $group->id)->first();

                // Chưa có dòng dịch vụ (nhóm tạo trước khi có bảng, hoặc tạo dở) thì coi là đang hoạt động.
                return [
                    'service_status' => $service?->service_status ?? 'active',
                    'lock_reason' => $service?->lock_reason,
                ];
            },
        );
    }

    public function forget(int $groupId): void
    {
        Cache::forget($this->key($groupId));
    }

    private function key(int $groupId): string
    {
        return "group-service-status:{$groupId}";
    }
}
