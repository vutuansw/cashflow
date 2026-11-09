<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaRoutesTest extends TestCase
{
    public function test_moi_duong_dan_giao_dien_tra_khung_spa(): void
    {
        foreach (['/', '/dang-nhap', '/chidoan-a12', '/chidoan-a12/quan-tri/phieu', '/system'] as $path) {
            $this->get($path)->assertOk()->assertSee('<div id="app"></div>', false);
        }
    }

    public function test_api_khong_bi_spa_nuot(): void
    {
        $this->getJson('/api/app')->assertOk()->assertJson(['name' => 'Quỹ nhóm']);
        $this->getJson('/api/khong-co')->assertNotFound();
    }
}
