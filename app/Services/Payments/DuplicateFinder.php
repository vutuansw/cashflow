<?php

namespace App\Services\Payments;

use App\Models\BillScan;
use App\Models\PaymentClaim;
use App\Models\SepayTransaction;
use App\Models\Transaction;
use App\Support\BankReference;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Nhận ra một lần chuyển tiền đã có trong quỹ để không ra hai phiếu. Xem
 * plan/05 mục 5 "Chống trùng".
 *
 * - Trùng chắc (strong): mã giao dịch ngân hàng trên bill trùng mã của giao
 *   dịch SePay (chờ hoặc đã duyệt) hoặc của bill đã gắn vào phiếu.
 * - Có thể trùng (soft): cùng chiến dịch, cùng số tiền, giờ lệch không quá
 *   30 phút, và không phải hai người khác nhau (biết cả hai người mà khác
 *   nhau thì không tính).
 *
 * Mọi truy vấn đi qua global scope nhóm (CurrentGroup phải đã được đặt).
 */
class DuplicateFinder
{
    public const WINDOW_MINUTES = 30;

    /**
     * @return array{level: 'strong'|'soft', sepay: ?SepayTransaction, transaction: ?Transaction}|null
     *                                                                                                 transaction là phiếu đã có (null khi chỉ khớp giao dịch SePay còn chờ duyệt)
     */
    public function forClaim(PaymentClaim $claim): ?array
    {
        $scan = $claim->scan;

        return $this->find(
            $scan,
            $claim->campaign_id,
            $claim->contact_id,
            self::amounts($claim),
            self::billTime($scan),
        );
    }

    /** Bill QTV đọc bằng AI (plan/04): chỉ xét trùng chắc theo mã giao dịch. */
    public function forScan(BillScan $scan, ?int $exceptSepayId = null): ?array
    {
        $found = $this->strong($scan);
        if ($found !== null && $exceptSepayId !== null && $found['sepay']?->id === $exceptSepayId) {
            return null;
        }

        return $found;
    }

    /**
     * @param  list<int>  $amounts
     */
    public function find(?BillScan $scan, ?int $campaignId, ?int $contactId, array $amounts, ?Carbon $at): ?array
    {
        if ($scan !== null && ($strong = $this->strong($scan)) !== null) {
            return $strong;
        }
        if ($campaignId === null || $amounts === [] || $at === null) {
            return null;
        }

        $from = $at->copy()->subMinutes(self::WINDOW_MINUTES);
        $to = $at->copy()->addMinutes(self::WINDOW_MINUTES);
        $compatible = fn (?int $other) => $contactId === null || $other === null || $other === $contactId;

        $candidates = collect();

        SepayTransaction::query()
            ->where('transfer_type', 'in')
            ->where('status', 'pending')
            ->where('matched_campaign_id', $campaignId)
            ->whereIn('amount', $amounts)
            ->whereBetween('transaction_date', [$from, $to])
            ->get()
            ->filter(fn (SepayTransaction $s) => $compatible($s->suggested_contact_id))
            ->each(fn ($s) => $candidates->push(['at' => $s->transaction_date, 'sepay' => $s, 'transaction' => null]));

        // Phiếu đã có (kể cả phiếu từ SePay đã duyệt, nhập tay, bill QTV đọc).
        Transaction::query()
            ->where('type', 'income')
            ->where('campaign_id', $campaignId)
            ->whereIn('amount', $amounts)
            ->whereBetween('occurred_at', [$from, $to])
            ->get()
            ->filter(fn (Transaction $t) => $compatible($t->contact_id))
            ->each(fn ($t) => $candidates->push(['at' => $t->occurred_at, 'sepay' => $t->sepayTransaction, 'transaction' => $t]));

        $best = $candidates->sortBy(fn ($c) => abs($c['at']->diffInSeconds($at)))->first();

        return $best === null ? null : ['level' => 'soft', 'sepay' => $best['sepay'], 'transaction' => $best['transaction']];
    }

    /**
     * Chiều ngược lại: vừa duyệt giao dịch SePay thành phiếu, tìm bill thành
     * viên đang chờ cho đúng lần chuyển đó để gắn vào phiếu. Khớp mềm ở đây
     * đòi cùng người vì việc gắn diễn ra tự động.
     *
     * @return Collection<int, PaymentClaim>
     */
    public function claimsFor(Transaction $transaction, SepayTransaction $sepay): Collection
    {
        $claims = PaymentClaim::query()->where('status', 'pending')->with('scan')->get();

        return $claims->filter(function (PaymentClaim $claim) use ($transaction, $sepay) {
            if (BankReference::same($claim->scan?->reference_norm, $sepay->reference_norm)) {
                return true;
            }
            if ($claim->campaign_id !== $transaction->campaign_id || $claim->contact_id === null || $claim->contact_id !== $transaction->contact_id) {
                return false;
            }
            if (! in_array($transaction->amount, self::amounts($claim), true)) {
                return false;
            }
            $at = self::billTime($claim->scan);
            if ($at !== null) {
                return abs($at->diffInMinutes($sepay->transaction_date)) <= self::WINDOW_MINUTES;
            }

            // Bill không đọc được giờ: thành viên thường gửi bill sau khi chuyển.
            return $claim->created_at->between($sepay->transaction_date->copy()->subMinutes(self::WINDOW_MINUTES), $sepay->transaction_date->copy()->addDays(3));
        })->values();
    }

    /** Số tiền để so: số AI đọc trên bill và số người gửi khai. */
    public static function amounts(PaymentClaim $claim): array
    {
        $ai = self::readable($claim->scan) ? (int) $claim->scan->draft['amount'] : null;

        return array_values(array_unique(array_filter([$ai, $claim->amount])));
    }

    /** Ngày giờ giao dịch AI đọc được trên bill, chỉ khi có cả giờ. */
    public static function billTime(?BillScan $scan): ?Carbon
    {
        $value = self::readable($scan) ? ($scan->draft['occurred_at'] ?? null) : null;
        if (! is_string($value) || strlen($value) < 16) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i', substr($value, 0, 16), config('app.timezone'));
    }

    public static function readable(?BillScan $scan): bool
    {
        return $scan !== null && $scan->status === 'done' && ($scan->draft['is_bill'] ?? false) && ! empty($scan->draft['amount']);
    }

    private function strong(BillScan $scan): ?array
    {
        $ref = $scan->reference_norm;
        if ($ref === null) {
            return null;
        }

        $sepay = SepayTransaction::query()
            ->where('transfer_type', 'in')
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotNull('reference_norm')
            ->where(fn ($q) => $q->where('reference_norm', 'like', '%'.$ref)->orWhereRaw('? LIKE CONCAT(\'%\', reference_norm)', [$ref]))
            ->get()
            ->first(fn (SepayTransaction $s) => BankReference::same($s->reference_norm, $ref));
        if ($sepay !== null) {
            return ['level' => 'strong', 'sepay' => $sepay, 'transaction' => $sepay->status === 'approved' ? $sepay->transaction : null];
        }

        $other = BillScan::query()
            ->whereKeyNot($scan->id)
            ->whereNotNull('transaction_id')
            ->whereNotNull('reference_norm')
            ->where(fn ($q) => $q->where('reference_norm', 'like', '%'.$ref)->orWhereRaw('? LIKE CONCAT(\'%\', reference_norm)', [$ref]))
            ->with('transaction')
            ->get()
            ->first(fn (BillScan $s) => BankReference::same($s->reference_norm, $ref) && $s->transaction !== null);

        return $other === null ? null : ['level' => 'strong', 'sepay' => null, 'transaction' => $other->transaction];
    }
}
