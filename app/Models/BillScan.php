<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Support\BankReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ảnh bill chứng từ (chỉ QTV xem) kiêm nhật ký một lượt AI đọc bill. Xem plan/04. */
class BillScan extends Model
{
    use BelongsToGroup;

    protected $fillable = [
        'user_id', 'transaction_id', 'path', 'status', 'model', 'fallback_used', 'result', 'draft',
        'prompt_tokens', 'completion_tokens', 'cost_usd', 'error',
    ];

    protected static function booted(): void
    {
        // Mã giao dịch AI đọc được, chuẩn hóa để so trùng với SePay và bill khác (plan/05 mục 5).
        static::saving(function (BillScan $scan) {
            if ($scan->isDirty('result')) {
                $scan->reference_norm = BankReference::normalize($scan->result['reference_code'] ?? null);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'fallback_used' => 'boolean',
            'result' => 'array',
            'draft' => 'array',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
