<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Transactions\GroupStats;
use App\Services\Transactions\TransactionWriter;
use App\Support\CurrentGroup;
use Illuminate\Database\Seeder;

/**
 * Bổ sung dữ liệu cho nhóm demo ĐÃ CÓ để box "Top đóng góp" (plan/22) có người.
 *
 * DemoSeeder thấy nhóm đã có thì bỏ qua, nên nhóm nhomdemo tạo trước plan/22
 * không ai đóng vượt mức. Seeder này thêm vào chiến dịch đang mở mới nhất:
 * - 3 người đóng thêm (tổng 1.000.000 / 800.000 / 700.000 đ, mức 500.000 đ);
 * - 1 người đóng bù năm trước, phiếu đã ẩn khỏi top.
 * Chạy lại không thêm gì nếu nhóm đã có người trên top hoặc đã có phiếu ẩn khỏi top.
 *
 *   php artisan db:seed --class=DemoTopUpSeeder --force
 */
class DemoTopUpSeeder extends Seeder
{
    /** Thứ tự trong danh bạ (sort_order) => [số tiền thêm, tiêu đề, ẩn khỏi top]. */
    private const EXTRA = [
        1 => [500_000, 'Ủng hộ thêm quỹ nhóm', false],
        3 => [300_000, 'Ủng hộ thêm quỹ nhóm', false],
        8 => [200_000, 'Ủng hộ thêm quỹ nhóm', false],
        10 => [500_000, 'Đóng bù quỹ năm trước', true],
    ];

    public function __construct(private readonly TransactionWriter $writer, private readonly GroupStats $stats) {}

    public function run(): void
    {
        $code = config('cashflow.landing.demo_group') ?: 'nhomdemo';
        $group = Group::query()->where('code', $code)->first();
        if ($group === null) {
            $this->command?->warn("Không có nhóm {$code}, bỏ qua.");

            return;
        }
        app(CurrentGroup::class)->set($group);

        $campaign = Campaign::query()->where('status', 'open')->latest('id')->first();
        if ($campaign === null || $campaign->amount_per_member === null) {
            $this->command?->warn("Nhóm {$code} không có chiến dịch đang mở có mức đóng, bỏ qua.");

            return;
        }

        if ($this->stats->topContributors($group) !== null
            || Transaction::query()->where('campaign_id', $campaign->id)->where('exclude_from_top', true)->exists()) {
            $this->command?->info("Nhóm {$code} đã có dữ liệu Top đóng góp, không thêm gì.");

            return;
        }

        $added = 0;
        foreach (self::EXTRA as $sortOrder => [$amount, $title, $exclude]) {
            $contact = Contact::query()->where('sort_order', $sortOrder)->first();
            if ($contact === null) {
                continue;
            }
            $this->writer->create($group, $group->owner, [
                'type' => 'income',
                'category_id' => $campaign->category_id,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'amount' => $amount,
                'title' => $title,
                'exclude_from_top' => $exclude,
                'occurred_at' => now()->subDays($added)->setTime(9 + $added, 30)->toDateTimeString(),
            ]);
            $added++;
        }

        $this->command?->info("Đã thêm {$added} phiếu thu vào {$campaign->name} của nhóm {$code}.");
    }
}
