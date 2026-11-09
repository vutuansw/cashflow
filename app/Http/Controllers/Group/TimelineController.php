<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Presenters\GroupPresenter;
use App\Http\Presenters\TransactionPresenter;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Groups\GroupViewer;
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

        return $stats->summary($group, $data['year'] ?? null) + ['campaigns' => $stats->openCampaigns($group)];
    }

    public function feed(Request $request, Group $group, TimelineFeed $feed)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:income,expense'],
            'category_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        return $feed->page($group, $filters, (int) ($filters['page'] ?? 1), $request->user()?->id);
    }

    /** Một bài, mở từ link chia sẻ hoặc khi bấm vào thẻ. */
    public function post(Request $request, Group $group, int $transaction)
    {
        $viewer = GroupViewer::of($request, $group);

        $item = TransactionPresenter::withCardRelations(Transaction::query(), $request->user()?->id)
            ->when(! $viewer->canManage(), fn ($q) => $q->where('show_on_timeline', true))
            ->findOrFail($transaction);

        return ['kind' => 'transaction'] + TransactionPresenter::card($item, $group);
    }
}
