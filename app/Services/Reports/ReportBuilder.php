<?php

namespace App\Services\Reports;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Campaigns\CampaignProgress;
use Illuminate\Support\Carbon;

/**
 * Số liệu bốn báo cáo trong quản trị nhóm: sổ quỹ, tổng hợp theo loại, theo
 * tháng, chiến dịch. Dùng chung cho màn hình và file Excel. Xem plan/03 mục 7.
 *
 * Mọi khoảng thời gian tính theo occurred_at, đến hết ngày "to". Chỉ tính
 * phiếu chưa xóa (cả phiếu ẩn khỏi timeline vì vẫn là tiền thật).
 */
class ReportBuilder
{
    public function __construct(private readonly CampaignProgress $progress) {}

    /**
     * Sổ quỹ: số dư đầu kỳ, từng phiếu theo ngày với tồn lũy kế, số dư cuối kỳ.
     * Số dư đầu kỳ = số dư đầu kỳ của nhóm + thu − chi của các phiếu trước "from".
     */
    public function ledger(Group $group, Carbon $from, Carbon $to): array
    {
        $before = Transaction::query()->where('occurred_at', '<', $from->copy()->startOfDay())
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')->toBase()->pluck('total', 'type');
        $opening = $group->opening_balance + (int) ($before['income'] ?? 0) - (int) ($before['expense'] ?? 0);

        $balance = $opening;
        $rows = $this->range($from, $to)
            ->with(['category:id,group_id,name', 'contact:id,group_id,full_name', 'campaign:id,group_id,name'])
            ->orderBy('occurred_at')->orderBy('id')
            ->get()
            ->map(function (Transaction $t) use (&$balance) {
                $income = $t->type->value === 'income' ? $t->amount : 0;
                $expense = $t->type->value === 'expense' ? $t->amount : 0;
                $balance += $income - $expense;

                return [
                    'id' => $t->id,
                    'date' => $t->occurred_at->format('Y-m-d H:i'),
                    'title' => $t->title,
                    'category' => $t->category?->name,
                    'person' => $t->personName(),
                    'campaign' => $t->campaign?->name,
                    'income' => $income,
                    'expense' => $expense,
                    'balance' => $balance,
                ];
            })->all();

        return [
            'opening' => $opening,
            'income' => array_sum(array_column($rows, 'income')),
            'expense' => array_sum(array_column($rows, 'expense')),
            'closing' => $balance,
            'rows' => $rows,
        ];
    }

    /** Tổng thu, tổng chi từng loại và tỷ lệ trong khoảng thời gian. */
    public function categories(Carbon $from, Carbon $to): array
    {
        $totals = $this->range($from, $to)
            ->selectRaw('type, category_id, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('type', 'category_id')->toBase()->get();
        $names = Category::query()->whereIn('id', $totals->pluck('category_id'))->pluck('name', 'id');

        $result = [];
        foreach (['income', 'expense'] as $type) {
            $items = $totals->where('type', $type)->sortByDesc('total');
            $sum = (int) $items->sum('total');
            $result[$type] = [
                'total' => $sum,
                'rows' => $items->map(fn ($r) => [
                    'category' => $names[$r->category_id] ?? 'Khác',
                    'count' => (int) $r->n,
                    'total' => (int) $r->total,
                    'percent' => $sum > 0 ? round($r->total * 100 / $sum, 1) : 0,
                ])->values()->all(),
            ];
        }

        return $result;
    }

    /** Thu, chi, chênh lệch từng tháng trong khoảng thời gian (tháng không có phiếu vẫn hiện). */
    public function months(Carbon $from, Carbon $to): array
    {
        $data = $this->range($from, $to)
            ->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') as ym, type, SUM(amount) as total")
            ->groupBy('ym', 'type')->toBase()->get();

        $rows = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $key = $m->format('Y-m');
            $income = (int) ($data->where('ym', $key)->firstWhere('type', 'income')->total ?? 0);
            $expense = (int) ($data->where('ym', $key)->firstWhere('type', 'expense')->total ?? 0);
            $rows[] = ['month' => $key, 'label' => 'Tháng '.$m->format('m/Y'), 'income' => $income, 'expense' => $expense, 'diff' => $income - $expense];
        }

        return [
            'income' => array_sum(array_column($rows, 'income')),
            'expense' => array_sum(array_column($rows, 'expense')),
            'rows' => $rows,
        ];
    }

    /** Từng người: phải đóng, đã đóng, còn thiếu, ngày đóng gần nhất. */
    public function campaign(Campaign $campaign): array
    {
        $p = $this->progress->of($campaign);
        $label = ['paid' => 'Đã đủ', 'partial' => 'Đóng thiếu', 'unpaid' => 'Chưa đóng', 'exempt' => 'Miễn'];

        return [
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name, 'transfer_code' => $campaign->transfer_code, 'amount_per_member' => $campaign->amount_per_member],
            'collected' => $p['collected'],
            'expected_total' => $p['expected_total'],
            'members_total' => $p['members_total'],
            'members_paid' => $p['members_paid'],
            'rows' => array_map(fn ($person) => [
                'name' => $person['name'],
                'expected' => $person['status'] === 'exempt' ? 0 : $person['expected'],
                'paid' => $person['paid'],
                'missing' => $person['status'] === 'exempt' || $person['expected'] === null ? 0 : max(0, $person['expected'] - $person['paid']),
                'last_paid_at' => $person['last_paid_at'] ? Carbon::parse($person['last_paid_at'])->format('Y-m-d') : null,
                'status' => $label[$person['status']] ?? $person['status'],
                'in_list' => $person['in_list'],
            ], $p['people']),
        ];
    }

    private function range(Carbon $from, Carbon $to)
    {
        return Transaction::query()
            ->where('occurred_at', '>=', $from->copy()->startOfDay())
            ->where('occurred_at', '<=', $to->copy()->endOfDay());
    }
}
