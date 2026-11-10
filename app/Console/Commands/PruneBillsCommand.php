<?php

namespace App\Console\Commands;

use App\Models\BillScan;
use App\Services\Media\ImageStore;
use Illuminate\Console\Command;

/**
 * Ảnh bill đã đọc nhưng người dùng không lưu thành phiếu: sau 7 ngày xóa file,
 * giữ dòng bill_scans để còn tính chi phí AI. Xem plan/01 (bill_scans).
 */
class PruneBillsCommand extends Command
{
    protected $signature = 'cashflow:prune-bills {--days=7}';

    protected $description = 'Xóa file ảnh bill không gắn vào phiếu nào sau N ngày';

    public function handle(ImageStore $images): int
    {
        $count = 0;
        BillScan::acrossGroups()
            ->whereNull('transaction_id')
            ->whereNotNull('path')
            ->where('created_at', '<', now()->subDays((int) $this->option('days')))
            ->chunkById(200, function ($scans) use ($images, &$count) {
                foreach ($scans as $scan) {
                    $images->delete([$scan->path]);
                    $scan->forceFill(['path' => null])->save();
                    $count++;
                }
            });

        $this->info("Đã xóa {$count} ảnh bill mồ côi.");

        return self::SUCCESS;
    }
}
