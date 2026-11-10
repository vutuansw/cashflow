<?php

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;

/**
 * Liệt kê nhóm đang dùng mã trùng từ dành riêng (vd sau khi thêm từ mới khi đổi URL
 * sang tiếng Anh, xem plan/12). Chạy trước khi deploy; có nhóm trùng thì đổi mã ở /system.
 */
class ReservedCodesCommand extends Command
{
    protected $signature = 'cashflow:reserved-codes';

    protected $description = 'Kiểm tra nhóm nào đang dùng mã nhóm dành riêng';

    public function handle(): int
    {
        $reserved = config('cashflow.group_code.reserved');
        $groups = Group::withTrashed()->whereIn('code', $reserved)->get(['id', 'code', 'name']);

        if ($groups->isEmpty()) {
            $this->info('Không có nhóm nào dùng mã dành riêng.');

            return self::SUCCESS;
        }

        $this->table(['id', 'Mã', 'Tên'], $groups->map(fn ($g) => [$g->id, $g->code, $g->name]));
        $this->error('Các nhóm trên cần đổi mã ở /system trước khi deploy.');

        return self::FAILURE;
    }
}
