<?php

namespace App\Http\Presenters;

use App\Models\Group;
use App\Models\StatusPost;
use App\Models\StatusPostPhoto;
use App\Services\Groups\GroupViewer;
use App\Services\Groups\MemberIndex;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;

/** Thẻ bài đăng thành viên trên timeline (kind = status). Xem plan/21 mục 6. */
class StatusPostPresenter
{
    /** Nạp sẵn những gì thẻ cần, giống TransactionPresenter::withCardRelations. */
    public static function withCardRelations(Builder $query, GroupViewer $viewer): Builder
    {
        $userId = $viewer->user?->id;

        return $query
            ->with([
                'user:id,name', 'photos',
                'comments' => fn ($q) => CommentPresenter::visibleTo($q, $viewer)
                    ->with('user:id,name')->orderByDesc('id')->limit(CommentPresenter::PREVIEW + 1),
            ])
            ->withCount([
                'reactions as hearts_count',
                'comments as comments_count' => fn ($q) => $q->whereNull('hidden_at'),
            ])
            ->when($userId, fn ($q) => $q->withExists(['reactions as hearted' => fn ($r) => $r->where('user_id', $userId)]));
    }

    public static function card(StatusPost $p, Group $group, GroupViewer $viewer): array
    {
        $comments = $p->relationLoaded('comments') ? $p->comments : collect();
        $author = MemberIndex::of($group)->user($p->user_id);
        $mine = $viewer->user?->id === $p->user_id;

        return [
            'kind' => 'status',
            'id' => $p->id,
            'body' => $p->body,
            'status' => $p->status,
            'reject_reason' => $mine || $viewer->canManage() ? $p->reject_reason : null,
            'hidden' => $p->hidden_at !== null,
            // Bài chưa hiện (chờ duyệt, bị từ chối) thì lấy giờ gửi để xếp và hiện "2 phút trước".
            'occurred_at' => ($p->published_at ?? $p->created_at)->toIso8601String(),
            'edited' => $p->updated_at->gt($p->created_at->copy()->addSecond()),
            // member_handle null: người đăng đã rời nhóm, tên không bấm được.
            'author' => [
                'name' => $p->user?->name ?? 'Tài khoản đã xóa',
                'member_handle' => $author['member_handle'] ?? null,
                'avatar_url' => $author['avatar_url'] ?? null,
                'role_label' => $author['role_label'] ?? null,
            ],
            'photos' => $p->photos->map(fn (StatusPostPhoto $photo) => self::photo($group, $photo))->values(),
            'hearts_count' => (int) ($p->hearts_count ?? 0),
            'comments_count' => (int) ($p->comments_count ?? 0),
            'hearted' => (bool) ($p->hearted ?? false),
            'comments' => $comments->take(CommentPresenter::PREVIEW)->reverse()->values()->map(fn ($c) => CommentPresenter::present($c, $viewer)),
            'comments_more' => $comments->count() > CommentPresenter::PREVIEW,
            'mine' => $mine,
            'can_edit' => $mine && $p->status !== 'rejected',
            'can_delete' => $mine || $viewer->canManage(),
            'can_hide' => $viewer->canManage() && $p->isPublished(),
        ];
    }

    public static function photo(Group $group, StatusPostPhoto $p): array
    {
        return [
            'id' => $p->id,
            'sm' => MediaUrl::statusPhoto($group, $p, 'sm'),
            'lg' => MediaUrl::statusPhoto($group, $p, 'lg'),
            'width' => $p->width,
            'height' => $p->height,
        ];
    }
}
