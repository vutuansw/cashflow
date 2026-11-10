<?php

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Gửi một webhook SePay giả vào chính ứng dụng (không cần mạng, không cần
 * SePay) để thử luồng hàng chờ duyệt trên máy dev. Xem plan/05 mục 5.
 *
 *   php artisan cashflow:sepay-simulate chidoan-a12 500000 "CHIDOAN26 Tran Van Minh"
 */
class SepaySimulateCommand extends Command
{
    protected $signature = 'cashflow:sepay-simulate {group : mã nhóm} {amount} {content} {--out : tiền ra thay vì tiền vào} {--id= : id giao dịch SePay, mặc định ngẫu nhiên}';

    protected $description = 'Gửi webhook SePay giả để thử hàng chờ duyệt';

    public function handle(Kernel $kernel): int
    {
        $group = Group::query()->where('code', $this->argument('group'))->first();
        if ($group === null) {
            $this->error('Không có nhóm này.');

            return self::FAILURE;
        }
        if ($group->sepay_api_key === null) {
            $this->error('Nhóm chưa bật SePay (Cài đặt nhóm → SePay).');

            return self::FAILURE;
        }

        $payload = [
            'id' => (int) ($this->option('id') ?: random_int(100000, 999999999)),
            'gateway' => $group->bank_code ?? 'MBBank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => $group->bank_account_no ?? '0000000000',
            'code' => null,
            'content' => $this->argument('content'),
            'transferType' => $this->option('out') ? 'out' : 'in',
            'transferAmount' => (int) $this->argument('amount'),
            'accumulated' => 0,
            'subAccount' => null,
            'referenceCode' => 'SIM.'.now()->format('His'),
            'description' => '',
        ];

        $request = Request::create("/api/webhooks/sepay/{$group->code}", 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Apikey '.$group->sepay_api_key,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode($payload));

        $response = $kernel->handle($request);
        $this->line("HTTP {$response->getStatusCode()} ".$response->getContent());

        return $response->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
