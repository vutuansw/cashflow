<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Chốt chặn trước khi làm mới database: test chỉ được đụng cashflow_test_*.
     * Bài học từ chatbot: config bị cache thì test bỏ qua phpunit.xml và xóa
     * sạch database thật.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if ($app->configurationIsCached()) {
            throw new RuntimeException('Config đang được cache nên test sẽ chạy vào database thật. Chạy "php artisan config:clear" rồi chạy lại test.');
        }

        foreach (['groups', 'system'] as $connection) {
            $database = (string) $app['config']->get("database.connections.{$connection}.database");
            if (! $app->environment('testing') || ! str_starts_with($database, 'cashflow_test_')) {
                throw new RuntimeException("Test chỉ chạy trên database cashflow_test_*, connection {$connection} đang trỏ tới {$database}. Dừng để không xóa dữ liệu thật.");
            }
        }

        return $app;
    }
}
