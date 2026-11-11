<?php

namespace App\Services\Payments;

use App\Jobs\ReadClaimBill;
use App\Jobs\SendSepayDigest;
use App\Models\BillScan;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Contact;
use App\Models\Group;
use App\Models\PaymentClaim;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\BillScanService;
use App\Services\Media\ImageStore;
use App\Services\Transactions\TransactionWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Thành viên tự gửi bill đã chuyển khoản và QTV duyệt. Xem plan/05 mục 5.
 * Bill chỉ được tính là đã đóng sau khi QTV duyệt (lúc đó mới có phiếu thu).
 */
class PaymentClaimService
{
    public function __construct(
        private readonly BillScanService $scans,
        private readonly ImageStore $images,
        private readonly TransactionWriter $writer,
        private readonly DuplicateFinder $duplicates,
    ) {}

    /**
     * Một người (trong danh bạ) chỉ có một bill đang chờ mỗi chiến dịch: gửi lại
     * thì thay ảnh cũ. Có ảnh thì không cần số tiền (AI đọc); quên chụp bill thì
     * chỉ có số tiền người gửi gõ, không có ảnh (plan/23).
     *
     * @param  array{contact_id?: ?int, payer_name?: ?string, amount?: ?int, note?: ?string}  $data
     * @return array{claim: PaymentClaim, replaced: bool}
     */
    public function submit(Group $group, Campaign $campaign, array $data, ?UploadedFile $bill, ?User $user, ?string $ip): array
    {
        // Lớp chống trùng 2: đúng file ảnh đã có trong nhóm.
        if ($bill !== null && BillScan::query()->where('file_hash', BillScanService::hash($bill))->exists()) {
            throw ValidationException::withMessages(['bill' => 'Bill này đã được gửi trước đó.']);
        }

        $contact = empty($data['contact_id']) ? null : Contact::query()->find($data['contact_id']);
        $existing = $contact === null ? null : PaymentClaim::query()
            ->where('campaign_id', $campaign->id)
            ->where('contact_id', $contact->id)
            ->where('status', 'pending')
            ->first();

        $scan = $bill === null ? null : $this->scans->store($group, $user, $bill);
        $old = $existing?->scan;

        try {
            $claim = DB::connection('groups')->transaction(function () use ($group, $campaign, $data, $contact, $existing, $scan, $user, $ip) {
                $attributes = [
                    'contact_id' => $contact?->id,
                    'payer_name' => $contact?->full_name ?? mb_substr(trim((string) ($data['payer_name'] ?? '')), 0, 120),
                    'amount' => empty($data['amount']) ? null : (int) $data['amount'],
                    // Gửi lại mà không kèm ảnh: giữ ảnh của bản chờ cũ cho thủ quỹ đối chiếu.
                    'bill_scan_id' => $scan?->id ?? $existing?->bill_scan_id,
                    'note' => trim((string) ($data['note'] ?? '')) ?: null,
                    'user_id' => $user?->id,
                    'ip' => $ip,
                ];

                if ($existing !== null) {
                    $existing->forceFill($attributes)->save();

                    return $existing;
                }

                return PaymentClaim::forceCreate($attributes + ['group_id' => $group->id, 'campaign_id' => $campaign->id, 'status' => 'pending']);
            });
        } catch (\Throwable $e) {
            if ($scan !== null) {
                $this->images->delete([$scan->path]);
                $scan->delete();
            }
            throw $e;
        }

        // Ảnh cũ bị thay: bỏ file, giữ dòng bill_scans vì vẫn tính lượt AI đã dùng.
        if ($scan !== null && $old !== null && $old->transaction_id === null) {
            $this->images->delete([$old->path]);
            $old->forceFill(['path' => null])->save();
        }

        // AI đọc sau khi đã trả lời người gửi, không bắt họ chờ.
        if ($scan !== null) {
            ReadClaimBill::dispatchAfterResponse($group->id, $scan->id);
        }

        // Email gộp cho QTV, dùng chung nhịp với giao dịch SePay (plan/03 mục 8).
        if (Cache::add("sepay-digest:{$group->id}", true, now()->addMinutes(15))) {
            SendSepayDigest::dispatch($group->id)->delay(now()->addMinute());
        }

        return ['claim' => $claim, 'replaced' => $existing !== null];
    }

    /**
     * Duyệt bill. mode "new": tạo phiếu thu mới (bị chặn khi trùng chắc, khi
     * có thể trùng thì phải xác nhận). mode "merge": ghép với lần chuyển đã có
     * (phiếu sẵn có thì gắn ảnh vào; giao dịch SePay còn chờ thì duyệt nó luôn).
     */
    public function approve(Group $group, User $user, PaymentClaim $claim, string $mode, bool $confirmed = false): Transaction
    {
        if ($claim->status !== 'pending') {
            throw ValidationException::withMessages(['claim' => 'Bill này không còn chờ duyệt.']);
        }
        $match = $this->duplicates->forClaim($claim);

        if ($mode === 'merge') {
            if ($match === null) {
                throw ValidationException::withMessages(['claim' => 'Không tìm thấy giao dịch nào để ghép, hãy duyệt thành phiếu mới.']);
            }
            if ($match['transaction'] !== null) {
                return $this->writer->attachClaim($match['transaction'], $user, $claim);
            }

            $sepay = $match['sepay'];

            return $this->writer->create($group, $user, [
                'type' => 'income',
                'category_id' => $claim->campaign->category_id,
                'campaign_id' => $claim->campaign_id,
                'contact_id' => $claim->contact_id ?? $sepay->suggested_contact_id,
                'payer_name' => $claim->contact_id ? null : $claim->payer_name,
                'amount' => $sepay->amount,
                'occurred_at' => $sepay->transaction_date->format('Y-m-d H:i'),
                'description' => $sepay->content,
                'sepay_transaction_id' => $sepay->id,
                'payment_claim_id' => $claim->id,
            ]);
        }

        if ($match !== null && $match['level'] === 'strong') {
            throw ValidationException::withMessages(['claim' => 'Bill này trùng mã giao dịch với '.self::matchLabel($match).', chỉ gắn được vào phiếu đã có.']);
        }
        if ($match !== null && ! $confirmed) {
            throw ValidationException::withMessages(['confirm' => 'Bill này có thể trùng với '.self::matchLabel($match).'. Xác nhận nếu đây là một lần chuyển khác.']);
        }

        $draft = $this->draft($claim);
        if (empty($draft['amount'])) {
            throw ValidationException::withMessages(['claim' => 'Bill này chưa có số tiền, bấm "Nhập số tiền" để duyệt.']);
        }

        return $this->writer->create($group, $user, [
            'type' => 'income',
            'category_id' => $draft['category_id'],
            'campaign_id' => $claim->campaign_id,
            'contact_id' => $claim->contact_id,
            'payer_name' => $claim->contact_id ? null : $claim->payer_name,
            'amount' => $draft['amount'],
            'occurred_at' => $draft['occurred_at'],
            'description' => $draft['description'],
            'payment_claim_id' => $claim->id,
        ]);
    }

    /**
     * Số tiền để duyệt một bill và nó lấy từ đâu (plan/23): AI đọc trên ảnh →
     * người gửi khai (khi quên chụp bill) → mức người đó phải đóng trong chiến dịch.
     *
     * @return array{amount: ?int, source: ?string} source: ai | claimed | expected
     */
    public static function amountOf(PaymentClaim $claim, ?int $aiAmount): array
    {
        if (! empty($aiAmount)) {
            return ['amount' => (int) $aiAmount, 'source' => 'ai'];
        }
        if ($claim->amount !== null) {
            return ['amount' => $claim->amount, 'source' => 'claimed'];
        }
        $member = $claim->contact_id === null ? null : CampaignMember::query()
            ->where('campaign_id', $claim->campaign_id)->where('contact_id', $claim->contact_id)->first();
        $expected = $member === null || $member->exempt ? null : ($member->expected_amount ?? $claim->campaign?->amount_per_member);

        return $expected ? ['amount' => $expected, 'source' => 'expected'] : ['amount' => null, 'source' => null];
    }

    public function ignore(User $user, PaymentClaim $claim, ?string $reason): void
    {
        if ($claim->status !== 'pending') {
            throw ValidationException::withMessages(['claim' => 'Bill này không còn chờ duyệt.']);
        }
        $claim->forceFill(['status' => 'ignored', 'ignore_reason' => $reason, 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
    }

    public function restore(PaymentClaim $claim): void
    {
        if ($claim->status !== 'ignored') {
            throw ValidationException::withMessages(['claim' => 'Chỉ khôi phục được bill đã bỏ qua.']);
        }
        // Người đó đã gửi bill khác đang chờ thì không đưa về được (mỗi người một bill chờ).
        if ($claim->contact_id !== null && PaymentClaim::query()->where('campaign_id', $claim->campaign_id)->where('contact_id', $claim->contact_id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['claim' => 'Người này đang có bill khác chờ duyệt.']);
        }
        $claim->forceFill(['status' => 'pending', 'ignore_reason' => null, 'reviewed_by' => null, 'reviewed_at' => null])->save();
    }

    /**
     * Bản nháp phiếu thu từ bill: số tiền và giờ theo AI đọc, không có thì số
     * người gửi khai, rồi mức người đó phải đóng (plan/23), giờ thì lấy giờ gửi.
     * Không có số nào thì amount null. Dùng cho Duyệt nhanh và form "Sửa".
     */
    public function draft(PaymentClaim $claim): array
    {
        $scan = $claim->scan;
        $readable = DuplicateFinder::readable($scan);
        $ai = $readable ? $scan->draft : [];
        $at = DuplicateFinder::billTime($scan);

        $aiFields = ['type', 'campaign_id'];
        if (! empty($ai['amount'])) {
            $aiFields[] = 'amount';
        }
        if ($at !== null) {
            $aiFields[] = 'occurred_at';
        }

        return [
            'is_bill' => true,
            'type' => 'income',
            'amount' => self::amountOf($claim, $ai['amount'] ?? null)['amount'],
            'occurred_at' => ($at ?? $claim->created_at)->format('Y-m-d H:i'),
            'campaign_id' => $claim->campaign_id,
            'category_id' => $claim->campaign?->category_id,
            'contact_id' => $claim->contact_id,
            'contact_candidates' => [],
            'payer_name' => $claim->contact_id ? null : $claim->payer_name,
            'title' => null,
            'description' => collect([$ai['description'] ?? null, $claim->note ? 'Ghi chú: '.$claim->note : null])->filter()->implode("\n") ?: null,
            'ai_fields' => [...$aiFields, $claim->contact_id ? 'contact_id' : 'payer_name'],
            'bank_name' => $ai['bank_name'] ?? null,
            'reference_code' => $ai['reference_code'] ?? null,
        ];
    }

    /** "giao dịch SePay +200.000 đ lúc 14:02 10/11" hoặc "phiếu thu "Đóng quỹ năm Lan" lúc …". */
    public static function matchLabel(array $match): string
    {
        $money = fn (int $v) => number_format($v, 0, ',', '.').' đ';
        if ($match['transaction'] !== null) {
            $t = $match['transaction'];

            return 'phiếu thu "'.$t->title.'" '.$money($t->amount).' lúc '.$t->occurred_at->format('H:i d/m');
        }
        $s = $match['sepay'];

        return 'giao dịch SePay +'.$money($s->amount).' lúc '.$s->transaction_date->format('H:i d/m').' (đang chờ duyệt)';
    }
}
