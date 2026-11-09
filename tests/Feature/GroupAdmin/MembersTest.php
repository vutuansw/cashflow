<?php

namespace Tests\Feature\GroupAdmin;

use App\Enums\GroupRole;
use App\Models\Contact;
use App\Models\GroupMember;
use App\Models\User;
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
}
