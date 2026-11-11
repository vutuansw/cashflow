<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\PaymentClaim;
use App\Services\Campaigns\CampaignProgress;
use App\Services\Payments\DuplicateFinder;
use App\Services\Payments\PaymentClaimService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Hàng chờ duyệt, tab "Bill thành viên": bill người đóng tự gửi từ trang đóng
 * quỹ. Xem plan/05 mục 5.
 */
class PaymentClaimController extends Controller
{
    public function __construct(
        private readonly PaymentClaimService $claims,
        private readonly DuplicateFinder $duplicates,
        private readonly CampaignProgress $progress,
    ) {}

    public function index(Request $request, Group $group)
    {
        $status = $request->validate(['status' => ['nullable', 'in:pending,ignored,approved']])['status'] ?? 'pending';

        $items = PaymentClaim::query()
            ->where('status', $status)
            ->with(['campaign', 'contact:id,group_id,full_name', 'scan'])
            ->orderBy($status === 'pending' ? 'created_at' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->limit(200)
            ->get();

        // Ai đã đóng đủ trong từng chiến dịch (lớp chống trùng 4).
        $paid = [];
        foreach ($items->pluck('campaign')->filter()->unique('id') as $campaign) {
            foreach ($this->progress->of($campaign)['people'] as $p) {
                if ($p['contact_id'] !== null && $p['status'] === 'paid') {
                    $paid[$campaign->id][$p['contact_id']] = true;
                }
            }
        }

        return [
            'data' => $items->map(fn (PaymentClaim $c) => $this->present($group, $c, $status === 'pending', isset($paid[$c->campaign_id][$c->contact_id]))),
            'counts' => PaymentClaim::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->toBase()->pluck('n', 'status'),
        ];
    }

    /** Bản nháp để mở form phiếu điền sẵn ("Sửa"). */
    public function show(Group $group, PaymentClaim $claim)
    {
        $claim->load(['campaign', 'contact:id,group_id,full_name', 'scan']);

        return $this->present($group, $claim, $claim->status === 'pending', false) + ['draft' => $this->claims->draft($claim)];
    }

    public function approve(Request $request, Group $group, PaymentClaim $claim)
    {
        $data = $request->validate(['mode' => ['nullable', 'in:new,merge'], 'confirm' => ['nullable', 'boolean']]);

        try {
            $transaction = $this->claims->approve($group, $request->user(), $claim, $data['mode'] ?? 'new', (bool) ($data['confirm'] ?? false));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['claim' => 'Không duyệt được bill này, bạn thử lại.']);
        }

        return ['transaction_id' => $transaction->id];
    }

    public function ignore(Request $request, Group $group, PaymentClaim $claim)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->claims->ignore($request->user(), $claim, $data['reason'] ?? null);

        return response()->noContent();
    }

    public function restore(Group $group, PaymentClaim $claim)
    {
        $this->claims->restore($claim);

        return response()->noContent();
    }

    private function present(Group $group, PaymentClaim $c, bool $checkDuplicate, bool $alreadyPaid): array
    {
        $scan = $c->scan;
        $readable = DuplicateFinder::readable($scan);
        $ai = $readable ? $scan->draft : null;
        $match = $checkDuplicate ? $this->duplicates->forClaim($c) : null;
        $approve = PaymentClaimService::amountOf($c, $ai['amount'] ?? null);

        $warnings = [];
        if ($scan?->status === 'done' && ! ($scan->draft['is_bill'] ?? false)) {
            $warnings[] = 'Ảnh không giống bill chuyển khoản.';
        }
        // Chỉ so khi người gửi có khai số tiền (quên chụp bill), plan/23.
        if ($ai !== null && $c->amount !== null && (int) $ai['amount'] !== $c->amount) {
            $warnings[] = 'Số tiền AI đọc khác số người gửi khai.';
        }

        return [
            'id' => $c->id,
            'status' => $c->status,
            'name' => $c->personName(),
            'contact_id' => $c->contact_id,
            'campaign' => $c->campaign ? ['id' => $c->campaign->id, 'name' => $c->campaign->name, 'transfer_code' => $c->campaign->transfer_code] : null,
            // Số người gửi khai, null khi gửi kèm ảnh (AI đọc).
            'amount' => $c->amount,
            // Số sẽ duyệt và nó lấy từ đâu: ai | claimed | expected; null thì QTV phải nhập.
            'approve_amount' => $approve['amount'],
            'amount_source' => $approve['source'],
            'note' => $c->note,
            'submitted_at' => $c->created_at->toIso8601String(),
            'bill_url' => $scan?->path ? MediaUrl::bill($group, $scan) : null,
            'ai' => [
                'state' => $scan?->status ?? 'none',
                'amount' => $ai['amount'] ?? null,
                'occurred_at' => $ai['occurred_at'] ?? null,
                'reference_code' => $ai['reference_code'] ?? null,
                'note' => $ai['description'] ?? null,
                'bank_name' => $ai['bank_name'] ?? null,
            ],
            'warnings' => $warnings,
            'already_paid' => $alreadyPaid,
            'duplicate' => $match === null ? null : [
                'level' => $match['level'],
                'label' => PaymentClaimService::matchLabel($match),
                'transaction_id' => $match['transaction']?->id,
                'sepay_id' => $match['sepay']?->id,
            ],
            'ignore_reason' => $c->ignore_reason,
            'reviewed_at' => $c->reviewed_at?->toIso8601String(),
            'transaction_id' => $c->transaction_id,
        ];
    }
}
