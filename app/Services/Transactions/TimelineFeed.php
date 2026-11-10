<?php

namespace App\Services\Transactions;

use App\Http\Presenters\TransactionPresenter;
use App\Models\Campaign;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Groups\GroupViewer;
use App\Services\Groups\MemberIndex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bài trên timeline nhóm, mới nhất trước, 20 bài một trang. Xem plan/03 mục 4.
 *
 * Phiếu thu lẻ của cùng một chiến dịch trong cùng một ngày được gộp thành một
 * bài "8 người đóng hôm nay" để timeline không bị ngập. Phân trang theo bài
 * (sau khi gộp), không theo phiếu.
 */
class TimelineFeed
{
    public const PER_PAGE = 20;

    /**
     * @param  array{type?: ?string, category_id?: ?int, year?: ?int}  $filters
     * @return array{data: list<array>, has_more: bool, page: int}
     */
    public function page(Group $group, array $filters, int $page, GroupViewer $viewer): array
    {
        $page = max(1, $page);

        $base = Transaction::query()
            ->where('show_on_timeline', true)
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereYear('occurred_at', $v));

        // Khóa bài: "t{id}" cho phiếu thường, "c{campaign}:{ngày}" cho nhóm phiếu chiến dịch.
        $keyExpr = "CASE WHEN campaign_id IS NOT NULL AND type = 'income' THEN CONCAT('c', campaign_id, ':', DATE(occurred_at)) ELSE CONCAT('t', id) END";

        $posts = (clone $base)
            ->selectRaw("{$keyExpr} as post_key, MAX(occurred_at) as at, MAX(id) as last_id, COUNT(*) as n")
            ->groupBy(DB::raw('post_key'))
            ->orderByDesc('at')->orderByDesc('last_id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->toBase()
            ->get();

        $hasMore = $posts->count() > self::PER_PAGE;
        $posts = $posts->take(self::PER_PAGE);

        // Nhóm chiến dịch chỉ có một phiếu thì hiện như phiếu thường.
        $single = [];
        $groupsKeys = [];
        foreach ($posts as $post) {
            if (str_starts_with($post->post_key, 't') || (int) $post->n === 1) {
                $single[] = $post;
            } else {
                $groupsKeys[] = $post->post_key;
            }
        }

        $cardIds = [];
        foreach ($single as $post) {
            $cardIds[] = (int) $post->last_id;
        }

        $cards = TransactionPresenter::withCardRelations(Transaction::query(), $viewer)
            ->whereIn('id', $cardIds)
            ->get()
            ->keyBy('id');

        $grouped = $this->campaignDays($group, $base, $groupsKeys);

        $data = [];
        foreach ($posts as $post) {
            if (isset($grouped[$post->post_key])) {
                $data[] = $grouped[$post->post_key];
            } elseif ($card = $cards->get((int) $post->last_id)) {
                $data[] = ['kind' => 'transaction'] + TransactionPresenter::card($card, $group, $viewer);
            }
        }

        return ['data' => $data, 'has_more' => $hasMore, 'page' => $page];
    }

    /** @param  list<string>  $keys  dạng "c{campaign_id}:{Y-m-d}" */
    private function campaignDays(Group $group, $base, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $pairs = array_map(function (string $key) {
            [$campaign, $date] = explode(':', substr($key, 1), 2);

            return [(int) $campaign, $date];
        }, $keys);

        $transactions = (clone $base)
            ->with(['contact:id,group_id,full_name', 'category'])
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$campaign, $date]) {
                    $q->orWhere(fn ($w) => $w->where('campaign_id', $campaign)->whereDate('occurred_at', $date));
                }
            })
            ->where('type', 'income')
            ->orderByDesc('occurred_at')
            ->get();

        $campaigns = Campaign::query()->whereIn('id', array_column($pairs, 0))->get()->keyBy('id');
        $members = MemberIndex::of($group);

        $result = [];
        foreach ($transactions->groupBy(fn (Transaction $t) => 'c'.$t->campaign_id.':'.$t->occurred_at->toDateString()) as $key => $items) {
            /** @var Transaction $first */
            $first = $items->first();
            $campaign = $campaigns->get($first->campaign_id);

            $result[$key] = [
                'kind' => 'campaign_day',
                'key' => $key,
                'date' => $first->occurred_at->toDateString(),
                'occurred_at' => $first->occurred_at->toIso8601String(),
                'campaign' => $campaign ? ['id' => $campaign->id, 'name' => $campaign->name, 'slug' => $campaign->slug] : null,
                'category' => TransactionPresenter::category($first->category),
                'count' => $items->count(),
                'total' => (int) $items->sum('amount'),
                'payers' => $items->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'name' => $t->personName() ?? 'Không rõ tên',
                    'member_id' => $members->contact($t->contact_id)['member_id'] ?? null,
                    'amount' => $t->amount,
                    'time' => Carbon::parse($t->occurred_at)->format('H:i'),
                ])->values(),
            ];
        }

        return $result;
    }
}
