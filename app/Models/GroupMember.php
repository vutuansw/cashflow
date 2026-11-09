<?php

namespace App\Models;

use App\Enums\GroupRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tài khoản thuộc nhóm với vai trò nào. Không dùng BelongsToGroup vì bảng này
 * được đọc theo user (danh sách nhóm của tôi) khi chưa vào nhóm nào.
 */
class GroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'role', 'contact_id', 'status'];

    protected function casts(): array
    {
        return ['role' => GroupRole::class];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
