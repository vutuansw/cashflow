<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Chạy migration cho cả hai database: cashflow_groups (database/migrations)
 * và cashflow_system (database/migrations/system). Xem plan/01.
 */
class MigrateCommand extends Command
{
    protected $signature = 'cashflow:migrate
        {--fresh : Xóa hết bảng rồi tạo lại (mất dữ liệu)}
        {--seed : Chạy seeder sau khi migrate}
        {--force : Cho phép chạy ở production}';

    protected $description = 'Migrate cả hai database cashflow_groups và cashflow_system';

    public function handle(): int
    {
        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        $force = $this->option('force') ? ['--force' => true] : [];

        $this->components->info('Database cashflow_groups');
        $code = $this->call($command, ['--database' => 'groups', '--path' => 'database/migrations'] + $force);

        if ($code === self::SUCCESS) {
            $this->components->info('Database cashflow_system');
            $code = $this->call($command, ['--database' => 'system', '--path' => 'database/migrations/system'] + $force);
        }

        if ($code === self::SUCCESS && $this->option('seed')) {
            $code = $this->call('db:seed', $force);
        }

        return $code;
    }
}
