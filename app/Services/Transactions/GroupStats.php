<?php

namespace App\Services\Transactions;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Group;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu tổng hợp cho thẻ tóm tắt trên timeline và trang Tổng quan.
 *
 * Số dư không lưu cứng: số dư đầu kỳ + tổng thu − tổng chi của phiếu chưa xóa
 * (plan/plan.md nguyên tắc 3). Cache 5 phút theo nhóm; khi phiếu thay đổi chỉ
 * cần tăng "phiên bản" của nhóm là mọi khóa cache cũ hết hiệu lực.
 */
class GroupStats
{
    public function forget(Group|int $group): void
    {
        $id = $group instanceof Group ? $group->id : $group;
        Cache::forever($this->versionKey($id), $this->version($id) + 1);
    }

    public function balance(Group $group): int
    {
        return $this->remember($group, 'balance', function () use ($group) {
            $totals = $this->totals($group);

            return $group->opening_balance + $totals['income'] - $totals['expense'];
        });
    }

    /**
     * @return array{balance: int, opening_balance: int, year: int, years: list<int>, income: int, expense: int,
     *     months: list<array{month: int, income: int, expense: int}>, expense_by_category: list<array>, income_by_category: list<array>}
     */
    public function summary(Group $group, ?int $year = null): array
    {
        $years = $this->years($group);
        $year ??= (int) now()->year;

        return $this->remember($group, "summary:{$year}", function () use ($group, $year, $years) {
            $rows = $this->base($group)
                ->whereYear('occurred_at', $year)
                ->selectRaw('MONTH(occurred_at) as m, type, SUM(amount) as total')
                ->groupBy('m', 'type')
                ->toBase()
                ->get();

            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $months[$m] = ['month' => $m, 'income' => 0, 'expense' => 0];
            }
            foreach ($rows as $row) {
                $months[(int) $row->m][$row->type] = (int) $row->total;
            }

            $income = array_sum(array_column($months, 'income'));
            $expense = array_sum(array_column($months, 'expense'));

            return [
                'balance' => $this->balance($group),
                'opening_balance' => $group->opening_balance,
                'year' => $year,
                'years' => $years,
                'income' => $income,
                'expense' => $expense,
                'months' => array_values($months),
                'expense_by_category' => $this->byCategory($group, $year, 'expense', $expense),
                'income_by_category' => $this->byCategory($group, $year, 'income', $income),
            ];
        });
    }

    /**
     * Tiến độ các chiến dịch đang mở: đã thu bao nhiêu, bao nhiêu người đã đóng
     * đủ trên tổng số phải đóng. Chiến dịch làm đầy đủ ở M4 (plan/05).
     */
    public function openCampaigns(Group $group): array
    {
        return $this->remember($group, 'campaigns', function () use ($group) {
            $campaigns = Campaign::acrossGroups()
                ->where('group_id', $group->id)
                ->where('status', 'open')
                ->orderByDesc('id')
                ->get();

            return $campaigns->map(function (Campaign $c) {
                $collected = (int) $this->base($c->group_id)->where('campaign_id', $c->id)->sum('amount');

                $members = DB::connection('groups')->table('campaign_members as cm')
                    ->where('cm.campaign_id', $c->id)
                    ->where('cm.exempt', false)
                    ->leftJoinSub(
                        $this->base($c->group_id)->where('campaign_id', $c->id)
                            ->selectRaw('contact_id, SUM(amount) as paid')->groupBy('contact_id')->toBase(),
                        'p', 'p.contact_id', '=', 'cm.contact_id',
                    )
                    ->selectRaw('COUNT(*) as total, SUM(CASE WHEN COALESCE(p.paid, 0) >= COALESCE(cm.expected_amount, ?, 1) THEN 1 ELSE 0 END) as paid', [$c->amount_per_member])
                    ->first();

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'transfer_code' => $c->transfer_code,
                    'amount_per_member' => $c->amount_per_member,
                    'target_amount' => $c->target_amount,
                    'ends_on' => $c->ends_on?->toDateString(),
                    'collected' => $collected,
                    'members_total' => (int) ($members->total ?? 0),
                    'members_paid' => (int) ($members->paid ?? 0),
                ];
            })->all();
        });
    }

    /** @return list<int> các năm có phiếu, mới nhất trước, luôn có năm nay. */
    private function years(Group $group): array
    {
        return $this->remember($group, 'years', function () use ($group) {
            $years = $this->base($group)
                ->selectRaw('DISTINCT YEAR(occurred_at) as y')
                ->toBase()
                ->pluck('y')
                ->map(fn ($y) => (int) $y)
                ->push((int) now()->year)
                ->unique()
                ->sortDesc()
                ->values()
                ->all();

            return $years;
        });
    }

    /** @return array{income: int, expense: int} */
    private function totals(Group $group): array
    {
        $rows = $this->base($group)->selectRaw('type, SUM(amount) as total')->groupBy('type')->toBase()->pluck('total', 'type');

        return ['income' => (int) ($rows['income'] ?? 0), 'expense' => (int) ($rows['expense'] ?? 0)];
    }

    private function byCategory(Group $group, int $year, string $type, int $sum): array
    {
        $rows = $this->base($group)
            ->whereYear('occurred_at', $year)
            ->where('type', $type)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->toBase()
            ->pluck('total', 'category_id');

        $categories = Category::acrossGroups()->whereIn('id', $rows->keys())->get()->keyBy('id');

        return $rows->map(fn ($total, $id) => [
            'category_id' => (int) $id,
            'name' => $categories[$id]->name ?? 'Khác',
            'icon' => $categories[$id]->icon ?? null,
            'color' => $categories[$id]->color ?? null,
            'total' => (int) $total,
            'percent' => $sum > 0 ? round($total * 100 / $sum, 1) : 0,
        ])->values()->all();
    }

    /** Phiếu chưa xóa của nhóm, không phụ thuộc nhóm hiện tại của request. */
    private function base(Group|int $group)
    {
        return Transaction::acrossGroups()->where('group_id', $group instanceof Group ? $group->id : $group);
    }

    private function remember(Group $group, string $key, \Closure $callback): mixed
    {
        $version = $this->version($group->id);

        return Cache::remember("stats:{$group->id}:v{$version}:{$key}", config('cashflow.stats_cache_seconds'), $callback);
    }

    private function version(int $groupId): int
    {
        return (int) Cache::get($this->versionKey($groupId), 0);
    }

    private function versionKey(int $groupId): string
    {
        return "stats:{$groupId}:version";
    }
}
