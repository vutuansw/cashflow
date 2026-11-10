<?php

namespace Tests\Feature\System;

use App\Models\System\Admin;
use App\Models\System\AdminAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Quản trị viên /system tự đổi mật khẩu, xem plan/10. */
class AdminPasswordTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private const OLD = 'matkhau-cu-123';

    private const NEW = 'matkhau-moi-456';

    private function admin(array $attrs = []): Admin
    {
        // fresh(): nạp đủ cột như request thật (logout() cần remember_token).
        return Admin::query()->create($attrs + ['name' => 'HIPOTECH', 'email' => 'admin@example.test', 'password' => self::OLD, 'status' => 'active'])->fresh();
    }

    private function change(array $body)
    {
        return $this->putJson('/api/system/me/password', $body);
    }

    public function test_doi_dung_thi_duoc_va_dang_nhap_lai_bang_mat_khau_moi(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'system');

        $this->change(['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.test');

        $this->assertTrue(Hash::check(self::NEW, $admin->fresh()->password));
        $this->assertTrue(AdminAuditLog::query()->where('action', 'change_password')->where('admin_id', $admin->id)->exists());
        // Phiên đang dùng để đổi vẫn còn hiệu lực.
        $this->getJson('/api/system/overview')->assertOk();

        $this->postJson('/api/system/logout')->assertNoContent();
        Auth::guard('system')->forgetUser();
        $this->postJson('/api/system/login', ['email' => 'admin@example.test', 'password' => self::OLD])->assertJsonValidationErrors('email');
        $this->postJson('/api/system/login', ['email' => 'admin@example.test', 'password' => self::NEW])->assertOk();
    }

    public function test_sai_mat_khau_hien_tai_thi_bi_tu_choi(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'system');

        $this->change(['current_password' => 'khong-dung-1', 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertJsonValidationErrors('current_password');
        $this->change(['password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::OLD, $admin->fresh()->password));
        $this->assertFalse(AdminAuditLog::query()->where('action', 'change_password')->exists());
    }

    public function test_luat_mat_khau_moi(): void
    {
        $this->actingAs($this->admin(), 'system');

        // Ngắn hơn 8 ký tự, nhập lại không khớp, trùng mật khẩu cũ.
        $this->change(['current_password' => self::OLD, 'password' => 'ngan12', 'password_confirmation' => 'ngan12'])
            ->assertJsonValidationErrors('password');
        $this->change(['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => 'khac-han-789'])
            ->assertJsonValidationErrors('password');
        $this->change(['current_password' => self::OLD, 'password' => self::OLD, 'password_confirmation' => self::OLD])
            ->assertJsonValidationErrors('password');
    }

    public function test_nguoi_khong_phai_admin_system_khong_goi_duoc(): void
    {
        $body = ['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW];

        // Khách.
        $this->change($body)->assertUnauthorized();

        // Người dùng nhóm đăng nhập guard web.
        $owner = $this->ownerOf($this->makeGroup());
        $this->actingAs($owner)->putJson('/api/system/me/password', $body)->assertUnauthorized();
        $this->assertFalse(Hash::check(self::NEW, $owner->fresh()->password));

        // Quản trị viên đã bị khóa.
        Auth::guard('web')->logout();
        $locked = $this->admin(['email' => 'khoa@example.test', 'status' => 'locked']);
        $this->actingAs($locked, 'system')->putJson('/api/system/me/password', $body)->assertForbidden();
        $this->assertTrue(Hash::check(self::OLD, $locked->fresh()->password));
    }

    public function test_doi_mat_khau_thi_phien_khac_bi_dang_xuat(): void
    {
        $admin = $this->admin();

        // Phiên khác: đăng nhập trước khi đổi, mang dấu mật khẩu cũ.
        $this->actingAs($admin, 'system')->getJson('/api/system/overview')->assertOk();
        $otherSession = session()->all();

        // Phiên này đổi mật khẩu và vẫn dùng tiếp được.
        $this->change(['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW])->assertOk();
        $this->getJson('/api/system/overview')->assertOk();

        // Phiên khác gọi tiếp thì bị đăng xuất.
        $this->flushSession();
        $this->actingAs($admin->fresh(), 'system')->withSession($otherSession)->getJson('/api/system/overview')->assertUnauthorized();
        $this->getJson('/api/system/me')->assertJsonPath('data', null);
    }
}
