<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_chuan_hoa_ve_dang_0_va_10_so(string $raw, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($raw));
    }

    public static function validNumbers(): array
    {
        return [
            'có dấu chấm' => ['0988.777.222', '0988777222'],
            'có khoảng trắng' => ['098 877 7222', '0988777222'],
            'đầu +84' => ['+84988777222', '0988777222'],
            'đầu 84' => ['84988777222', '0988777222'],
            'máy bàn' => ['024 3825 1234', '02438251234'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_so_khong_hop_le_tra_null(?string $raw): void
    {
        $this->assertNull(PhoneNumber::normalize($raw));
    }

    public static function invalidNumbers(): array
    {
        return [
            'rỗng' => [''],
            'null' => [null],
            'thiếu số' => ['098877722'],
            'thừa số' => ['09887772221'],
            'đầu số không cấp' => ['0698777222'],
            'có chữ' => ['0988abc222'],
        ];
    }

    public function test_che_so_dien_thoai(): void
    {
        $this->assertSame('0988 xxx 222', PhoneNumber::mask('0988777222'));
    }
}
