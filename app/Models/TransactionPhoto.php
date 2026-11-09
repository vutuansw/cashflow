<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ảnh sự kiện hiện trên timeline. */
class TransactionPhoto extends Model
{
    protected $fillable = ['transaction_id', 'path', 'thumb_path', 'width', 'height', 'sort_order'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
