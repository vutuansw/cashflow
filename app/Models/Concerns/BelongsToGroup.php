<?php

namespace App\Models\Concerns;

use App\Models\Group;
use App\Support\CurrentGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model có cột group_id. Nguyên tắc bất biến số 1 của plan: mọi truy vấn dữ
 * liệu nhóm đều lọc theo group_id.
 *
 * - Có nhóm hiện tại (middleware ResolveGroup đã chạy): tự lọc theo nhóm đó và
 *   tự điền group_id khi tạo mới.
 * - Chưa có nhóm hiện tại: mặc định không trả dòng nào. Lệnh console và trang
 *   /system muốn đọc chéo nhóm phải gọi tường minh Model::acrossGroups().
 */
trait BelongsToGroup
{
    public static function bootBelongsToGroup(): void
    {
        static::addGlobalScope('group', function (Builder $builder) {
            $groupId = app(CurrentGroup::class)->id();
            $column = $builder->getModel()->qualifyColumn('group_id');

            $groupId === null ? $builder->whereRaw('1 = 0') : $builder->where($column, $groupId);
        });

        static::creating(function ($model) {
            if (empty($model->group_id)) {
                $model->group_id = app(CurrentGroup::class)->require()->id;
            }
        });
    }

    /** Bỏ lọc theo nhóm. Chỉ dùng ở console, /system và job đã tự kiểm soát group_id. */
    public static function acrossGroups(): Builder
    {
        return static::withoutGlobalScope('group');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
