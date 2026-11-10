<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Models\Concerns\HasEncryptedPhone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Tài khoản đăng nhập, dùng chung cho nhiều nhóm; vai trò tính theo từng nhóm
 * qua group_members. Đăng nhập bằng SĐT + mật khẩu. Xem plan/02.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasEncryptedPhone, HasFactory, Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'password', 'must_change_password', 'avatar_path', 'cover_path', 'bio', 'status'];

    /** Giá trị mặc định giống migration, để model vừa tạo đọc được ngay (strict mode). */
    protected $attributes = ['must_change_password' => false, 'status' => 'active', 'avatar_path' => null, 'cover_path' => null, 'bio' => null];

    protected $hidden = ['password', 'remember_token', 'phone', 'phone_hash'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot(['role', 'status', 'contact_id'])
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    public function roleIn(Group $group): ?GroupRole
    {
        $member = $this->memberships()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->first();

        return $member?->role;
    }
}
