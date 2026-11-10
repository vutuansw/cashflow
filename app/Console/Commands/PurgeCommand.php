<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\System\GroupService;
use App\Models\Transaction;
use App\Services\Media\ImageStore;
use App\Support\CurrentGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Chạy hằng ngày: xóa hẳn nhóm đã xóa quá 30 ngày (cùng ảnh của nhóm) và phiếu
 * đã xóa quá 30 ngày. Xem plan/07 mục 4.
 */
class PurgeCommand extends Command
{
    protected $signature = 'cashflow:purge {--days=30}';

    protected $description = 'Xóa hẳn nhóm và phiếu đã xóa mềm quá N ngày';

    public function handle(CurrentGroup $current): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $disk = ImageStore::disk();

        $groups = 0;
        foreach (Group::onlyTrashed()->where('deleted_at', '<', $cutoff)->get() as $group) {
            $disk->deleteDirectory("groups/{$group->id}");
            GroupService::query()->where('group_id', $group->id)->delete();
            // Phiếu và chiến dịch trỏ tới loại thu chi bằng khóa restrict: xóa trước, phần còn lại cascade theo nhóm.
            DB::connection('groups')->transaction(function () use ($group) {
                DB::connection('groups')->table('transactions')->where('group_id', $group->id)->delete();
                DB::connection('groups')->table('sepay_transactions')->where('group_id', $group->id)->delete();
                DB::connection('groups')->table('campaigns')->where('group_id', $group->id)->delete();
                $group->forceDelete();
            });
            $groups++;
        }

        $transactions = 0;
        Transaction::acrossGroups()->onlyTrashed()->where('deleted_at', '<', $cutoff)->with('photos')
            ->chunkById(200, function ($items) use ($disk, &$transactions) {
                foreach ($items as $t) {
                    $disk->delete($t->photos->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all());
                    $t->forceDelete();
                    $transactions++;
                }
            });

        $this->info("Đã xóa hẳn {$groups} nhóm và {$transactions} phiếu.");

        return self::SUCCESS;
    }
}
