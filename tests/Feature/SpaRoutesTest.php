<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SpaRoutesTest extends TestCase
{
    public function test_moi_duong_dan_giao_dien_tra_khung_spa(): void
    {
        foreach (['/', '/login', '/groups/new', '/chidoan-a12', '/chidoan-a12/admin/transactions', '/chidoan-a12/quan-tri/phieu', '/system'] as $path) {
            $this->get($path)->assertOk()->assertSee('<div id="app"></div>', false);
        }
    }

    /**
     * Nút "Xem nhóm mẫu" đọc meta của trang mở ĐẦU TIÊN. Mở app ở /login hay trang nhóm
     * rồi mới về trang chủ thì meta phải có sẵn, không chỉ ở "/" (plan/14).
     */
    public function test_moi_trang_co_meta_nhom_demo(): void
    {
        config(['cashflow.landing.demo_group' => 'nhomdemo']);
        foreach (['/', '/login', '/groups/new', '/chidoan-a12'] as $path) {
            $this->get($path)->assertOk()->assertSee('<meta name="wepo-demo-group" content="nhomdemo">', false);
        }

        config(['cashflow.landing.demo_group' => null]);
        $this->get('/login')->assertOk()->assertDontSee('wepo-demo-group', false);
    }

    public function test_api_khong_bi_spa_nuot(): void
    {
        $this->getJson('/api/app')->assertOk()->assertJson(['name' => 'WePo']);
        $this->getJson('/api/khong-co')->assertNotFound();
    }

    /** Mã bản build cho trang tự lấy bản mới sau deploy (plan/27). */
    public function test_api_app_tra_ma_build_va_khong_cho_cache(): void
    {
        $dir = 'build-test-'.uniqid();
        File::ensureDirectoryExists(public_path($dir));
        File::put(public_path("{$dir}/manifest.json"), '{"resources/js/app.js":{"file":"assets/app-abc.js","isEntry":true}}');
        Vite::useBuildDirectory($dir)->useHotFile(public_path("{$dir}/hot"));

        try {
            $response = $this->getJson('/api/app')->assertOk();
            $this->assertSame(md5_file(public_path("{$dir}/manifest.json")), $response->json('build'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

            // Deploy bản mới: manifest đổi thì mã đổi.
            File::put(public_path("{$dir}/manifest.json"), '{"resources/js/app.js":{"file":"assets/app-def.js","isEntry":true}}');
            $this->assertNotSame($response->json('build'), $this->getJson('/api/app')->json('build'));

            // Chạy dev (có file hot): không có mã, trang sẽ không kiểm tra.
            File::put(public_path("{$dir}/hot"), 'http://localhost:5173');
            $this->assertNull($this->getJson('/api/app')->json('build'));
        } finally {
            File::deleteDirectory(public_path($dir));
        }
    }

    public function test_trang_co_meta_ma_build_khop_api(): void
    {
        $build = Vite::manifestHash();
        if ($build === null) {
            $this->markTestSkipped('Chưa có public/build/manifest.json (chưa npm run build) hoặc đang chạy npm run dev.');
        }

        $this->get('/login')->assertOk()->assertSee('<meta name="wepo-build" content="'.$build.'">', false);
        $this->assertSame($build, $this->getJson('/api/app')->json('build'));
    }
}
