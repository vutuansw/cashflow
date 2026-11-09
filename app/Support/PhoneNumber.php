<?php

namespace App\Support;

/**
 * Chuẩn hóa số điện thoại Việt Nam về dạng 0XXXXXXXXX (10 số), lấy từ
 * chatbot (App\Modules\TenantAccess\Support\PhoneNumber).
 *
 * Không đoán số thiếu hay thừa chữ số; số không hợp lệ trả null. Số di động
 * phải có đầu số nhà mạng đang cấp để số gõ nhầm không thành tài khoản.
 */
final class PhoneNumber
{
    private const MOBILE_PREFIXES = [
        '32', '33', '34', '35', '36', '37', '38', '39', '86', '96', '97', '98', // Viettel
        '52', '56', '58', '81', '82', '83', '84', '85', '88', '91', '92', '94', // VinaPhone, Vietnamobile
        '70', '76', '77', '78', '79', '89', '90', '93', '95', '99', // MobiFone
        '59', '87', // Gmobile, iTel
    ];

    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/[\s.\-()]/', '', trim((string) $raw));

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+84')) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '84') && strlen($digits) >= 11) {
            $national = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            return null;
        }

        // Di động 9 chữ số có đầu số nhà mạng; máy bàn 10 chữ số bắt đầu bằng mã vùng 2x.
        $mobile = strlen($national) === 9 && ctype_digit($national)
            && in_array(substr($national, 0, 2), self::MOBILE_PREFIXES, true);
        if (! $mobile && ! preg_match('/^2\d{9}$/', $national)) {
            return null;
        }

        return '0'.$national;
    }

    /** Che số ở những nơi không phải trang quản trị: 0988777222 thành 0988 xxx 222. */
    public static function mask(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 7) {
            return $phone;
        }

        return substr($phone, 0, 4).' xxx '.substr($phone, -3);
    }
}
