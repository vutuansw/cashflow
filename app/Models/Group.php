<?php

namespace App\Models;

use App\Models\System\GroupService;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mỗi nhóm đăng ký là một dòng; id của dòng này là group_id ở mọi bảng khác.
 * Trạng thái dịch vụ (duyệt, khóa) không nằm ở đây mà ở cashflow_system,
 * xem System\GroupService và plan/01 mục 2.
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'avatar_path', 'cover_path', 'visibility', 'owner_id',
        'opening_balance', 'opening_balance_date', 'bank_code', 'bank_account_no', 'bank_account_name',
        'notify_emails', 'notify_settings',
    ];

    protected $hidden = ['bank_account_no', 'sepay_api_key'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'integer',
            'opening_balance_date' => 'date',
            'bank_account_no' => 'encrypted',
            'sepay_api_key' => 'encrypted',
            'sepay_last_hook_at' => 'datetime',
            'notify_emails' => 'array',
            'notify_settings' => 'array',
        ];
    }

    /** Tham số {group} trên route là mã nhóm (chidoan-a12), không phải id. */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function service(): ?GroupService
    {
        return GroupService::query()->where('group_id', $this->id)->first();
    }
}
