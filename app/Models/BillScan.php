<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
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
