<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Presenters\GroupPresenter;
use App\Http\Presenters\TransactionPresenter;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Groups\GroupViewer;
use App\Services\Groups\MemberIndex;
use App\Services\Transactions\GroupStats;
use App\Services\Transactions\TimelineFeed;
use Illuminate\Http\Request;

/** Trang nhóm /{ma-nhom}: phần đầu, thẻ tóm tắt, timeline. Xem plan/03 mục 4, 6. */
class TimelineController extends Controller
{
    /** Phần đầu trang; nhóm private vẫn trả tên và ảnh để hiện màn hình mời đăng nhập. */
    public function show(Request $request, Group $group)
    {
        return GroupPresenter::header($group, GroupViewer::of($request, $group));
    }

    public function summary(Request $request, Group $group, GroupStats $stats)
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        // Nội quy nằm ở đây (sau middleware group.view) để nhóm riêng tư không lộ nội quy cho khách. Xem plan/15.
        return $stats->summary($group, $data['year'] ?? null) + [
            'campaigns' => $stats->openCampaigns($group),
            'rules' => $group->rules,
            'top_contributors' => $this->topContributors($group, $stats->topContributors($group), GroupViewer::of($request, $group)->canManage()),
        ];
    }

    /**
     * Box Top đóng góp (plan/22). Ảnh đại diện và trang thành viên gắn lúc trả về (không cache) để đổi ảnh là thấy ngay.
     * Khách và thành viên thường không cần biết id phiếu, cũng không biết có phiếu bị ẩn khỏi top.
     */
    private function topContributors(Group $group, ?array $top, bool $canManage): ?array
    {
        if ($top === null) {
            return null;
        }
        $index = MemberIndex::of($group);
        $top['people'] = array_map(function ($p) use ($index, $canManage) {
            $member = $index->contact($p['contact_id']);
            $p += ['member_handle' => $member['member_handle'] ?? null, 'avatar_url' => $member['avatar_url'] ?? null];

            return $canManage ? $p : array_diff_key($p, ['transaction_ids' => true]);
        }, $top['people']);
        if (! $canManage) {
            unset($top['hidden_count']);
        }

        return $top;
    }

    public function feed(Request $request, Group $group, TimelineFeed $feed)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:income,expense'],
            'category_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        return $feed->page($group, $filters, (int) ($filters['page'] ?? 1), GroupViewer::of($request, $group));
    }

    /** Một bài, mở từ link chia sẻ hoặc khi bấm vào thẻ. */
    public function post(Request $request, Group $group, int $transaction)
    {
        $viewer = GroupViewer::of($request, $group);

        $item = TransactionPresenter::withCardRelations(Transaction::query(), $viewer)
            ->when(! $viewer->canManage(), fn ($q) => $q->where('show_on_timeline', true))
            ->findOrFail($transaction);

        return ['kind' => 'transaction'] + TransactionPresenter::card($item, $group, $viewer);
    }
}
