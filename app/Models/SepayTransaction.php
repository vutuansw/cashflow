<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Support\BankReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Giao dịch SePay báo về, nằm trong hàng chờ cho tới khi QTV duyệt. Xem plan/05. */
class SepayTransaction extends Model
{
    use BelongsToGroup;

    protected $fillable = [
        'sepay_id', 'gateway', 'transaction_date', 'account_number', 'account_mismatch', 'transfer_type',
        'amount', 'content', 'reference_code', 'raw', 'matched_campaign_id', 'suggested_contact_id',
        'status', 'ignore_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (SepayTransaction $t) {
            if ($t->isDirty('reference_code')) {
                $t->reference_norm = BankReference::normalize($t->reference_code);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'account_mismatch' => 'boolean',
            'amount' => 'integer',
            'raw' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function matchedCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'matched_campaign_id');
    }

    public function suggestedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'suggested_contact_id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
