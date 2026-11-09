<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class GroupCodeConfigTest extends TestCase
{
    private function pattern(): string
    {
        return (require __DIR__.'/../../config/cashflow.php')['group_code']['pattern'];
    }

    public function test_ma_nhom_hop_le(): void
    {
        foreach (['chidoan-a12', 'nhomabc', 'k45', 'lop-12a1-2026'] as $code) {
            $this->assertMatchesRegularExpression($this->pattern(), $code, $code);
        }
    }

    public function test_ma_nhom_khong_hop_le(): void
    {
        foreach (['ab', 'Nhom', '-abc', 'abc-', 'nhóm', 'a b c', str_repeat('a', 33)] as $code) {
            $this->assertDoesNotMatchRegularExpression($this->pattern(), $code, $code);
        }
    }
}
