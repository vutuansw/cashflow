<?php

namespace App\Models;

use App\Enums\GroupRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tài khoản thuộc nhóm với vai trò nào. Không dùng BelongsToGroup vì bảng này
 * được đọc theo user (danh sách nhóm của tôi) khi chưa vào nhóm nào.
 *
 * handle: mã ngẫu nhiên cố định trên link trang thành viên, không bao giờ đưa
 * id ra trang công khai. Xem plan/25.
 */
class GroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'role', 'contact_id', 'status'];

    protected $attributes = ['status' => 'active'];

    public const HANDLE_LENGTH = 10;

    public const HANDLE_PATTERN = '[a-z0-9]{10}';

    protected static function booted(): void
    {
        static::creating(function (self $member) {
            $member->handle ??= self::newHandle();
        });
    }

    /** 10 ký tự a-z0-9 lấy đều bằng random_int (36^10 khả năng), thử lại nếu trùng. */
    public static function newHandle(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        do {
            $handle = '';
            for ($i = 0; $i < self::HANDLE_LENGTH; $i++) {
                $handle .= $alphabet[random_int(0, 35)];
            }
        } while (static::query()->where('handle', $handle)->exists());

        return $handle;
    }

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
