<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yêu cầu vào nhóm qua link /{ma-nhom}/register, chờ QTV duyệt (plan/13).
 * Không dùng BelongsToGroup vì được đọc theo user (nhóm tôi đang chờ duyệt).
 */
class GroupJoinRequest extends Model
{
    protected $fillable = ['group_id', 'user_id', 'contact_id', 'status', 'decided_by', 'decided_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
