<?php

namespace Tests\Feature\System;

use App\Mail\GroupStatusMail;
use App\Models\Category;
use App\Models\Group;
use App\Models\System\Admin;
use App\Models\System\AdminAuditLog;
use App\Models\System\GroupService;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CurrentGroup;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class SystemTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function admin(): Admin
    {
        return Admin::query()->create(['name' => 'HIPOTECH', 'email' => 'admin@example.test', 'password' => 'matkhau-dai-123', 'status' => 'active']);
    }

    public function test_dang_nhap_rieng_khong_cheo_voi_nguoi_dung_nhom(): void
    {
        $this->admin();
        $owner = $this->ownerOf($this->makeGroup());

        // Người dùng nhóm đăng nhập không vào được /system.
        $this->actingAs($owner)->getJson('/api/system/overview')->assertUnauthorized();

        $this->postJson('/api/system/login', ['email' => 'admin@example.test', 'password' => 'sai'])->assertJsonValidationErrors('email');
        $r = $this->postJson('/api/system/login', ['email' => 'ADMIN@example.test', 'password' => 'matkhau-dai-123'])->assertOk();
        $this->assertSame('admin@example.test', $r->json('data.email'));
        // Tên cookie session riêng: test dùng session array nên không có cookie, kiểm trên trình duyệt.
        $this->assertNull(collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'XSRF-TOKEN'));

        // Quản trị /system không vào được trang quản trị nhóm.
        $group = Group::first();
        auth()->guard('web')->logout();
        $this->actingAs(Admin::first(), 'system');
        $this->app['auth']->shouldUse('web'); // như request thật: guard mặc định là web
        $this->getJson($this->adminUrl($group, 'settings'))->assertUnauthorized();
    }

    public function test_khoa_nhom_chan_trang_va_webhook_va_bao_email(): void
    {
        Mail::fake();
        $group = $this->makeGroup(['code' => 'nhom-khoa', 'visibility' => 'public']);
        $group->owner->update(['email' => 'truongnhom@example.test']);
        $group->forceFill(['sepay_api_key' => 'k'])->save();
        $this->actingAs($this->admin(), 'system');

        $this->postJson("/api/system/groups/{$group->id}/lock", ['locked' => true])->assertJsonValidationErrors('reason');
        $this->postJson("/api/system/groups/{$group->id}/lock", ['locked' => true, 'reason' => 'Nội dung vi phạm'])->assertOk()
            ->assertJsonPath('service_status', 'locked')->assertJsonPath('lock_reason', 'Nội dung vi phạm');

        $this->app['auth']->shouldUse('web');
        $this->getJson('/api/g/nhom-khoa')->assertStatus(423);
        $this->postJson('/api/webhooks/sepay/nhom-khoa', ['id' => 1], ['Authorization' => 'Apikey k'])->assertStatus(423);
        Mail::assertQueued(GroupStatusMail::class, fn ($m) => $m->hasTo('truongnhom@example.test') && $m->locked);

        $this->actingAs(Admin::first(), 'system')->postJson("/api/system/groups/{$group->id}/lock", ['locked' => false])->assertOk()->assertJsonPath('service_status', 'active');
        $this->app['auth']->shouldUse('web');
        $this->getJson('/api/g/nhom-khoa')->assertOk();
        $this->assertSame(['lock', 'unlock'], AdminAuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_danh_sach_loc_tim_theo_sdt_truong_nhom_duyet(): void
    {
        $a = $this->makeGroup(['name' => 'Chi đoàn A12'], User::factory()->create(['phone' => '0988000111']));
        $b = $this->makeGroup(['name' => 'Hội đồng hương']);
        $this->actingAs($this->admin(), 'system');

        $this->getJson('/api/system/groups?'.http_build_query(['q' => '0988 000 111']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id);
        $this->getJson('/api/system/groups?'.http_build_query(['q' => 'đồng hương']))->assertJsonPath('data.0.id', $b->id);

        $this->postJson("/api/system/groups/{$a->id}/review", ['status' => 'approved'])->assertOk()->assertJsonPath('review_status', 'approved');
        $this->getJson('/api/system/groups?review=pending_review')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $b->id);
        $this->getJson('/api/system/overview')->assertJsonPath('groups.total', 2)->assertJsonPath('groups.pending_review', 1);

        $detail = $this->getJson("/api/system/groups/{$a->id}")->assertOk()->json();
        $this->assertSame('0988000111', $detail['owner']['phone']);
        $this->assertArrayHasKey('storage_mb', $detail);
    }

    public function test_sua_ma_nhom_han_muc_ai_va_xoa_khoi_phuc(): void
    {
        $group = $this->makeGroup(['code' => 'ma-cu']);
        $other = $this->makeGroup(['code' => 'ma-khac']);
        $this->actingAs($this->admin(), 'system');

        $this->putJson("/api/system/groups/{$group->id}", ['name' => 'Tên mới', 'code' => 'ma-khac', 'visibility' => 'public'])->assertJsonValidationErrors('code');
        $this->putJson("/api/system/groups/{$group->id}", ['name' => 'Tên mới', 'code' => 'ma-moi', 'visibility' => 'private', 'ai_monthly_quota' => 50])->assertOk()
            ->assertJsonPath('code', 'ma-moi')->assertJsonPath('ai_monthly_quota', 50);
        $this->assertSame(50, GroupService::query()->where('group_id', $group->id)->value('ai_monthly_quota'));
        $this->assertSame('change_code', AdminAuditLog::query()->latest('id')->value('action'));

        $this->deleteJson("/api/system/groups/{$group->id}", ['confirm_code' => 'ma-cu'])->assertJsonValidationErrors('confirm_code');
        $this->deleteJson("/api/system/groups/{$group->id}", ['confirm_code' => 'ma-moi'])->assertNoContent();
        $this->getJson('/api/system/groups?deleted=1')->assertJsonPath('data.0.id', $group->id);
        $this->app['auth']->shouldUse('web');
        $this->getJson('/api/g/ma-moi')->assertNotFound();
        $this->actingAs(Admin::first(), 'system')->postJson("/api/system/groups/{$group->id}/restore")->assertOk();
        $this->app['auth']->shouldUse('web');
        $this->getJson('/api/g/ma-moi')->assertOk();
    }

    public function test_tao_nhom_ho_khach(): void
    {
        $this->actingAs($this->admin(), 'system');

        $r = $this->postJson('/api/system/groups', [
            'owner_name' => 'Nguyễn Văn An', 'owner_phone' => '0912 000 222', 'name' => 'Hội cựu sinh viên', 'code' => 'cuu-sinh-vien', 'visibility' => 'private',
        ])->assertCreated()->assertJsonPath('owner_existing', false)->assertJsonPath('group.review_status', 'approved')->json();

        $this->assertSame(8, strlen($r['temp_password']));
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/login', ['phone' => '0912000222', 'password' => $r['temp_password']])->assertOk()->assertJsonPath('data.must_change_password', true);

        // SĐT đã có tài khoản thì gắn tài khoản đó, không tạo mật khẩu mới.
        auth()->guard('web')->logout();
        $this->actingAs(Admin::first(), 'system');
        $this->postJson('/api/system/groups', [
            'owner_name' => 'An', 'owner_phone' => '0912000222', 'name' => 'Nhóm thứ hai', 'code' => 'nhom-thu-hai', 'visibility' => 'public',
        ])->assertCreated()->assertJsonPath('owner_existing', true)->assertJsonPath('temp_password', null);
    }

    public function test_quan_tri_vien_bi_khoa_mat_quyen_ngay(): void
    {
        $me = $this->admin();
        $this->actingAs($me, 'system');
        $new = $this->postJson('/api/system/admins', ['name' => 'Thủ quỹ', 'email' => 'tq@example.test'])->assertCreated()->json();
        $this->assertSame(16, strlen($new['temp_password']));

        $this->putJson("/api/system/admins/{$new['admin']['id']}", ['status' => 'locked'])->assertOk();
        $this->actingAs(Admin::find($new['admin']['id']), 'system')->getJson('/api/system/overview')->assertForbidden();
    }

    public function test_lenh_tao_admin_va_xoa_han_sau_30_ngay(): void
    {
        $this->artisan('cashflow:admin-create', ['email' => 'boss@example.test', '--password' => 'mat-khau-123'])->assertSuccessful();
        $this->assertTrue(Admin::query()->where('email', 'boss@example.test')->exists());

        Storage::fake('media');
        $group = $this->makeGroup();
        app(CurrentGroup::class)->set($group);
        Transaction::forceCreate(['group_id' => $group->id, 'type' => 'expense', 'category_id' => Category::query()->value('id'), 'amount' => 1, 'occurred_at' => now(), 'title' => 'x']);
        Storage::disk('media')->put("groups/{$group->id}/photos/a.webp", 'x');
        $group->delete();
        Group::withTrashed()->whereKey($group->id)->update(['deleted_at' => now()->subDays(31)]);

        $this->artisan('cashflow:purge')->assertSuccessful();

        $this->assertNull(Group::withTrashed()->find($group->id));
        $this->assertSame(0, Transaction::acrossGroups()->withTrashed()->where('group_id', $group->id)->count());
        Storage::disk('media')->assertMissing("groups/{$group->id}/photos/a.webp");
        $this->assertFalse(GroupService::query()->where('group_id', $group->id)->exists());
    }
}
