<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\HasEncryptedPhone;
use App\Services\Transactions\GroupStats;
use App\Support\VietnameseText;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Danh bạ thành viên của nhóm, tách khỏi tài khoản đăng nhập. SĐT và địa chỉ
 * mã hóa. Xem plan/02 mục 4.
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToGroup, HasEncryptedPhone, HasFactory;

    protected $fillable = ['full_name', 'phone', 'birthday', 'address', 'note', 'user_id', 'status', 'sort_order'];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    protected $hidden = ['phone', 'phone_hash', 'address'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'address' => 'encrypted',
            'birthday' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contact $contact) {
            if ($contact->isDirty('full_name')) {
                $contact->search_name = VietnameseText::fold($contact->full_name);
            }
        });

        // Người mới vào danh bạ tự vào danh sách phải đóng của các chiến dịch đang mở (plan/05 mục 1).
        static::created(function (Contact $contact) {
            if ($contact->status !== 'active') {
                return;
            }
            $open = Campaign::acrossGroups()->where('group_id', $contact->group_id)->where('status', 'open')->pluck('id');
            $open->each(fn ($campaignId) => CampaignMember::firstOrCreate(['campaign_id' => $campaignId, 'contact_id' => $contact->id]));
            if ($open->isNotEmpty()) {
                app(GroupStats::class)->forget($contact->group_id);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
