<?php

namespace App\Support;

/**
 * Mã giao dịch ngân hàng (mã tham chiếu) để nhận ra hai bản ghi là cùng một
 * lần chuyển tiền: bill AI đọc được và giao dịch SePay báo về. Xem plan/05
 * mục 5, lớp chống trùng 1.
 */
final class BankReference
{
    /** Chữ hoa, chỉ giữ chữ và số. Ngắn hơn 6 ký tự thì coi như không có (dễ trùng nhầm). */
    public static function normalize(?string $code): ?string
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $code));

        return strlen($code) >= 6 ? substr($code, 0, 100) : null;
    }

    /**
     * Cùng một mã: giống hệt, hoặc một mã là đuôi của mã kia khi đủ dài
     * (SePay gửi "MBVCB.3278907687", bill chỉ in "3278907687").
     */
    public static function same(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        [$short, $long] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];

        return strlen($short) >= 8 && str_ends_with($long, $short);
    }
}
