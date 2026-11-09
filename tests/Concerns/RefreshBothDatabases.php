<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * RefreshDatabase cho hai database: migrate:fresh cả cashflow_test_groups lẫn
 * cashflow_test_system một lần mỗi lượt chạy test, rồi bọc mỗi test trong
 * transaction của cả hai connection.
 */
trait RefreshBothDatabases
{
    use RefreshDatabase;

    /** @var list<string> */
    protected array $connectionsToTransact = ['groups', 'system'];

    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('cashflow:migrate', ['--fresh' => true]);
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
