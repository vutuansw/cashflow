<?php

namespace App\Support;

use Illuminate\Support\Str;

final class VietnameseText
{
    /**
     * Chữ thường, bỏ dấu, gộp khoảng trắng: "Trần  Văn Đức" thành "tran van duc".
     * Dùng cho cột search_name và để so tên trong nội dung chuyển khoản.
     */
    public static function fold(?string $text): string
    {
        $text = str_replace(['đ', 'Đ'], 'd', (string) $text);
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';

        return trim($text);
    }
}
