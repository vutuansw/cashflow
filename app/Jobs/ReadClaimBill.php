<?php

namespace App\Jobs;

use App\Models\BillScan;
use App\Models\Group;
use App\Services\Ai\BillScanService;
use App\Support\CurrentGroup;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * AI đọc bill thành viên vừa gửi (plan/05 mục 5). Chạy sau khi đã trả lời
 * người gửi để họ không phải chờ; QTV mở hàng chờ là thấy sẵn số AI đọc.
 */
class ReadClaimBill
{
    use Dispatchable;

    public function __construct(public readonly int $groupId, public readonly int $scanId) {}

    public function handle(CurrentGroup $current, BillScanService $service): void
    {
        $group = Group::query()->find($this->groupId);
        if ($group === null) {
            return;
        }
        $previous = $current->get();
        $current->set($group);

        try {
            $scan = BillScan::query()->whereKey($this->scanId)->where('status', 'processing')->whereNotNull('path')->first();
            if ($scan !== null) {
                $service->readIfQuota($group, $scan);
            }
        } finally {
            $current->set($previous);
        }
    }
}
