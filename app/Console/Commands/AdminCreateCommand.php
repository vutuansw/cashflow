<?php

namespace App\Console\Commands;

use App\Models\System\Admin;
use App\Support\TempPassword;
use Illuminate\Console\Command;

/** Tạo quản trị viên /system (người đầu tiên tạo bằng lệnh này). Xem plan/01 mục 2. */
class AdminCreateCommand extends Command
{
    protected $signature = 'cashflow:admin-create {email} {--name= : tên hiển thị} {--password= : bỏ trống thì sinh mật khẩu ngẫu nhiên}';

    protected $description = 'Tạo tài khoản quản trị /system';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email không hợp lệ.');

            return self::FAILURE;
        }
        if (Admin::query()->where('email', $email)->exists()) {
            $this->error('Email này đã là quản trị viên.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: TempPassword::make().TempPassword::make();
        Admin::query()->create([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => $password,
            'status' => 'active',
        ]);

        $this->info("Đã tạo quản trị viên {$email}.");
        if (! $this->option('password')) {
            $this->line("Mật khẩu: {$password}");
            $this->warn('Mật khẩu chỉ hiện một lần, lưu lại ngay.');
        }

        return self::SUCCESS;
    }
}
