<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Danh sách app ngân hàng mở được bằng deeplink VietQR và cách tạo link mở
 * app chuyển khoản. Xem plan/05 mục 5b.
 *
 * Lấy từ api.vietqr.io, cache 1 ngày; lỗi thì cache danh sách rỗng 10 phút để
 * trang đóng quỹ không chờ VietQR mỗi lượt xem.
 */
class BankApps
{
    public const SOURCES = [
        'android' => 'https://api.vietqr.io/v2/android-app-deeplinks',
        'ios' => 'https://api.vietqr.io/v2/ios-app-deeplinks',
    ];

    /** Tên ngân hàng theo qr.sepay.vn (bank_code của nhóm) → mã ngân hàng VietQR dùng trong tham số "ba". */
    public const VIETQR_CODES = [
        'VietinBank' => 'ICB', 'Vietcombank' => 'VCB', 'MBBank' => 'MB', 'ACB' => 'ACB', 'VPBank' => 'VPB',
        'TPBank' => 'TPB', 'MSB' => 'MSB', 'NamABank' => 'NAB', 'LienVietPostBank' => 'LPB', 'VietCapitalBank' => 'VCCB',
        'BIDV' => 'BIDV', 'Sacombank' => 'STB', 'VIB' => 'VIB', 'HDBank' => 'HDB', 'SeABank' => 'SEAB', 'GPBank' => 'GPB',
        'PVcomBank' => 'PVCB', 'NCB' => 'NCB', 'ShinhanBank' => 'SHBVN', 'SCB' => 'SCB', 'PGBank' => 'PGB',
        'Agribank' => 'VBA', 'Techcombank' => 'TCB', 'SaigonBank' => 'SGICB', 'BacABank' => 'BAB', 'ABBANK' => 'ABB',
        'VietABank' => 'VAB', 'Eximbank' => 'EIB', 'VietBank' => 'VIETBANK', 'BaoVietBank' => 'BVB', 'SHB' => 'SHB',
        'OCB' => 'OCB', 'KienLongBank' => 'KLB', 'CIMB' => 'CIMB', 'COOPBANK' => 'COOPBANK',
    ];

    /** Đoán hệ điều hành từ User-Agent; không phải điện thoại thì null (máy tính không hiện lưới app). */
    public static function os(?string $userAgent): ?string
    {
        $ua = (string) $userAgent;

        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'ios',
            (bool) preg_match('/Android/i', $ua) => 'android',
            default => null,
        };
    }

    /**
     * Bỏ cờ autofill của VietQR: từ 11/11/2026 không app nào nhận được số tiền,
     * nội dung qua link nữa dù cờ vẫn bật (plan/19).
     *
     * @return list<array{id: string, name: string, logo: string}> xếp theo mức phổ biến
     */
    public function list(string $os): array
    {
        $url = self::SOURCES[$os] ?? null;
        if ($url === null) {
            return [];
        }

        $key = "vietqr-apps:{$os}";
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(5)->acceptJson()->get($url);
            $apps = $response->successful() ? (array) $response->json('apps', []) : [];
        } catch (Throwable) {
            $apps = [];
        }

        $list = collect($apps)
            ->filter(fn ($a) => is_array($a) && is_string($a['appId'] ?? null) && preg_match('/^[a-z0-9-]{1,30}$/', $a['appId']))
            ->sortByDesc(fn ($a) => (int) ($a['monthlyInstall'] ?? 0))
            ->map(fn ($a) => [
                'id' => $a['appId'],
                'name' => mb_substr((string) ($a['appName'] ?? $a['appId']), 0, 60),
                'logo' => str_starts_with((string) ($a['appLogo'] ?? ''), 'https://') ? $a['appLogo'] : '',
            ])
            ->values()
            ->all();

        Cache::put($key, $list, $list === [] ? now()->addMinutes(10) : now()->addDay());

        return $list;
    }

    /**
     * Link mở app: https://dl.vietqr.io/pay?app=mb&ba=0123456789@mb&am=200000&tn=CHIDOAN26
     * Hiện VietQR bỏ các tham số, chỉ mở app; vẫn gửi đủ để VietQR bật lại thì tự có (plan/19).
     */
    public static function deeplink(string $appId, string $accountNo, string $bankCode, ?int $amount, ?string $content): string
    {
        $bank = strtolower(self::VIETQR_CODES[$bankCode] ?? $bankCode);

        return 'https://dl.vietqr.io/pay?'.http_build_query(array_filter([
            'app' => $appId,
            'ba' => $accountNo.'@'.$bank,
            'am' => $amount ?: null,
            'tn' => $content ?: null,
        ]), '', '&', PHP_QUERY_RFC3986);
    }
}
