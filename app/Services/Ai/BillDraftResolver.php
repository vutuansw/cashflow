<?php

namespace App\Services\Ai;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Services\Transactions\TransactionWriter;
use App\Support\CurrentGroup;
use App\Support\VietnameseText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Từ JSON thô AI đọc được + dữ liệu của nhóm hiện tại, suy ra bản nháp form
 * phiếu. Chạy bằng PHP thuần nên chỗ nào đoán sai thì viết test được. Xem
 * plan/04 mục 4.
 *
 * Nguyên tắc: không chắc thì để trống cho người dùng chọn, không đoán bừa.
 * Mọi truy vấn đi qua global scope nhóm (CurrentGroup phải đã được đặt).
 *
 * Bill chuyển vào tài khoản của nhóm (plan/26): luôn là khoản thu, tên đối
 * tác là chủ tài khoản quỹ nên không dùng để tìm người đóng.
 */
class BillDraftResolver
{
    /** Cụm hay gặp trong nội dung chuyển khoản, bỏ đi trước khi tìm tên người (đã bỏ dấu). */
    private const NOTE_FILLERS = [
        'chuyen khoan nhanh qua zalo', 'nhanh qua zalo', 'qua zalo', 'chuyen tien', 'chuyen khoan',
        'dong quy', 'nop quy', 'gui tien', 'thanh toan', 'ck', 'ct',
    ];

    public function __construct(private readonly CurrentGroup $current) {}

    /**
     * @return array{
     *     is_bill: bool, type: ?string, amount: ?int, occurred_at: ?string, campaign_id: ?int, category_id: ?int,
     *     contact_id: ?int, contact_candidates: list<array{id: int, name: string}>, payer_name: ?string,
     *     title: ?string, description: ?string, ai_fields: list<string>, bank_name: ?string, reference_code: ?string
     * }
     */
    public function resolve(array $raw): array
    {
        $note = $this->string($raw['note'] ?? null);
        $counterparty = $this->string($raw['counterparty_name'] ?? null);
        $isBill = ($raw['is_bank_receipt'] ?? true) !== false;

        $draft = [
            'is_bill' => $isBill,
            'type' => null,
            'amount' => null,
            'occurred_at' => null,
            'campaign_id' => null,
            'category_id' => null,
            'contact_id' => null,
            'contact_candidates' => [],
            'payer_name' => null,
            'title' => null,
            'description' => $note,
            'ai_fields' => [],
            'bank_name' => $this->string($raw['bank_name'] ?? null),
            'reference_code' => $this->string($raw['reference_code'] ?? null),
        ];

        if (! $isBill) {
            return $draft;
        }

        $amount = $this->amount($raw['amount'] ?? null);
        if ($amount !== null) {
            $draft['amount'] = $amount;
            $draft['ai_fields'][] = 'amount';
        }

        if ($at = $this->datetime($raw['transaction_datetime'] ?? ($raw['transaction_date'] ?? null))) {
            $draft['occurred_at'] = $at;
            $draft['ai_fields'][] = 'occurred_at';
        }

        // Chuyển vào STK của nhóm: thu, dù là màn "đã chuyển đi" trên app người đóng.
        $intoFund = $this->isIntoFund($raw);

        // Chiến dịch: nội dung chứa mã chuyển khoản. Có mã chiến dịch thì chắc chắn là khoản thu.
        $campaign = $this->campaign($note);
        $type = $intoFund ? 'income' : match ($raw['screen_type'] ?? null) {
            'outgoing' => 'expense',
            'incoming' => 'income',
            default => null,
        };
        // Tiền vào quỹ, không ghi mã mà nhóm chỉ mở đúng một chiến dịch: gần như chắc là đóng cho chiến dịch đó.
        $campaign ??= $intoFund ? $this->onlyOpenCampaign() : null;
        if ($campaign !== null) {
            $type = 'income';
            $draft['campaign_id'] = $campaign->id;
            $draft['ai_fields'][] = 'campaign_id';
        }

        $category = $campaign
            ? Category::find($campaign->category_id)
            : $this->category($note, $type);
        if ($type === null && $category !== null) {
            $type = $category->type->value;
        }
        if ($type !== null) {
            $draft['type'] = $type;
            $draft['ai_fields'][] = 'type';
        }
        if ($category !== null && ($type === null || $category->type->value === $type)) {
            $draft['category_id'] = $category->id;
            $draft['ai_fields'][] = 'category_id';
        }

        // Người: tiền vào quỹ thì chỉ tìm trong nội dung (tên đối tác là chủ TK quỹ);
        // không thì tên đối tác trên bill, rồi tên tự ghi trong nội dung.
        $noteForNames = $this->noteForNames($note);
        [$candidates, $sure] = $intoFund
            ? $this->contacts([$noteForNames], givenNameOnly: true)
            : $this->contacts([$counterparty, $noteForNames]);
        if ($sure && $candidates->count() === 1) {
            $draft['contact_id'] = $candidates->first()->id;
            $draft['ai_fields'][] = 'contact_id';
        } elseif ($candidates->isNotEmpty()) {
            $draft['contact_candidates'] = $candidates->map(fn (Contact $c) => ['id' => $c->id, 'name' => $c->full_name])->values()->all();
        } elseif ($counterparty !== null && ! $intoFund) {
            $draft['payer_name'] = $this->properName($counterparty);
            $draft['ai_fields'][] = 'payer_name';
        }

        if ($category !== null) {
            $person = $draft['contact_id'] ? $candidates->first()->full_name : $draft['payer_name'];
            $draft['title'] = mb_substr(TransactionWriter::suggestTitle($category, $person), 0, 150);
        }

        return $draft;
    }

    /** Bản đọc có dùng được không; không thì đọc lại bằng model dự phòng một lần (plan/04 mục 4). */
    public static function needsRetry(array $raw): bool
    {
        if (($raw['is_bank_receipt'] ?? true) === false) {
            return false;
        }

        $amount = $raw['amount'] ?? null;
        $noAmount = ! is_numeric(is_string($amount) ? preg_replace('/\D/', '', $amount) : $amount)
            || (int) (is_string($amount) ? preg_replace('/\D/', '', $amount) : $amount) <= 0;
        $noContext = empty($raw['transaction_datetime']) && empty($raw['transaction_date']) && empty($raw['note']);

        return $noAmount || $noContext;
    }

    /**
     * Bill chuyển vào tài khoản ngân hàng của nhóm: số tài khoản đối tác trùng
     * (chấp nhận số bị che "1032***050"), hoặc tên đối tác trùng tên chủ TK.
     */
    private function isIntoFund(array $raw): bool
    {
        $group = $this->current->get();
        if ($group === null) {
            return false;
        }

        $account = (string) preg_replace('/\D/', '', (string) $group->bank_account_no);
        $seen = $this->string($raw['counterparty_account'] ?? null);
        if ($account !== '' && $seen !== null) {
            // Mỗi đoạn ký tự che (*, x, •) thành "\d*", chữ số giữ nguyên, ký tự khác bỏ.
            $pattern = (string) preg_replace(['/[^0-9*xX•]/u', '/[*xX•]+/u'], ['', '\d*'], $seen);
            if (preg_match('/\d/', $pattern) && preg_match('/^'.$pattern.'$/', $account)) {
                return true;
            }
        }

        $owner = VietnameseText::fold($group->bank_account_name);
        $name = VietnameseText::fold($raw['counterparty_name'] ?? null);

        return $owner !== '' && $owner === $name;
    }

    private function onlyOpenCampaign(): ?Campaign
    {
        $open = Campaign::query()->where('status', 'open')->limit(2)->get(['id', 'group_id', 'category_id', 'transfer_code', 'status']);

        return $open->count() === 1 ? $open->first() : null;
    }

    /** Nội dung đã bỏ dấu, bỏ mã chiến dịch, năm và các cụm "chuyen tien"… để chỉ còn tên người. */
    private function noteForNames(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $codes = Campaign::query()->pluck('transfer_code')->filter()->map(fn ($c) => VietnameseText::fold($c))->all();
        $text = ' '.VietnameseText::fold($note).' ';
        foreach ([...$codes, ...self::NOTE_FILLERS] as $phrase) {
            $text = str_replace(" {$phrase} ", ' ', $text);
            $text = str_replace(" {$phrase} ", ' ', $text);
        }
        $text = (string) preg_replace('/ (19|20)\d\d /', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function campaign(?string $note): ?Campaign
    {
        if ($note === null) {
            return null;
        }

        // So khớp bỏ dấu cách, gạch, chữ hoa thường: "qna12 n26" vẫn ra QNA12N26.
        $compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($note)));

        return Campaign::query()
            ->orderByRaw("status = 'open' desc")->orderByDesc('id')
            ->get(['id', 'group_id', 'category_id', 'transfer_code', 'status'])
            ->first(fn (Campaign $c) => $c->transfer_code !== '' && str_contains($compact, strtoupper($c->transfer_code)));
    }

    /** Loại theo từ khóa trong nội dung: tên loại của nhóm, rồi từ khóa cấu hình. Cụm dài nhất thắng. */
    private function category(?string $note, ?string $type): ?Category
    {
        $text = ' '.VietnameseText::fold($note).' ';
        if (trim($text) === '') {
            return null;
        }

        $categories = Category::query()
            ->whereNull('archived_at')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('sort_order')
            ->get();

        $best = null;
        $bestLength = 0;
        foreach ($categories as $category) {
            $phrases = [VietnameseText::fold($category->name), ...(config("cashflow.bill_keywords.{$category->name}") ?? [])];
            foreach ($phrases as $phrase) {
                if ($phrase !== '' && str_contains($text, " {$phrase} ") && strlen($phrase) > $bestLength) {
                    $best = $category;
                    $bestLength = strlen($phrase);
                }
            }
        }

        return $best;
    }

    /**
     * Người trong danh bạ có tên xuất hiện trong các đoạn chữ, so theo bậc và
     * dừng ở bậc đầu tiên có người khớp (plan/26 mục 2):
     *   1. đủ họ tên  2. đệm + tên  3. họ + tên, đảo chỗ được ("Tu Tran")
     *   4. chỉ tên gọi, khi $givenNameOnly: không bao giờ chọn sẵn.
     * Chữ cái lẻ cuối tên để phân biệt ("Nguyễn Văn Thường B") bỏ khi so.
     *
     * @param  list<?string>  $texts
     * @return array{0: Collection<int, Contact>, 1: bool} ứng viên, và có được chọn sẵn khi chỉ một người không
     */
    private function contacts(array $texts, bool $givenNameOnly = false): array
    {
        $haystacks = array_values(array_filter(array_map(fn ($t) => ' '.VietnameseText::fold($t).' ', $texts), fn ($t) => trim($t) !== ''));
        if ($haystacks === []) {
            return [collect(), false];
        }

        $found = fn (string $needle) => $needle !== '' && collect($haystacks)->contains(fn ($h) => str_contains($h, " {$needle} "));
        $contacts = Contact::query()->where('status', 'active')->get(['id', 'group_id', 'full_name', 'search_name'])
            ->map(function (Contact $c) {
                $words = array_values(array_filter(explode(' ', $c->search_name), fn ($w) => $w !== ''));
                if (count($words) >= 3 && strlen(end($words)) === 1) {
                    array_pop($words);
                }

                return ['contact' => $c, 'words' => $words];
            })
            ->filter(fn ($c) => $c['words'] !== []);

        $tiers = [
            fn ($w) => $found(implode(' ', $w)),
            fn ($w) => count($w) >= 2 && $found(implode(' ', array_slice($w, -2))),
            fn ($w) => count($w) >= 2 && $found($w[0]) && $found(end($w)),
        ];
        if ($givenNameOnly) {
            $tiers[] = fn ($w) => $found(end($w));
        }

        foreach ($tiers as $level => $match) {
            $hits = $contacts->filter(fn ($c) => $match($c['words']));
            if ($hits->isNotEmpty()) {
                return [$hits->pluck('contact')->values(), $level < 3];
            }
        }

        return [collect(), false];
    }

    private function amount(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = preg_replace('/\D/', '', $value);
        }
        if (! is_numeric($value)) {
            return null;
        }
        $amount = (int) round((float) $value);

        return $amount > 0 && $amount <= 999_999_999_999 ? $amount : null;
    }

    private function datetime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $at = Carbon::parse(trim($value), config('app.timezone'));
        } catch (Throwable) {
            return null;
        }

        // Bill trong tương lai hoặc quá cũ là đọc sai.
        if ($at->gt(now()->addDay()) || $at->lt(now()->subYears(5))) {
            return null;
        }

        return $at->format(strlen(trim($value)) <= 10 ? 'Y-m-d 00:00' : 'Y-m-d H:i');
    }

    /** Tên trên bill thường viết hoa không dấu: "NGUYEN THI LAN" thành "Nguyen Thi Lan". */
    private function properName(string $name): string
    {
        return mb_convert_case(mb_strtolower(trim(preg_replace('/\s+/', ' ', $name))), MB_CASE_TITLE);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
