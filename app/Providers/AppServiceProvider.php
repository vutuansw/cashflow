<?php

namespace App\Providers;

use App\Support\CurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mỗi request một nhóm hiện tại; scoped để không rò sang request sau nếu chạy Octane/queue.
        $this->app->scoped(CurrentGroup::class);
    }

    public function boot(): void
    {
        // Bắt sớm lỗi gán thuộc tính sai tên và truy cập quan hệ chưa nạp (N+1) khi dev.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
