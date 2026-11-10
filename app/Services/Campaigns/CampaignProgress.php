<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Transaction;

/**
 * Ai đã đóng, đóng thiếu, chưa đóng trong một chiến dịch. Xem plan/05 mục 1.
 *
 * Số đã đóng = tổng phiếu thu chưa xóa cùng chiến dịch + người (danh bạ). Chỉ
 * phiếu đã có mới tính: giao dịch SePay chưa duyệt chưa tính là đã đóng.
 * Phiếu của người ngoài danh sách phải đóng, hoặc chỉ có tên tự do, vẫn hiện
 * trong danh sách đã đóng.
 */
class CampaignProgress
{
    /**
     * @return array{
     *     collected: int, expected_total: int, members_total: int, members_paid: int, members_partial: int,
     *     people: list<array{contact_id: ?int, name: string, expected: ?int, paid: int, last_paid_at: ?string, status: string, in_list: bool}>
     * }
     */
    public function of(Campaign $campaign): array
    {
        $payments = Transaction::acrossGroups()
            ->where('group_id', $campaign->group_id)
            ->where('campaign_id', $campaign->id)
            ->where('type', 'income')
            ->with('contact:id,group_id,full_name')
            ->orderBy('occurred_at')
            ->get(['id', 'group_id', 'contact_id', 'payer_name', 'amount', 'occurred_at']);

        $byContact = $payments->whereNotNull('contact_id')->groupBy('contact_id');

        $members = CampaignMember::query()
            ->where('campaign_id', $campaign->id)
            ->with('contact:id,group_id,full_name,status,sort_order')
            ->get()
            ->sortBy(fn (CampaignMember $m) => [$m->contact?->sort_order ?? 0, $m->contact?->full_name ?? ''])
            ->values();

        $people = [];
        $listed = [];
        foreach ($members as $member) {
            $listed[$member->contact_id] = true;
            $paidRows = $byContact->get($member->contact_id, collect());
            $expected = $member->expected_amount ?? $campaign->amount_per_member;
            $paid = (int) $paidRows->sum('amount');

            $people[] = [
                'contact_id' => $member->contact_id,
                'name' => $member->contact?->full_name ?? 'Không rõ tên',
                'expected' => $expected,
                'paid' => $paid,
                'last_paid_at' => $paidRows->last()?->occurred_at?->toIso8601String(),
                'status' => $this->status($member->exempt, $paid, $expected),
                'in_list' => true,
            ];
        }

        // Người đóng không nằm trong danh sách phải đóng.
        foreach ($byContact as $contactId => $rows) {
            if (! isset($listed[$contactId])) {
                $people[] = [
                    'contact_id' => (int) $contactId,
                    'name' => $rows->first()->contact?->full_name ?? 'Không rõ tên',
                    'expected' => null,
                    'paid' => (int) $rows->sum('amount'),
                    'last_paid_at' => $rows->last()->occurred_at->toIso8601String(),
                    'status' => 'paid',
                    'in_list' => false,
                ];
            }
        }
        foreach ($payments->whereNull('contact_id') as $row) {
            $people[] = [
                'contact_id' => null,
                'name' => $row->payer_name ?? 'Không rõ tên',
                'expected' => null,
                'paid' => $row->amount,
                'last_paid_at' => $row->occurred_at->toIso8601String(),
                'status' => 'paid',
                'in_list' => false,
            ];
        }

        $inList = array_filter($people, fn ($p) => $p['in_list'] && $p['status'] !== 'exempt');

        return [
            'collected' => (int) $payments->sum('amount'),
            'expected_total' => (int) array_sum(array_map(fn ($p) => (int) ($p['expected'] ?? 0), $inList)),
            'members_total' => count($inList),
            'members_paid' => count(array_filter($inList, fn ($p) => $p['status'] === 'paid')),
            'members_partial' => count(array_filter($inList, fn ($p) => $p['status'] === 'partial')),
            'people' => $people,
        ];
    }

    /** paid: đủ; partial: đóng thiếu; unpaid: chưa đóng; exempt: được miễn. Tùy tâm thì đóng bao nhiêu cũng là đủ. */
    private function status(bool $exempt, int $paid, ?int $expected): string
    {
        return match (true) {
            $exempt => 'exempt',
            $paid <= 0 => 'unpaid',
            $expected !== null && $paid < $expected => 'partial',
            default => 'paid',
        };
    }
}
