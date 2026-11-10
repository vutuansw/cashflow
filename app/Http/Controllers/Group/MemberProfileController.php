<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Presenters\TransactionPresenter;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Transaction;
use App\Services\Campaigns\CampaignProgress;
use App\Services\Groups\GroupViewer;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

/**
 * Trang thành viên /{ma-nhom}/thanh-vien/{memberId}: ảnh, tên, vai trò, thẻ
 * tóm tắt đóng quỹ và timeline các phiếu người đó đóng hoặc nhận trong nhóm.
 * Ai xem được timeline nhóm thì xem được; không bao giờ có SĐT, ngày sinh,
 * địa chỉ. Xem plan/08 mục 1, 2, 5.
 */
class MemberProfileController extends Controller
{
    public const PER_PAGE = 20;

    /**
     * Danh sách thành viên /{ma-nhom}/thanh-vien (bấm "19 thành viên" ở đầu trang nhóm): mọi người đang tham gia
     * trong danh bạ, cùng số đếm với đầu trang. Chỉ tên, ảnh và vai trò quản trị như hàng avatar; không SĐT, ngày
     * sinh, địa chỉ. Người có tài khoản có member_id để mở trang thành viên. Xem plan/08 mục 10e.
     */
    public function index(Group $group)
    {
        $accounts = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->whereNotNull('contact_id')
            ->with('user:id,avatar_path')
            ->get(['id', 'user_id', 'contact_id', 'role'])
            ->keyBy('contact_id');

        $people = Contact::acrossGroups()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->orderBy('sort_order')->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(function (Contact $c) use ($accounts) {
                $m = $accounts->get($c->id);

                return [
                    'name' => $c->full_name,
                    'member_id' => $m?->id,
                    'avatar_url' => $m?->user ? MediaUrl::profile($m->user, 'avatar') : null,
                    'role' => $m?->role->value,
                    'role_label' => $m?->role->canManage() ? $m->role->label() : null,
                ];
            });

        return ['data' => $people->values()];
    }

    public function show(Request $request, Group $group, int $member, CampaignProgress $progress)
    {
        $viewer = GroupViewer::of($request, $group);
        $m = $this->member($viewer, $member);
        $user = $m->user;

        return [
            'member' => [
                'id' => $m->id,
                'name' => $user->name,
                'bio' => $user->bio,
                'avatar_url' => MediaUrl::profile($user, 'avatar'),
                'cover_url' => MediaUrl::profile($user, 'cover'),
                'role' => $m->role->value,
                'role_label' => $m->role->label(),
                'removed' => $m->status !== 'active',
                'joined_at' => $m->created_at?->toIso8601String(),
                'is_me' => $viewer->user?->id === $user->id,
            ],
            'stats' => $this->stats($m->contact_id, $progress),
        ];
    }

    public function timeline(Request $request, Group $group, int $member)
    {
        $viewer = GroupViewer::of($request, $group);
        $m = $this->member($viewer, $member);
        $page = max(1, (int) $request->query('page', 1));

        if ($m->contact_id === null) {
            return ['data' => [], 'has_more' => false, 'page' => $page];
        }

        // Không gộp theo chiến dịch/ngày như timeline nhóm: mỗi phiếu một thẻ.
        $items = TransactionPresenter::withCardRelations(Transaction::query(), $viewer)
            ->where('contact_id', $m->contact_id)
            ->where('show_on_timeline', true)
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        return [
            'data' => $items->take(self::PER_PAGE)->map(fn (Transaction $t) => ['kind' => 'transaction'] + TransactionPresenter::card($t, $group, $viewer))->values(),
            'has_more' => $items->count() > self::PER_PAGE,
            'page' => $page,
        ];
    }

    /** Thành viên đã rời nhóm chỉ QTV còn xem được trang. */
    private function member(GroupViewer $viewer, int $id): GroupMember
    {
        $m = GroupMember::query()
            ->where('group_id', $viewer->group->id)
            ->whereKey($id)
            ->with('user')
            ->firstOrFail();

        abort_if($m->user === null || ($m->status !== 'active' && ! $viewer->canManage()), 404);

        return $m;
    }

    /**
     * Tổng đã đóng và tình trạng từng chiến dịch. Người xem timeline vốn đã
     * thấy những số này ở bảng đã đóng/chưa đóng, nên không lộ thêm gì.
     */
    private function stats(?int $contactId, CampaignProgress $progress): array
    {
        $year = (int) now()->year;
        $empty = ['year' => $year, 'paid_year' => 0, 'paid_total' => 0, 'campaigns_paid' => 0, 'campaigns_total' => 0, 'open_campaigns' => []];
        if ($contactId === null) {
            return $empty;
        }

        $income = Transaction::query()->where('contact_id', $contactId)->where('type', 'income');
        $stats = [
            'paid_year' => (int) (clone $income)->whereYear('occurred_at', $year)->sum('amount'),
            'paid_total' => (int) (clone $income)->sum('amount'),
        ] + $empty;

        $campaigns = Campaign::query()
            ->whereIn('id', CampaignMember::query()->where('contact_id', $contactId)->select('campaign_id'))
            ->orderByDesc('id')
            ->get();

        foreach ($campaigns as $campaign) {
            $person = collect($progress->of($campaign)['people'])->firstWhere('contact_id', $contactId);
            if ($person === null) {
                continue;
            }
            if ($campaign->status === 'open') {
                $stats['open_campaigns'][] = $this->campaignRow($campaign, $person);
            }
            // Được miễn thì không tính vào số chiến dịch phải đóng.
            if ($person['status'] !== 'exempt') {
                $stats['campaigns_total']++;
                $stats['campaigns_paid'] += $person['status'] === 'paid' ? 1 : 0;
            }
        }

        return $stats;
    }

    private function campaignRow(Campaign $campaign, array $person): array
    {
        return [
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'status' => $person['status'],
            'paid' => $person['paid'],
            'expected' => $person['expected'],
        ];
    }
}
