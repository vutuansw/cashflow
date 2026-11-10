<?php

namespace App\Http\Presenters;

use App\Models\Comment;
use App\Services\Groups\GroupViewer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Bình luận trả cho SPA, kèm quyền sửa/ẩn/xóa của người đang xem. Xem plan/03 mục 5. */
class CommentPresenter
{
    /** Số bình luận gần nhất hiện sẵn dưới mỗi phiếu; cũ hơn thì bấm "Xem thêm". */
    public const PREVIEW = 5;

    /** Mỗi lần bấm "Xem thêm" tải thêm chừng này bình luận cũ hơn. */
    public const PAGE = 10;

    /** Bình luận bị ẩn chỉ QTV và người viết thấy. */
    public static function visibleTo(Builder|Relation $query, GroupViewer $viewer): Builder|Relation
    {
        $userId = $viewer->user?->id;

        return $query->when(! $viewer->canManage(), fn ($q) => $q->where(
            fn ($w) => $w->whereNull('hidden_at')->when($userId, fn ($o) => $o->orWhere('user_id', $userId))
        ));
    }

    public static function present(Comment $c, GroupViewer $viewer): array
    {
        $mine = $viewer->user?->id === $c->user_id;

        return [
            'id' => $c->id,
            'body' => $c->body,
            'user' => $c->user?->name ?? 'Tài khoản đã xóa',
            'created_at' => $c->created_at->toIso8601String(),
            'edited' => $c->updated_at->gt($c->created_at->copy()->addSecond()),
            'hidden' => $c->hidden_at !== null,
            'mine' => $mine,
            'can_edit' => $mine,
            'can_delete' => $mine || $viewer->canManage(),
            'can_hide' => $viewer->canManage(),
        ];
    }
}
