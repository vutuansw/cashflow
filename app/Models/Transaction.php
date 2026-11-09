<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phiếu thu chi; mỗi phiếu là một bài trên timeline. Tiền là số nguyên VND,
 * thu và chi đều dương, phân biệt bằng type. Xem plan/03 mục 2.
 */
class Transaction extends Model
{
    use BelongsToGroup, SoftDeletes;

    protected $fillable = [
        'type', 'category_id', 'campaign_id', 'contact_id', 'amount', 'occurred_at', 'title',
        'description', 'payer_name', 'source', 'sepay_transaction_id', 'show_on_timeline',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $attributes = ['source' => 'manual', 'show_on_timeline' => true];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
            'occurred_at' => 'datetime',
            'show_on_timeline' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function billScans(): HasMany
    {
        return $this->hasMany(BillScan::class);
    }

    /** Tên người đóng/nhận để hiển thị: người trong danh bạ, hoặc tên gõ tự do. */
    public function personName(): ?string
    {
        return $this->contact?->full_name ?? $this->payer_name;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sepayTransaction(): BelongsTo
    {
        return $this->belongsTo(SepayTransaction::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TransactionPhoto::class)->orderBy('sort_order');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TransactionHistory::class)->latest('id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
