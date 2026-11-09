<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nhật ký tạo/sửa/xóa phiếu: tiền bạc phải truy vết được ai sửa gì. */
class TransactionHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['transaction_id', 'user_id', 'action', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
