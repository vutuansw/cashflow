<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ảnh của bài đăng thành viên. Xem plan/21 mục 4. */
class StatusPostPhoto extends Model
{
    protected $fillable = ['status_post_id', 'path', 'thumb_path', 'width', 'height', 'sort_order'];

    public function statusPost(): BelongsTo
    {
        return $this->belongsTo(StatusPost::class);
    }
}
