<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Services\Groups\GroupViewer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bài đăng của thành viên: chữ và/hoặc ảnh, không có số tiền. Một bài thuộc
 * một nhóm, hiện ở timeline nhóm và trang thành viên của người đăng. Xem plan/21.
 *
 * status: published (đang hiện), pending (chờ QTV duyệt), rejected (không được duyệt).
 */
class StatusPost extends Model
{
    use BelongsToGroup, SoftDeletes;

    public const MAX_BODY = 5000;

    protected $fillable = [
        'user_id', 'body', 'status', 'published_at', 'reviewed_by', 'reviewed_at', 'reject_reason', 'hidden_at', 'hidden_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * Bài người xem được thấy: bài đang hiện và chưa ẩn; người đăng thấy mọi bài của mình;
     * QTV thấy mọi bài (để duyệt, ẩn, xóa).
     */
    public static function visibleTo(Builder $query, GroupViewer $viewer): Builder
    {
        if ($viewer->canManage()) {
            return $query;
        }

        $userId = $viewer->user?->id;

        return $query->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('status', 'published')->whereNull('hidden_at'))
            ->when($userId, fn ($o) => $o->orWhere('user_id', $userId)));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(StatusPostPhoto::class)->orderBy('sort_order')->orderBy('id');
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
