<?php

namespace App\Http\Presenters;

use App\Models\BillScan;
use App\Models\Category;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\TransactionHistory;
use App\Models\TransactionPhoto;
use App\Services\Groups\GroupViewer;
use App\Services\Groups\MemberIndex;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;

/** Dữ liệu phiếu trả cho SPA: thẻ timeline, dòng danh sách quản trị, chi tiết. */
class TransactionPresenter
{
    /** Nạp sẵn những gì thẻ timeline cần (kể cả vài bình luận mới nhất), tránh truy vấn N+1. */
    public static function withCardRelations(Builder $query, GroupViewer $viewer): Builder
    {
        $userId = $viewer->user?->id;

        return $query
            ->with([
                'category', 'contact:id,group_id,full_name', 'photos', 'campaign:id,name,slug',
                // Lấy dư một bình luận để biết còn bình luận cũ hơn không.
                'comments' => fn ($q) => CommentPresenter::visibleTo($q, $viewer)
                    ->with('user:id,name')->orderByDesc('id')->limit(CommentPresenter::PREVIEW + 1),
            ])
            ->withCount([
                'reactions as hearts_count',
                'comments as comments_count' => fn ($q) => $q->whereNull('hidden_at'),
            ])
            ->when($userId, fn ($q) => $q->withExists(['reactions as hearted' => fn ($r) => $r->where('user_id', $userId)]));
    }

    public static function card(Transaction $t, Group $group, GroupViewer $viewer): array
    {
        $comments = $t->relationLoaded('comments') ? $t->comments : collect();

        return [
            'id' => $t->id,
            'type' => $t->type->value,
            'amount' => $t->amount,
            'title' => $t->title,
            'description' => $t->description,
            'occurred_at' => $t->occurred_at->toIso8601String(),
            'person' => $t->personName(),
            // Người đóng/người nhận đã có tài khoản trong nhóm thì tên bấm được, mở trang thành viên (plan/08 mục 6).
            'person_member_id' => MemberIndex::of($group)->contact($t->contact_id)['member_id'] ?? null,
            'category' => self::category($t->category),
            'campaign' => $t->campaign ? ['id' => $t->campaign->id, 'name' => $t->campaign->name, 'slug' => $t->campaign->slug] : null,
            'photos' => $t->photos->map(fn (TransactionPhoto $p) => self::photo($group, $p))->values(),
            'hearts_count' => (int) ($t->hearts_count ?? 0),
            'comments_count' => (int) ($t->comments_count ?? 0),
            'hearted' => (bool) ($t->hearted ?? false),
            // Vài bình luận mới nhất, xếp cũ trước mới sau như khung chat.
            'comments' => $comments->take(CommentPresenter::PREVIEW)->reverse()->values()->map(fn ($c) => CommentPresenter::present($c, $viewer)),
            'comments_more' => $comments->count() > CommentPresenter::PREVIEW,
        ];
    }

    /** Dòng trong danh sách phiếu của quản trị. */
    public static function row(Transaction $t): array
    {
        return [
            'id' => $t->id,
            'type' => $t->type->value,
            'amount' => $t->amount,
            'title' => $t->title,
            'occurred_at' => $t->occurred_at->toIso8601String(),
            'person' => $t->personName(),
            'category' => self::category($t->category),
            'campaign' => $t->campaign?->name,
            'source' => $t->source,
            'show_on_timeline' => $t->show_on_timeline,
            'photos_count' => (int) ($t->photos_count ?? 0),
            'bills_count' => (int) ($t->bill_scans_count ?? 0),
            'deleted_at' => $t->deleted_at?->toIso8601String(),
        ];
    }

    /** Chi tiết cho QTV: đủ trường để mở form sửa, kèm bill và lịch sử. */
    public static function detail(Transaction $t, Group $group): array
    {
        $t->loadMissing(['category', 'contact', 'campaign', 'photos', 'creator:id,name', 'histories.user:id,name']);
        $bills = BillScan::where('transaction_id', $t->id)->whereNotNull('path')->orderBy('id')->get();
        $categories = null;

        return self::row($t) + [
            'description' => $t->description,
            'category_id' => $t->category_id,
            'campaign_id' => $t->campaign_id,
            'contact_id' => $t->contact_id,
            'payer_name' => $t->payer_name,
            'created_by' => $t->creator?->name,
            'created_at' => $t->created_at->toIso8601String(),
            'photos' => $t->photos->map(fn (TransactionPhoto $p) => self::photo($group, $p))->values(),
            'bills' => $bills->map(fn (BillScan $b) => ['id' => $b->id, 'url' => MediaUrl::bill($group, $b), 'ai' => $b->model !== null])->values(),
            'histories' => $t->histories->map(function (TransactionHistory $h) use (&$categories) {
                return [
                    'id' => $h->id,
                    'action' => $h->action,
                    'user' => $h->user?->name,
                    'at' => $h->created_at?->toIso8601String(),
                    // Lúc tạo phiếu chỉ cần ghi "Tạo phiếu"; ảnh chụp toàn bộ trường vẫn lưu trong changes.
                    'changes' => $h->action === 'updated' ? self::describeChanges($h->changes, $categories) : [],
                ];
            })->values(),
            'restorable_until' => $t->deleted_at?->copy()->addDays(config('cashflow.restore_days'))->toIso8601String(),
        ];
    }

    public static function photo(Group $group, TransactionPhoto $p): array
    {
        return [
            'id' => $p->id,
            'sm' => MediaUrl::photo($group, $p, 'sm'),
            'lg' => MediaUrl::photo($group, $p, 'lg'),
            'width' => $p->width,
            'height' => $p->height,
        ];
    }

    public static function category(?Category $c): ?array
    {
        return $c === null ? null : ['id' => $c->id, 'name' => $c->name, 'icon' => $c->icon, 'color' => $c->color];
    }

    /**
     * Lịch sử sửa dạng đọc được: "Số tiền: 500.000 → 600.000".
     *
     * @return list<array{field: string, from: mixed, to: mixed}>
     */
    private static function describeChanges(?array $changes, ?array &$categories): array
    {
        if (empty($changes['after']) && empty($changes['media'])) {
            return [];
        }

        $labels = [
            'type' => 'Thu/chi', 'category_id' => 'Loại', 'campaign_id' => 'Chiến dịch', 'contact_id' => 'Người trong danh bạ',
            'amount' => 'Số tiền', 'occurred_at' => 'Ngày giờ', 'title' => 'Tiêu đề', 'description' => 'Mô tả',
            'payer_name' => 'Tên người', 'show_on_timeline' => 'Hiện trên timeline',
        ];
        $categories ??= Category::query()->pluck('name', 'id')->all();

        $format = function (string $field, mixed $value) use ($categories) {
            return match ($field) {
                'type' => $value === 'income' ? 'Thu' : ($value === 'expense' ? 'Chi' : $value),
                'category_id' => $categories[$value] ?? $value,
                'amount' => $value === null ? null : number_format((int) $value, 0, ',', '.').' đ',
                'show_on_timeline' => $value ? 'Có' : 'Không',
                default => $value,
            };
        };

        $rows = [];
        foreach (($changes['after'] ?? []) as $field => $to) {
            $rows[] = [
                'field' => $labels[$field] ?? $field,
                'from' => $format($field, $changes['before'][$field] ?? null),
                'to' => $format($field, $to),
            ];
        }

        $media = $changes['media'] ?? [];
        $mediaLabels = [
            'photos_added' => 'Thêm ảnh', 'photos_removed' => 'Bỏ ảnh', 'bills_added' => 'Thêm ảnh bill', 'bills_removed' => 'Bỏ ảnh bill',
        ];
        foreach ($mediaLabels as $key => $label) {
            if (! empty($media[$key])) {
                $rows[] = ['field' => $label, 'from' => null, 'to' => $media[$key]];
            }
        }

        return $rows;
    }
}
