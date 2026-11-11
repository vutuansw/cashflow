<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Group;
use App\Models\PaymentClaim;
use App\Services\Campaigns\CampaignProgress;
use App\Services\Groups\GroupViewer;
use App\Services\Groups\MemberIndex;
use App\Services\Payments\PaymentClaimService;
use App\Support\TransferContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Trang đóng quỹ /{ma-nhom}/campaigns/{slug}: tiến độ, QR chuyển khoản, ai
 * đã đóng / chưa đóng (chỉ tên, không bao giờ có SĐT). Ai xem được timeline
 * thì xem được, gửi được bill đã chuyển khoản. Xem plan/05 mục 1, 5 và plan/19.
 */
class CampaignPageController extends Controller
{
    public function show(Request $request, Group $group, string $slug, CampaignProgress $progress)
    {
        $campaign = Campaign::query()->where('slug', $slug)->firstOrFail();
        $data = $progress->of($campaign);
        $hasBank = $group->bank_code !== null && $group->bank_account_no !== null;
        $open = $campaign->status === 'open';
        $pendingRows = PaymentClaim::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->pluck('contact_id');
        $pending = $pendingRows->filter()->flip()->all();
        $pendingCount = $pendingRows->count();
        $members = MemberIndex::of($group);
        $viewer = GroupViewer::of($request, $group);
        $content = $open ? TransferContent::forViewer($viewer, $campaign) : null;

        // Nội dung CK khác nhau theo người xem: không để proxy/trình duyệt dùng chung.
        return response()->json([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'amount_per_member' => $campaign->amount_per_member,
            'target_amount' => $campaign->target_amount,
            'ends_on' => $campaign->ends_on?->toDateString(),
            'transfer_code' => $open ? $campaign->transfer_code : null,
            // null = không biết người xem: QR không có nội dung, app ngân hàng tự điền tên chủ tài khoản.
            'transfer_content' => $content,
            'bank' => $open && $hasBank ? [
                'code' => $group->bank_code,
                'account_no' => $group->bank_account_no,
                'account_name' => $group->bank_account_name,
                'qr_url' => self::qrUrl($group, $campaign, $content),
            ] : null,
            'collected' => $data['collected'],
            'expected_total' => $data['expected_total'],
            'members_total' => $data['members_total'],
            'members_paid' => $data['members_paid'],
            'members_partial' => $data['members_partial'],
            // contact_id để chọn tên khi gửi bill; chỉ là số thứ tự, không lộ thông tin liên hệ.
            'people' => array_map(fn ($p) => array_intersect_key($p, array_flip(['contact_id', 'name', 'expected', 'paid', 'last_paid_at', 'status', 'in_list'])) + [
                'pending_claim' => $p['contact_id'] !== null && isset($pending[$p['contact_id']]),
                'member_id' => $members->contact($p['contact_id'])['member_id'] ?? null,
            ], $data['people']),
            'pending_claims' => $pendingCount,
            // Thành viên đang xem: form gửi bill chọn sẵn tên họ (plan/20).
            'me' => $open ? self::me($viewer, $campaign, $data['people'], $pending) : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Dòng của chính thành viên đang xem trong danh sách người đóng. Không có trong
     * danh sách chiến dịch thì dựng từ danh bạ. Khách, chưa gắn danh bạ: null.
     *
     * @param  list<array<string, mixed>>  $people
     * @param  array<int, int>  $pending  contact_id đang có bill chờ duyệt
     */
    private static function me(GroupViewer $viewer, Campaign $campaign, array $people, array $pending): ?array
    {
        $contactId = $viewer->member?->contact_id;
        $contact = $contactId ? Contact::query()->find($contactId) : null;
        if ($contact === null) {
            return null;
        }

        $row = collect($people)->firstWhere('contact_id', $contact->id) ?? [
            'name' => $contact->full_name, 'expected' => $campaign->amount_per_member, 'paid' => 0, 'status' => 'unpaid',
        ];

        return [
            'contact_id' => $contact->id,
            'name' => $row['name'],
            'expected' => $row['expected'],
            'paid' => $row['paid'],
            'status' => $row['status'],
            'pending_claim' => isset($pending[$contact->id]),
        ];
    }

    /**
     * Thành viên đã chuyển khoản tự gửi ảnh bill (plan/05 mục 5). Ai xem được
     * trang là gửi được, không cần đăng nhập; bill vào hàng chờ duyệt.
     */
    public function claim(Request $request, Group $group, string $slug, PaymentClaimService $claims)
    {
        $campaign = Campaign::query()->where('slug', $slug)->firstOrFail();

        $key = "claim:{$group->id}:".$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['bill' => 'Bạn gửi nhiều quá, thử lại sau ít phút nhé.']);
        }

        $data = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'payer_name' => ['required_without:contact_id', 'nullable', 'string', 'max:120'],
            // Có ảnh bill thì không cần số tiền (AI đọc), quên chụp bill thì phải gõ số tiền (plan/23).
            'amount' => ['required_without:bill', 'nullable', 'integer', 'min:1000', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:500'],
            'bill' => ['nullable', 'file', 'mimes:'.implode(',', config('cashflow.images.mimes')), 'max:10240'],
            'website' => ['nullable', 'string'],
        ], [
            'payer_name.required_without' => 'Chọn tên bạn hoặc gõ họ tên.',
            'amount.required_without' => 'Chọn ảnh bill, hoặc nhập số tiền nếu quên chụp bill.',
        ], ['amount' => 'số tiền', 'bill' => 'ảnh bill', 'note' => 'ghi chú']);

        RateLimiter::hit($key, 3600);

        // Ô bẫy máy gửi tự động: người thật không thấy ô này.
        if (! empty($data['website'])) {
            return response()->json(['status' => 'pending', 'replaced' => false], 201);
        }
        if ($campaign->status !== 'open') {
            throw ValidationException::withMessages(['bill' => 'Chiến dịch đã kết thúc, không nhận bill nữa.']);
        }
        if (! empty($data['contact_id']) && ! Contact::query()->whereKey($data['contact_id'])->exists()) {
            throw ValidationException::withMessages(['contact_id' => 'Không tìm thấy tên này trong nhóm.']);
        }

        $result = $claims->submit($group, $campaign, $data, $request->file('bill'), $request->user('web'), $request->ip());

        return response()->json(['status' => 'pending', 'replaced' => $result['replaced']], 201);
    }

    /** QR VietQR của SePay, không cần khóa. Không có nội dung thì app ngân hàng tự điền tên người chuyển. */
    public static function qrUrl(Group $group, Campaign $campaign, ?string $content): string
    {
        return 'https://qr.sepay.vn/img?'.http_build_query(array_filter([
            'acc' => $group->bank_account_no,
            'bank' => $group->bank_code,
            'amount' => $campaign->amount_per_member,
            'des' => $content,
            'template' => 'compact',
        ]));
    }
}
