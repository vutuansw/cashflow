<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Thả tim một phiếu hoặc một bài đăng trên timeline (đúng một trong hai cột). */
class Reaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['transaction_id', 'status_post_id', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
