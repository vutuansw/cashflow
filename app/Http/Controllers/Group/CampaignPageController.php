<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Group;
use App\Services\Campaigns\CampaignProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Trang đóng quỹ /{ma-nhom}/chien-dich/{slug}: tiến độ, QR chuyển khoản, ai
 * đã đóng / chưa đóng (chỉ tên, không bao giờ có SĐT). Ai xem được timeline
 * thì xem được. Xem plan/05 mục 1.
 */
class CampaignPageController extends Controller
{
    public function show(Group $group, string $slug, CampaignProgress $progress)
    {
        $campaign = Campaign::query()->where('slug', $slug)->firstOrFail();
        $data = $progress->of($campaign);
        $hasBank = $group->bank_code !== null && $group->bank_account_no !== null;
        $open = $campaign->status === 'open';

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
                'qr_download' => "/api/g/{$group->code}/campaigns/{$campaign->slug}/qr.png",
            ] : null,
            'collected' => $data['collected'],
            'expected_total' => $data['expected_total'],
            'members_total' => $data['members_total'],
            'members_paid' => $data['members_paid'],
            'members_partial' => $data['members_partial'],
            'people' => array_map(fn ($p) => array_intersect_key($p, array_flip(['name', 'expected', 'paid', 'last_paid_at', 'status', 'in_list'])), $data['people']),
        ];
    }

    /** Ảnh QR để tải về máy (ảnh trên qr.sepay.vn khác domain nên trình duyệt không cho tải thẳng). */
    public function qr(Group $group, string $slug)
    {
        $campaign = Campaign::query()->where('slug', $slug)->where('status', 'open')->firstOrFail();
        abort_if($group->bank_code === null || $group->bank_account_no === null, 404);

        $url = self::qrUrl($group, $campaign);
        $png = Cache::remember('qr:'.md5($url), now()->addDay(), function () use ($url) {
            try {
                $response = Http::timeout(10)->get($url);

                return $response->successful() ? base64_encode($response->body()) : null;
            } catch (Throwable) {
                return null;
            }
        });
        abort_if($png === null, 502, 'Không tạo được ảnh QR, bạn thử lại sau.');

        return response(base64_decode($png), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-'.$campaign->slug.'.png"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
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
