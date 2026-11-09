<?php

namespace App\Support;

/** Mật khẩu tạm 8 ký tự dễ đọc qua Zalo: bỏ các ký tự dễ nhầm 0/O, 1/l/I. */
final class TempPassword
{
    public static function make(int $length = 8): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
