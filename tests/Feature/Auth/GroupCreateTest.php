<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\System\GroupService;
use App\Models\User;
use App\Support\CurrentGroup;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Tạo nhóm ở /nhom/moi, bước riêng sau khi có tài khoản, xem plan/11. */
class GroupCreateTest extends TestCase
{
    use RefreshBothDatabases;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Chi đoàn A12',
            'code' => 'chidoan-a12',
            'visibility' => 'private',
            'opening_balance' => 2500000,
        ];
    }

    public function test_chua_dang_nhap_khong_tao_duoc_nhom(): void
    {
        $this->postJson('/api/groups', $this->payload())->assertUnauthorized();
        $this->assertSame(0, Group::count());
    }

    public function test_tao_nhom_nguoi_tao_la_truong_nhom(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/groups', $this->payload())
            ->assertCreated()
            ->assertJsonPath('code', 'chidoan-a12');

        $group = Group::where('code', 'chidoan-a12')->firstOrFail();
        $this->assertSame($user->id, $group->owner_id);
        $this->assertSame('private', $group->visibility);
        $this->assertSame(2500000, $group->opening_balance);
        $this->assertSame('owner', GroupMember::where('group_id', $group->id)->value('role')->value);
        $this->assertSame('pending_review', GroupService::where('group_id', $group->id)->value('review_status'));

        app(CurrentGroup::class)->set($group);
        $this->assertSame(['Đóng quỹ năm'], Category::where('type', 'income')->pluck('name')->all());
        $this->assertSame(['Liên hoan', 'Đám hiếu', 'Đám hỉ', 'Thăm ốm', 'Thăm đẻ'], Category::where('type', 'expense')->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame($user->id, Contact::sole()->user_id);

        $this->getJson('/api/me')->assertJsonPath('data.groups.0.role', 'owner');
    }

    /** Lỗi trên server 11/11/2026: gõ tên nhóm gọi /group-code nhiều lần, làm lượt tạo nhóm đầu tiên bị 429. */
    public function test_kiem_tra_ma_nhom_nhieu_lan_khong_lam_het_luot_tao_nhom(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 15; $i++) {
            $this->getJson('/api/group-code?code=chi-doan-a12-'.$i)->assertOk();
        }

        $this->postJson('/api/groups', $this->payload())->assertCreated();
    }

    public function test_dang_ky_tai_khoan_roi_tao_nhom(): void
    {
        $this->postJson('/api/register', ['name' => 'Nguyễn Văn An', 'phone' => '0988777222', 'password' => 'matkhau123'])->assertCreated();
        $this->postJson('/api/groups', $this->payload())->assertCreated();

        $this->getJson('/api/me')->assertJsonCount(1, 'data.groups');
    }

    public function test_ma_nhom_trung_hoac_danh_rieng_bi_tu_choi(): void
    {
        Group::factory()->create(['code' => 'chidoan-a12']);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/groups', $this->payload())->assertJsonValidationErrors('code');
        $this->postJson('/api/groups', $this->payload(['code' => 'system']))->assertJsonValidationErrors('code');
        $this->postJson('/api/groups', $this->payload(['code' => 'Chi Doan']))->assertJsonValidationErrors('code');
    }

    public function test_gioi_han_so_nhom_moi_tai_khoan(): void
    {
        config(['cashflow.limits.groups_per_user' => 2]);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/groups', ['name' => 'Nhóm 1', 'code' => 'nhom-mot', 'visibility' => 'public'])->assertCreated();
        $this->postJson('/api/groups', ['name' => 'Nhóm 2', 'code' => 'nhom-hai', 'visibility' => 'public'])->assertCreated();
        $this->postJson('/api/groups', ['name' => 'Nhóm 3', 'code' => 'nhom-ba', 'visibility' => 'public'])->assertJsonValidationErrors('name');

        $this->getJson('/api/me')->assertJsonCount(2, 'data.groups');
    }

    public function test_kiem_tra_ma_nhom_va_goi_y(): void
    {
        Group::factory()->create(['code' => 'chi-doan-a12']);

        $this->getJson('/api/group-code?'.http_build_query(['code' => 'chi-doan-a12', 'name' => 'Chi đoàn A12']))
            ->assertOk()
            ->assertJson(['available' => false, 'suggestion' => 'chi-doan-a12-2']);

        $this->getJson('/api/group-code?code=nhomabc')->assertJson(['available' => true]);
    }
}
