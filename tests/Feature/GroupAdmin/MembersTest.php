<?php

namespace Tests\Feature\GroupAdmin;

use App\Enums\GroupRole;
use App\Models\Contact;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\Groups\JoinRequests;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class MembersTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function contactIn($group, array $attrs = []): Contact
    {
        return Contact::forceCreate($attrs + ['group_id' => $group->id, 'full_name' => 'Lê Văn Bình', 'phone' => '0912000111']);
    }

    public function test_cap_tai_khoan_moi_roi_dang_nhap_duoc(): void
    {
        $group = $this->makeGroup(['code' => 'chidoan-a12']);
        $contact = $this->contactIn($group);

        $response = $this->actingAs($this->ownerOf($group))
            ->postJson($this->adminUrl($group, 'members'), ['contact_id' => $contact->id, 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('member.role', 'admin');

        $password = $response->json('temp_password');
        $this->assertSame(8, strlen($password));
        $this->assertStringContainsString($password, $response->json('share_message'));
        $this->assertStringContainsString('0912000111', $response->json('share_message'));
        $this->assertStringContainsString('/login', $response->json('share_message'));

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/logout');
        $this->postJson('/api/login', ['phone' => '0912000111', 'password' => $password])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.groups.0.code', 'chidoan-a12')
            ->assertJsonPath('data.groups.0.role', 'admin');
    }

    public function test_sdt_da_co_tai_khoan_o_nhom_khac_thi_chi_gan_vao(): void
    {
        $existing = User::factory()->create(['phone' => '0912000111', 'password' => 'cuacuatoi']);
        $group = $this->makeGroup();
        $contact = $this->contactIn($group);

        $this->actingAs($this->ownerOf($group))
            ->postJson($this->adminUrl($group, 'members'), ['contact_id' => $contact->id, 'role' => 'member'])
            ->assertCreated()
            ->assertJsonPath('temp_password', null);

        $this->assertTrue(Hash::check('cuacuatoi', $existing->fresh()->password));
        $this->assertSame($existing->id, $contact->fresh()->user_id);

        $this->postJson($this->adminUrl($group, 'members'), ['contact_id' => $contact->id, 'role' => 'member'])
            ->assertJsonValidationErrors('contact_id');
    }

    public function test_quan_tri_vien_khong_cap_hay_doi_quyen_admin(): void
    {
        $group = $this->makeGroup();
        $admin = $this->memberOf($group, GroupRole::Admin);
        $otherAdmin = $this->memberOf($group, GroupRole::Admin);
        $member = $this->memberOf($group, GroupRole::Member);
        $contact = $this->contactIn($group);

        $this->actingAs($admin);
        $this->postJson($this->adminUrl($group, 'members'), ['contact_id' => $contact->id, 'role' => 'admin'])->assertForbidden();
        $this->postJson($this->adminUrl($group, 'members'), ['contact_id' => $contact->id, 'role' => 'member'])->assertCreated();

        $memberRow = GroupMember::where('user_id', $member->id)->first();
        $otherAdminRow = GroupMember::where('user_id', $otherAdmin->id)->first();
        $ownerRow = GroupMember::where('user_id', $group->owner_id)->first();

        $this->putJson($this->adminUrl($group, "members/{$memberRow->id}"), ['role' => 'admin'])->assertForbidden();
        $this->postJson($this->adminUrl($group, "members/{$otherAdminRow->id}/reset-password"))->assertForbidden();
        $this->deleteJson($this->adminUrl($group, "members/{$ownerRow->id}"))->assertForbidden();
        $this->postJson($this->adminUrl($group, "members/{$memberRow->id}/reset-password"))->assertOk()->assertJsonStructure(['temp_password']);
        $this->deleteJson($this->adminUrl($group, "members/{$memberRow->id}"))->assertNoContent();

        $this->assertSame('removed', $memberRow->fresh()->status);
    }

    public function test_truong_nhom_phong_ha_va_chuyen_truong_nhom(): void
    {
        $group = $this->makeGroup();
        $owner = $this->ownerOf($group);
        $member = $this->memberOf($group, GroupRole::Member);
        $row = GroupMember::where('user_id', $member->id)->first();

        $this->actingAs($owner);
        $this->putJson($this->adminUrl($group, "members/{$row->id}"), ['role' => 'admin'])->assertOk()->assertJsonPath('role', 'admin');
        $this->postJson($this->adminUrl($group, 'transfer-owner'), ['user_id' => $member->id])->assertOk()->assertJsonPath('my_role', 'admin');

        $this->assertSame($member->id, $group->fresh()->owner_id);
        $this->assertSame('owner', $row->fresh()->role->value);

        // Đã thành quản trị viên thì không xóa nhóm được nữa.
        $this->deleteJson($this->adminUrl($group), ['confirm_code' => $group->code])->assertForbidden();
    }

    public function test_khong_dung_id_thanh_vien_cua_nhom_khac(): void
    {
        $a = $this->makeGroup();
        $b = $this->makeGroup();
        $memberB = $this->memberOf($b);
        $rowB = GroupMember::where('user_id', $memberB->id)->first();

        $this->actingAs($this->ownerOf($a))
            ->postJson($this->adminUrl($a, "members/{$rowB->id}/reset-password"))
            ->assertNotFound();
    }

    // --- Thêm thành viên bằng SĐT (plan/30 mục 2) ---

    public function test_them_bang_sdt_nguoi_da_co_tai_khoan_giu_mat_khau(): void
    {
        $existing = User::factory()->create(['name' => 'Phạm Thu Hà', 'phone' => '0912000222', 'password' => 'cuacuatoi']);
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $this->getJson($this->adminUrl($group, 'members/lookup?phone=0912 000 222'))
            ->assertOk()
            ->assertJsonPath('status', 'account')
            ->assertJsonPath('name', 'Phạm Thu Hà')
            ->assertJsonPath('contact', null);

        $this->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '+84912000222'])
            ->assertCreated()
            ->assertJsonPath('member.role', 'member')
            ->assertJsonPath('member.name', 'Phạm Thu Hà')
            ->assertJsonPath('temp_password', null);

        $this->assertTrue(Hash::check('cuacuatoi', $existing->fresh()->password));
        $contact = Contact::acrossGroups()->where('group_id', $group->id)->where('user_id', $existing->id)->firstOrFail();
        $this->assertSame('Phạm Thu Hà', $contact->full_name);

        $this->getJson($this->adminUrl($group, 'members/lookup?phone=0912000222'))->assertJsonPath('status', 'member');
        $this->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000222'])
            ->assertJsonValidationErrors('phone');
    }

    public function test_them_bang_sdt_nguoi_chua_co_tai_khoan_ra_mat_khau_tam(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $this->getJson($this->adminUrl($group, 'members/lookup?phone=0912000333'))
            ->assertOk()->assertJsonPath('status', 'none')->assertJsonPath('name', null);

        $this->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000333'])
            ->assertJsonValidationErrors('full_name');

        $password = $this->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000333', 'full_name' => 'Đỗ Minh Khoa'])
            ->assertCreated()
            ->assertJsonPath('member.name', 'Đỗ Minh Khoa')
            ->json('temp_password');

        $this->assertNotNull($password);
        $this->assertTrue(User::wherePhone('0912000333')->firstOrFail()->must_change_password);
    }

    public function test_them_bang_sdt_gan_vao_dong_danh_ba_san_co(): void
    {
        User::factory()->create(['name' => 'Tên Tài Khoản', 'phone' => '0912000111']);
        $group = $this->makeGroup();
        $contact = $this->contactIn($group, ['status' => 'left']);
        $this->actingAs($this->ownerOf($group));

        $this->getJson($this->adminUrl($group, 'members/lookup?phone=0912000111'))
            ->assertJsonPath('status', 'account')
            ->assertJsonPath('contact.id', $contact->id)
            ->assertJsonPath('contact.full_name', 'Lê Văn Bình');

        $this->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000111'])->assertCreated();

        $this->assertSame(1, Contact::acrossGroups()->where('group_id', $group->id)->wherePhone('0912000111')->count());
        $this->assertSame('active', $contact->fresh()->status);
        $this->assertNotNull($contact->fresh()->user_id);
    }

    public function test_them_bang_sdt_chua_co_tai_khoan_nhung_co_trong_danh_ba_lay_ten_danh_ba(): void
    {
        $group = $this->makeGroup();
        $this->contactIn($group);
        $this->actingAs($this->ownerOf($group));

        $this->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000111'])
            ->assertCreated()
            ->assertJsonPath('member.name', 'Lê Văn Bình');
        $this->assertSame(1, Contact::acrossGroups()->where('group_id', $group->id)->wherePhone('0912000111')->count());
    }

    public function test_them_bang_sdt_duyet_luon_yeu_cau_dang_cho(): void
    {
        $user = User::factory()->create(['phone' => '0912000444']);
        $group = $this->makeGroup();
        $owner = $this->ownerOf($group);
        $request = app(JoinRequests::class)->request($group, $user);

        $this->actingAs($owner)->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000444'])->assertCreated();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($owner->id, $request->decided_by);
        $this->getJson($this->adminUrl($group, 'join-requests'))->assertJsonCount(0, 'requests');
    }

    public function test_them_bang_sdt_chi_qtv_cua_nhom(): void
    {
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        User::factory()->create(['phone' => '0912000555']);

        $this->actingAs($this->memberOf($group))
            ->getJson($this->adminUrl($group, 'members/lookup?phone=0912000555'))->assertForbidden();
        $this->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000555'])->assertForbidden();

        $this->actingAs($this->ownerOf($other))
            ->postJson($this->adminUrl($group, 'members/by-phone'), ['phone' => '0912000555'])->assertForbidden();

        $this->actingAs($this->ownerOf($group))
            ->getJson($this->adminUrl($group, 'members/lookup?phone=123'))->assertJsonValidationErrors('phone');
    }
}
