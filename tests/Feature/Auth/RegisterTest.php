<?php

namespace Tests\Feature\Auth;

use App\Models\Group;
use App\Models\User;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Đăng ký tài khoản, tách khỏi tạo nhóm, xem plan/11. */
class RegisterTest extends TestCase
{
    use RefreshBothDatabases;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Nguyễn Văn An',
            'phone' => '0988 777 222',
            'email' => 'an@example.com',
            'password' => 'matkhau123',
        ];
    }

    public function test_dang_ky_tao_tai_khoan_va_dang_nhap_luon_khong_tao_nhom(): void
    {
        $this->postJson('/api/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('me.name', 'Nguyễn Văn An')
            ->assertJsonCount(0, 'me.groups');

        $this->assertAuthenticated();
        $this->assertSame('an@example.com', User::wherePhone('0988777222')->firstOrFail()->email);
        $this->assertSame(0, Group::count());
    }

    public function test_email_khong_bat_buoc(): void
    {
        $this->postJson('/api/register', $this->payload(['email' => null]))->assertCreated();
        $this->postJson('/api/logout');
        $this->postJson('/api/register', $this->payload(['phone' => '0988777333', 'email' => '']))->assertCreated();

        $this->assertSame([null, null], User::orderBy('id')->pluck('email')->all());
    }

    public function test_email_trung_bi_tu_choi(): void
    {
        User::factory()->create(['email' => 'an@example.com']);

        $this->postJson('/api/register', $this->payload())->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_sdt_da_co_tai_khoan_thi_bao_dang_nhap(): void
    {
        User::factory()->create(['phone' => '0988777222']);

        $this->postJson('/api/register', $this->payload(['phone' => '+84988777222']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
        $this->assertSame(1, User::count());
    }

    public function test_truong_nhom_cu_gui_kem_bi_bo_qua(): void
    {
        $this->postJson('/api/register', $this->payload(['group_name' => 'Chi đoàn A12', 'group_code' => 'chidoan-a12', 'visibility' => 'public']))
            ->assertCreated();

        $this->assertSame(0, Group::count());
    }

    public function test_o_an_chong_bot(): void
    {
        $this->postJson('/api/register', $this->payload(['website' => 'http://spam']))
            ->assertJsonValidationErrors('website');
        $this->assertGuest();
    }

    /** Lỗi trên server 11/11/2026: khách nhập sai 3 lần (422) là hết lượt, lần đúng sau đó bị 429 "Too Many Attempts.". */
    public function test_nhap_sai_nhieu_lan_van_dang_ky_duoc(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/register', $this->payload(['password' => 'ngan']))->assertUnprocessable();
        }

        $this->postJson('/api/register', $this->payload())->assertCreated();
    }

    public function test_moi_ip_toi_da_10_tai_khoan_moi_gio(): void
    {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/register', $this->payload(['phone' => '09887770'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'email' => null]))->assertCreated();
            $this->postJson('/api/logout');
        }

        $this->postJson('/api/register', $this->payload(['phone' => '0988777099', 'email' => null]))->assertStatus(429);
    }

    public function test_gui_qua_nhieu_lan_bi_chan_va_bao_tieng_viet(): void
    {
        foreach (range(1, 30) as $i) {
            $this->postJson('/api/register', $this->payload(['password' => 'ngan']))->assertUnprocessable();
        }

        $this->postJson('/api/register', $this->payload())
            ->assertStatus(429)
            ->assertJsonPath('message', 'Bạn thử quá nhiều lần. Đợi 60 phút rồi thử lại nhé.');
        $this->assertGuest();
    }
}
