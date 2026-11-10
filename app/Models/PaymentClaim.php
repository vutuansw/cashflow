<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Thành viên tự báo đã chuyển khoản bằng ảnh bill, nằm trong hàng chờ cho tới
 * khi QTV duyệt (ra phiếu thu) hoặc bỏ qua. Xem plan/05 mục 5.
 */
class PaymentClaim extends Model
{
    use BelongsToGroup;

    protected $fillable = ['campaign_id', 'contact_id', 'payer_name', 'amount', 'bill_scan_id', 'note'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(BillScan::class, 'bill_scan_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function personName(): string
    {
        return $this->contact?->full_name ?? $this->payer_name;
    }
}
