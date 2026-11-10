<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Group;
use App\Models\PaymentClaim;
use App\Services\Campaigns\CampaignProgress;
use App\Services\Payments\BankApps;
use App\Services\Payments\PaymentClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Trang đóng quỹ /{ma-nhom}/chien-dich/{slug}: tiến độ, QR chuyển khoản, ai
 * đã đóng / chưa đóng (chỉ tên, không bao giờ có SĐT). Ai xem được timeline
 * thì xem được, gửi được bill đã chuyển khoản. Xem plan/05 mục 1, 5, 5b.
 */
class CampaignPageController extends Controller
{
    public function show(Group $group, string $slug, CampaignProgress $progress)
    {
        $campaign = Campaign::query()->where('slug', $slug)->firstOrFail();
        $data = $progress->of($campaign);
        $hasBank = $group->bank_code !== null && $group->bank_account_no !== null;
        $open = $campaign->status === 'open';
        $pendingRows = PaymentClaim::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->pluck('contact_id');
        $pending = $pendingRows->filter()->flip()->all();
        $pendingCount = $pendingRows->count();

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'amount_per_member' => $campaign->amount_per_member,
            'target_amount' => $campaign->target_amount,
            'ends_on' => $campaign->ends_on?->toDateString(),
            'transfer_code' => $open ? $campaign->transfer_code : null,
            'bank' => $open && $hasBank ? [
                'code' => $group->bank_code,
                'account_no' => $group->bank_account_no,
                'account_name' => $group->bank_account_name,
                'qr_url' => self::qrUrl($group, $campaign),
            ] : null,
            'collected' => $data['collected'],
            'expected_total' => $data['expected_total'],
            'members_total' => $data['members_total'],
            'members_paid' => $data['members_paid'],
            'members_partial' => $data['members_partial'],
            // contact_id để chọn tên khi gửi bill; chỉ là số thứ tự, không lộ thông tin liên hệ.
            'people' => array_map(fn ($p) => array_intersect_key($p, array_flip(['contact_id', 'name', 'expected', 'paid', 'last_paid_at', 'status', 'in_list'])) + [
                'pending_claim' => $p['contact_id'] !== null && isset($pending[$p['contact_id']]),
            ], $data['people']),
            'pending_claims' => $pendingCount,
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
            'amount' => ['required', 'integer', 'min:1000', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:500'],
            'bill' => ['required', 'file', 'mimes:'.implode(',', config('cashflow.images.mimes')), 'max:10240'],
            'website' => ['nullable', 'string'],
        ], [
            'payer_name.required_without' => 'Chọn tên bạn hoặc gõ họ tên.',
            'bill.required' => 'Chọn ảnh bill chuyển khoản.',
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

    /**
     * App ngân hàng mở được bằng deeplink VietQR, kèm link đã điền sẵn tài
     * khoản nhóm, số tiền, mã chiến dịch (plan/05 mục 5b). Máy tính trả rỗng.
     */
    public function bankApps(Request $request, Group $group, string $slug, BankApps $apps)
    {
        $campaign = Campaign::query()->where('slug', $slug)->where('status', 'open')->firstOrFail();
        abort_if($group->bank_code === null || $group->bank_account_no === null, 404);

        $os = $request->query('os');
        $os = in_array($os, ['ios', 'android'], true) ? $os : BankApps::os($request->userAgent());

        return [
            'os' => $os,
            'apps' => $os === null ? [] : array_map(fn ($a) => $a + [
                'url' => BankApps::deeplink($a['id'], $group->bank_account_no, $group->bank_code, $campaign->amount_per_member, $campaign->transfer_code),
            ], $apps->list($os)),
        ];
    }

    /** QR VietQR của SePay, không cần khóa. Nội dung là mã chiến dịch, người đóng tự thêm tên phía sau. */
    public static function qrUrl(Group $group, Campaign $campaign): string
    {
        return 'https://qr.sepay.vn/img?'.http_build_query(array_filter([
            'acc' => $group->bank_account_no,
            'bank' => $group->bank_code,
            'amount' => $campaign->amount_per_member,
            'des' => $campaign->transfer_code,
            'template' => 'compact',
        ]));
    }
}
