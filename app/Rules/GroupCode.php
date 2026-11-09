<?php

namespace App\Rules;

use App\Models\Group;
use App\Support\VietnameseText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mã nhóm: chữ thường không dấu, số, dấu gạch; 3-32 ký tự; không trùng nhóm
 * khác (kể cả nhóm đã xóa mềm, vì link cũ có thể còn được chia sẻ) và không
 * trùng đường dẫn dành riêng của ứng dụng. Xem plan/plan.md mục 4.
 */
class GroupCode implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreGroupId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = (string) $value;

        if (! preg_match(config('cashflow.group_code.pattern'), $code)) {
            $fail('Mã nhóm chỉ gồm chữ thường không dấu, số và dấu gạch ngang, dài 3-32 ký tự, không bắt đầu hay kết thúc bằng gạch.');

            return;
        }

        if (in_array($code, config('cashflow.group_code.reserved'), true)) {
            $fail('Mã nhóm này trùng với đường dẫn của hệ thống, bạn chọn mã khác nhé.');

            return;
        }

        $taken = Group::withTrashed()
            ->where('code', $code)
            ->when($this->ignoreGroupId, fn ($q) => $q->whereKeyNot($this->ignoreGroupId))
            ->exists();

        if ($taken) {
            $fail('Mã nhóm này đã có nhóm khác dùng.');
        }
    }

    public static function isAvailable(string $code): bool
    {
        $ok = true;
        (new self)->validate('code', $code, function () use (&$ok) {
            $ok = false;
        });

        return $ok;
    }

    /** Gợi ý mã từ tên nhóm: "Chi đoàn A12" thành "chi-doan-a12", trùng thì thêm số. */
    public static function suggest(string $name): string
    {
        $base = str_replace(' ', '-', VietnameseText::fold($name));
        $base = trim(substr($base, 0, 28), '-');
        if (strlen($base) < 3) {
            $base = 'nhom-'.$base;
            $base = trim($base, '-');
        }

        if (self::isAvailable($base)) {
            return $base;
        }

        for ($i = 2; $i < 1000; $i++) {
            if (self::isAvailable("{$base}-{$i}")) {
                return "{$base}-{$i}";
            }
        }

        return $base.'-'.random_int(1000, 9999);
    }
}
