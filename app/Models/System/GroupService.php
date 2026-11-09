<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

/**
 * Trạng thái dịch vụ của một nhóm, nguồn sự thật cho duyệt/khóa và hạn mức AI.
 * group_id trỏ sang cashflow_groups.groups.id, không có khóa ngoại.
 */
class GroupService extends Model
{
    protected $connection = 'system';

    protected $fillable = [
        'group_id', 'review_status', 'service_status', 'lock_reason', 'ai_monthly_quota',
        'max_photos_mb', 'reviewed_by', 'reviewed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'ai_monthly_quota' => 'integer',
            'max_photos_mb' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->service_status === 'locked';
    }
}
