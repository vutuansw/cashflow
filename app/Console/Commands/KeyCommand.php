<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Sinh PHONE_HASH_KEY vào .env. Không ghi đè khóa đã có: đổi khóa là mọi
 * phone_hash cũ mất hiệu lực và không ai đăng nhập bằng SĐT được nữa.
 */
class KeyCommand extends Command
{
    protected $signature = 'cashflow:key {--show : Chỉ in khóa mới ra màn hình, không ghi .env}';

    protected $description = 'Sinh khóa PHONE_HASH_KEY cho .env';

    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $path = $this->laravel->environmentFilePath();
        $env = is_file($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^PHONE_HASH_KEY=(.+)$/m', $env, $match) && trim($match[1]) !== '') {
            $this->components->warn('PHONE_HASH_KEY đã có, không ghi đè. Đổi khóa làm mọi tài khoản không đăng nhập được bằng SĐT.');

            return self::FAILURE;
        }

        $env = Str::contains($env, 'PHONE_HASH_KEY=')
            ? preg_replace('/^PHONE_HASH_KEY=.*$/m', 'PHONE_HASH_KEY='.$key, $env)
            : rtrim($env)."\nPHONE_HASH_KEY={$key}\n";

        file_put_contents($path, $env);
        $this->components->info('Đã ghi PHONE_HASH_KEY vào .env. Nhớ sao lưu khóa này cùng APP_KEY ra ngoài server.');

        return self::SUCCESS;
    }
}
