<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Dùng trong test và trên máy dev chưa có khóa (AI_DRIVER=fake): trả kết quả
 * định sẵn, không gọi mạng. Test đặt FakeBillExtractor::$responses để giả lập
 * từng lượt đọc (kể cả lượt đọc lại bằng model dự phòng).
 */
class FakeBillExtractor implements BillExtractor
{
    /** @var list<array|\Throwable> mỗi phần tử là data trả về cho một lượt gọi, hoặc lỗi để ném */
    public static array $responses = [];

    /** @var list<string> các model đã được gọi, để test kiểm tra lượt đọc lại */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$calls = [];
    }

    public function extract(string $dataUrl, string $model): array
    {
        self::$calls[] = $model;
        $next = array_shift(self::$responses) ?? self::sample();

        if ($next instanceof \Throwable) {
            throw $next;
        }
        if (! is_array($next)) {
            throw new RuntimeException('FakeBillExtractor: phản hồi giả không hợp lệ.');
        }

        return ['model' => $model, 'data' => $next, 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 80, 'cost' => 0.0001]];
    }

    /** Bill mẫu: chuyển 500.000đ đi thăm ốm. */
    public static function sample(): array
    {
        return [
            'is_bank_receipt' => true,
            'amount' => 500000,
            'transaction_datetime' => now()->subHour()->format('Y-m-d H:i'),
            'screen_type' => 'outgoing',
            'counterparty_name' => 'NGUYEN THI LAN',
            'counterparty_account' => '0123456789',
            'note' => 'Tham om ban Lan',
            'bank_name' => 'MB Bank',
            'reference_code' => 'FT26314000001',
        ];
    }
}
