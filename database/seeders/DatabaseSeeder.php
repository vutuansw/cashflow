<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Dữ liệu mẫu chỉ dành cho máy dev, không bao giờ chạy trên production. */
    public function run(): void
    {
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
