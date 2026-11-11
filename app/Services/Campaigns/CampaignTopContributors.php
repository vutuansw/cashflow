<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Transaction;

/**
 * Box "Top đóng góp" trên trang nhóm. Xem plan/22.
 *
 * Số tính top = tổng phiếu thu chưa xóa của chiến dịch theo người (giống
 * CampaignProgress), trừ phiếu QTV ẩn khỏi top (exclude_from_top, vd đóng bù
 * năm trước). Lên top khi số tính top lớn hơn:
 * - chiến dịch có mức: mức riêng của người đó, không có thì mức chung (người
 *   được miễn hoặc ngoài danh sách cũng so với mức chung);
 * - chiến dịch tùy tâm: trung bình của những người có số tính top > 0.
 */
class CampaignTopContributors
{
    /**
     * @return array{
     *     campaign: array{id: int, name: string, slug: string, amount_per_member: ?int, average: ?int},
     *     people: list<array{rank: int, contact_id: ?int, name: string, amount: int, transaction_ids: list<int>}>,
     *     hidden_count: int
     * }
     */
    public function of(Campaign $campaign): array
    {
        $payments = Transaction::acrossGroups()
            ->where('group_id', $campaign->group_id)
            ->where('campaign_id', $campaign->id)
            ->where('type', 'income')
            ->with('contact:id,group_id,full_name')
            ->orderBy('occurred_at')->orderBy('id')
            ->get(['id', 'group_id', 'contact_id', 'payer_name', 'amount', 'occurred_at', 'exclude_from_top']);

        $counted = $payments->where('exclude_from_top', false);
        $members = CampaignMember::query()->where('campaign_id', $campaign->id)->get(['contact_id', 'expected_amount', 'exempt'])->keyBy('contact_id');
        $fixed = $campaign->amount_per_member;

        $people = [];
        foreach ($counted->whereNotNull('contact_id')->groupBy('contact_id') as $contactId => $rows) {
            $member = $members->get($contactId);
            $people[] = [
                'contact_id' => (int) $contactId,
                'name' => $rows->first()->contact?->full_name ?? 'Không rõ tên',
                'amount' => (int) $rows->sum('amount'),
                'last_at' => $rows->last()->occurred_at,
                'threshold' => $member !== null && ! $member->exempt ? ($member->expected_amount ?? $fixed) : $fixed,
                'transaction_ids' => $rows->pluck('id')->values()->all(),
            ];
        }
        // Phiếu chỉ có tên tự do: mỗi phiếu là một người, như danh sách Đã đóng.
        foreach ($counted->whereNull('contact_id') as $row) {
            $people[] = [
                'contact_id' => null,
                'name' => $row->payer_name ?? 'Không rõ tên',
                'amount' => $row->amount,
                'last_at' => $row->occurred_at,
                'threshold' => $fixed,
                'transaction_ids' => [$row->id],
            ];
        }

        $average = null;
        if ($fixed === null) {
            $paying = array_filter($people, fn ($p) => $p['amount'] > 0);
            $average = $paying === [] ? 0 : array_sum(array_column($paying, 'amount')) / count($paying);
            foreach ($people as &$p) {
                $p['threshold'] = $average;
            }
            unset($p);
        }

        $top = array_values(array_filter($people, fn ($p) => $p['threshold'] !== null && $p['amount'] > $p['threshold']));
        // Nhiều tiền đứng trước; bằng tiền thì ai đóng xong sớm hơn đứng trước.
        usort($top, fn ($a, $b) => [$b['amount'], $a['last_at']] <=> [$a['amount'], $b['last_at']]);

        return [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'amount_per_member' => $fixed,
                'average' => $average === null ? null : (int) round($average),
            ],
            'people' => array_map(fn ($p, $i) => [
                'rank' => $i + 1,
                'contact_id' => $p['contact_id'],
                'name' => $p['name'],
                'amount' => $p['amount'],
                'transaction_ids' => $p['transaction_ids'],
            ], $top, array_keys($top)),
            'hidden_count' => $payments->where('exclude_from_top', true)->count(),
        ];
    }
}
