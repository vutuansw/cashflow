<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshBothDatabases;

    public function test_cashflow_groups_du_bang(): void
    {
        foreach ([
            'users', 'groups', 'group_members', 'contacts', 'categories', 'campaigns', 'campaign_members',
            'transactions', 'transaction_photos', 'transaction_histories', 'bill_scans', 'sepay_transactions',
            'reactions', 'comments', 'sessions', 'jobs', 'cache',
        ] as $table) {
            $this->assertTrue(Schema::connection('groups')->hasTable($table), "Thiếu bảng {$table} ở cashflow_groups");
        }
    }

    public function test_cashflow_system_du_bang_va_khong_lan_bang_nhom(): void
    {
        foreach (['admins', 'group_services', 'admin_audit_logs', 'platform_settings'] as $table) {
            $this->assertTrue(Schema::connection('system')->hasTable($table), "Thiếu bảng {$table} ở cashflow_system");
        }

        $this->assertFalse(Schema::connection('system')->hasTable('transactions'));
        $this->assertFalse(Schema::connection('groups')->hasTable('admins'));
    }
}
