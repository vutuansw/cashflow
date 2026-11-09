<?php

namespace App\Support;

use RuntimeException;

/**
 * HMAC-SHA256 của SĐT đã chuẩn hóa, để đăng nhập và tra trùng mà không lưu SĐT
 * thô làm khóa. Khóa PHONE_HASH_KEY riêng, khác APP_KEY. Xem plan/01 mục 3.
 */
final class PhoneHash
{
    /** Trả null khi SĐT không hợp lệ. */
    public static function make(?string $phone): ?string
    {
        $normalized = PhoneNumber::normalize($phone);

        return $normalized === null ? null : self::ofNormalized($normalized);
    }

    public static function ofNormalized(string $normalized): string
    {
        $key = (string) config('cashflow.phone_hash_key');

        if ($key === '') {
            throw new RuntimeException('Chưa cấu hình PHONE_HASH_KEY. Chạy "php artisan cashflow:key".');
        }

        return hash_hmac('sha256', $normalized, $key);
    }
}
