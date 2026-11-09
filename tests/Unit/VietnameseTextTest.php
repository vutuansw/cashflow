<?php

namespace Tests\Unit;

use App\Support\VietnameseText;
use PHPUnit\Framework\TestCase;

class VietnameseTextTest extends TestCase
{
    public function test_bo_dau_chu_thuong_gop_khoang_trang(): void
    {
        $this->assertSame('tran van duc', VietnameseText::fold('  Trần  Văn ĐỨC '));
        $this->assertSame('nguyen thi lan 12a', VietnameseText::fold('Nguyễn Thị Lan - 12A'));
    }
}
