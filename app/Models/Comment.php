<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Bình luận dưới phiếu hoặc bài đăng (đúng một trong hai cột); QTV ẩn hoặc xóa được. */
class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = ['transaction_id', 'status_post_id', 'user_id', 'body', 'hidden_at', 'hidden_by'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
