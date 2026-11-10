<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\PaymentClaim;
use App\Models\SepayTransaction;
use App\Models\Transaction;
use App\Services\Sepay\SepayMatcher;
use App\Services\Transactions\TransactionWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Hàng chờ duyệt giao dịch SePay, xem plan/05 mục 4. Người đóng chỉ được tính
 * là đã đóng sau khi QTV duyệt (lúc đó mới có phiếu thu).
 */
class SepayQueueController extends Controller
{
    public function __construct(private readonly TransactionWriter $writer) {}

    public function index(Request $request)
    {
        $status = $request->validate(['status' => ['nullable', 'in:pending,ignored,approved']])['status'] ?? 'pending';

        $items = SepayTransaction::query()
            ->where('status', $status)
            ->with(['matchedCampaign:id,group_id,name', 'suggestedContact:id,group_id,full_name'])
            ->orderBy($status === 'pending' ? 'transaction_date' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->limit(200)
            ->get();

        $linked = Transaction::query()->whereIn('sepay_transaction_id', $items->pluck('id'))->pluck('id', 'sepay_transaction_id');

        return [
            'data' => $items->map(fn (SepayTransaction $t) => $this->present($t, $linked[$t->id] ?? null)),
            'counts' => SepayTransaction::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->toBase()->pluck('n', 'status'),
        ];
    }

    /** Số giao dịch SePay + bill thành viên chờ duyệt cho chấm đỏ trên menu. */
    public function count(Group $group)
    {
        $sepay = SepayTransaction::query()->where('status', 'pending')->count();
        $claims = PaymentClaim::query()->where('status', 'pending')->count();

        // Người xin vào nhóm qua link (plan/13): chấm đỏ riêng ở mục Danh bạ, không cộng vào "Chờ duyệt".
        $joins = GroupJoinRequest::query()->where('group_id', $group->id)->where('status', 'pending')->count();

        return ['pending' => $sepay + $claims, 'sepay' => $sepay, 'claims' => $claims, 'joins' => $joins];
    }

    /** Bản nháp để mở form phiếu điền sẵn (như AI đọc bill). */
    public function show(Group $group, SepayTransaction $sepay, SepayMatcher $matcher)
    {
        $draft = $matcher->draft($sepay->transfer_type, $sepay->amount, $sepay->transaction_date->format('Y-m-d H:i'), $sepay->content, $sepay->raw['description'] ?? null);
        $draft['type'] = $sepay->transfer_type === 'in' ? 'income' : 'expense';
        $draft['campaign_id'] = $sepay->transfer_type === 'in' ? ($sepay->matched_campaign_id ?? $draft['campaign_id']) : null;
        $draft['contact_id'] = $sepay->suggested_contact_id ?? $draft['contact_id'];
        $draft['ai_fields'] = array_values(array_unique([...$draft['ai_fields'], 'type', 'amount', 'occurred_at']));

        return $this->present($sepay->load(['matchedCampaign:id,group_id,name', 'suggestedContact:id,group_id,full_name']), null) + ['draft' => $draft];
    }

    /**
     * Duyệt nhanh một chạm: tiền vào đã nhận ra chiến dịch và người đóng (hoặc
     * QTV chọn lại trong yêu cầu) thì tạo phiếu thu ngay. Tiền ra phải qua form.
     */
    public function approve(Request $request, Group $group, SepayTransaction $sepay)
    {
        $data = $request->validate(['campaign_id' => ['nullable', 'integer'], 'contact_id' => ['nullable', 'integer']]);
        $transaction = $this->approveOne($group, $request, $sepay, $data['campaign_id'] ?? null, $data['contact_id'] ?? null);

        return ['transaction_id' => $transaction->id] + $this->count($group);
    }

    /** Duyệt cả loạt các giao dịch tiền vào đã nhận ra đủ chiến dịch (không tính chiến dịch đoán) và người đóng. */
    public function approveMany(Request $request, Group $group)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']])['ids'];
        $done = 0;
        $failed = [];

        foreach (SepayTransaction::query()->whereIn('id', $ids)->where('status', 'pending')->get() as $sepay) {
            if ($sepay->campaign_guessed) {
                $failed[] = ['id' => $sepay->id, 'message' => 'Chiến dịch chỉ là đoán, hãy duyệt riêng giao dịch này.'];

                continue;
            }
            try {
                $this->approveOne($group, $request, $sepay, null, null);
                $done++;
            } catch (ValidationException $e) {
                $failed[] = ['id' => $sepay->id, 'message' => collect($e->errors())->flatten()->first()];
            }
        }

        return ['approved' => $done, 'failed' => $failed] + $this->count($group);
    }

    public function ignore(Request $request, Group $group, SepayTransaction $sepay)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        if ($sepay->status !== 'pending') {
            throw ValidationException::withMessages(['sepay' => 'Giao dịch này không còn chờ duyệt.']);
        }
        $sepay->forceFill(['status' => 'ignored', 'ignore_reason' => $data['reason'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();

        return $this->count($group);
    }

    /** Đưa giao dịch đã bỏ qua về lại hàng chờ. */
    public function restore(Group $group, SepayTransaction $sepay)
    {
        if ($sepay->status !== 'ignored') {
            throw ValidationException::withMessages(['sepay' => 'Chỉ khôi phục được giao dịch đã bỏ qua.']);
        }
        $sepay->forceFill(['status' => 'pending', 'ignore_reason' => null, 'reviewed_by' => null, 'reviewed_at' => null])->save();

        return $this->count($group);
    }

    private function approveOne(Group $group, Request $request, SepayTransaction $sepay, ?int $campaignId, ?int $contactId): Transaction
    {
        if ($sepay->status !== 'pending') {
            throw ValidationException::withMessages(['sepay' => 'Giao dịch này không còn chờ duyệt.']);
        }
        if ($sepay->transfer_type !== 'in') {
            throw ValidationException::withMessages(['sepay' => 'Tiền ra cần mở form phiếu chi để chọn loại.']);
        }

        $campaign = Campaign::find($campaignId ?? $sepay->matched_campaign_id);
        $contact = Contact::find($contactId ?? $sepay->suggested_contact_id);
        if ($campaign === null || $contact === null) {
            throw ValidationException::withMessages(['sepay' => 'Chưa rõ chiến dịch hoặc người đóng, mở form để chọn.']);
        }

        try {
            return DB::connection('groups')->transaction(fn () => $this->writer->create($group, $request->user(), [
                'type' => 'income',
                'category_id' => $campaign->category_id,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'amount' => $sepay->amount,
                'occurred_at' => $sepay->transaction_date->format('Y-m-d H:i'),
                'description' => $sepay->content,
                'sepay_transaction_id' => $sepay->id,
            ]));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['sepay' => 'Không duyệt được giao dịch này, bạn thử lại.']);
        }
    }

    private function present(SepayTransaction $t, ?int $transactionId): array
    {
        return [
            'id' => $t->id,
            'transfer_type' => $t->transfer_type,
            'amount' => $t->amount,
            'transaction_date' => $t->transaction_date->toIso8601String(),
            'content' => $t->content,
            'gateway' => $t->gateway,
            'reference_code' => $t->reference_code,
            'account_mismatch' => $t->account_mismatch,
            'campaign' => $t->matchedCampaign ? ['id' => $t->matchedCampaign->id, 'name' => $t->matchedCampaign->name] : null,
            // Đoán vì nội dung không có mã: hiện nhãn "Đoán", không đưa vào Duyệt cả loạt.
            'campaign_guessed' => $t->campaign_guessed,
            'contact' => $t->suggestedContact ? ['id' => $t->suggestedContact->id, 'name' => $t->suggestedContact->full_name] : null,
            'status' => $t->status,
            'ignore_reason' => $t->ignore_reason,
            'reviewed_at' => $t->reviewed_at?->toIso8601String(),
            'transaction_id' => $transactionId,
            'ready' => $t->transfer_type === 'in' && $t->matched_campaign_id !== null && $t->suggested_contact_id !== null,
        ];
    }
}
