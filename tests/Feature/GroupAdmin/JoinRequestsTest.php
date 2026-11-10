<?php

namespace Tests\Feature\GroupAdmin;

use App\Enums\GroupRole;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\GroupMember;
use App\Models\System\GroupService;
use App\Models\User;
use App\Services\GroupServiceStatus;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Link đăng ký vào nhóm /{ma-nhom}/register và QTV duyệt trong Danh bạ, xem plan/13. */
class JoinRequestsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private array $form = ['name' => 'Trần Thị Mai', 'phone' => '0933000111', 'password' => 'matkhau123'];

    private function registerUrl(Group $group): string
    {
        return "/api/g/{$group->code}/join/register";
    }

    private function openCampaign(Group $group): Campaign
    {
        $category = Category::acrossGroups()->where('group_id', $group->id)->where('type', 'income')->firstOrFail();

        return Campaign::forceCreate([
            'group_id' => $group->id, 'category_id' => $category->id, 'name' => 'Thu quỹ', 'slug' => 'thu-quy',
            'transfer_code' => 'QT'.random_int(1000, 9999), 'status' => 'open',
        ]);
    }

    private function pendingRequest(Group $group, array $user = []): GroupJoinRequest
    {
        $user = User::factory()->create($user + ['phone' => '0933000111']);

        return GroupJoinRequest::create(['group_id' => $group->id, 'user_id' => $user->id]);
    }

    public function test_dang_ky_qua_link_tao_tai_khoan_dang_nhap_va_cho_duyet(): void
    {
        $group = $this->makeGroup(['visibility' => 'private']);
        $campaign = $this->openCampaign($group);

        $this->postJson($this->registerUrl($group), $this->form)
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('me.groups', [])
            ->assertJsonPath('me.pending_groups.0.code', $group->code);

        $user = User::wherePhone('0933000111')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('group_join_requests', ['group_id' => $group->id, 'user_id' => $user->id, 'status' => 'pending']);

        // Chưa duyệt: không thành viên, không danh bạ, không chiến dịch, không xem được nhóm riêng tư.
        $this->assertFalse(GroupMember::where('user_id', $user->id)->exists());
        $this->assertFalse(Contact::acrossGroups()->where('group_id', $group->id)->wherePhone('0933000111')->exists());
        $this->assertSame(0, CampaignMember::where('campaign_id', $campaign->id)->count());
        $this->getJson("/api/g/{$group->code}/timeline")->assertForbidden();
        $this->getJson("/api/g/{$group->code}")
            ->assertJsonPath('viewer.join_pending', true)
            ->assertJsonPath('viewer.is_member', false);
    }

    public function test_link_tat_hoac_nhom_khoa_thi_khong_dang_ky_duoc(): void
    {
        $group = $this->makeGroup();
        $group->update(['join_link_enabled' => false]);
        $this->getJson("/api/g/{$group->code}/join")->assertOk()->assertJsonPath('enabled', false);
        $this->postJson($this->registerUrl($group), $this->form)->assertForbidden();
        $this->assertFalse(User::wherePhone('0933000111')->exists());

        $locked = $this->makeGroup();
        GroupService::updateOrCreate(['group_id' => $locked->id], ['service_status' => 'locked', 'lock_reason' => 'Vi phạm']);
        app(GroupServiceStatus::class)->forget($locked->id);
        $this->postJson($this->registerUrl($locked), $this->form)->assertStatus(423);
    }

    public function test_sdt_da_co_tai_khoan_thi_bao_loi_roi_dang_nhap_xin_vao(): void
    {
        $group = $this->makeGroup();
        $existing = User::factory()->create(['phone' => '0933000111']);

        $this->postJson($this->registerUrl($group), $this->form)->assertJsonValidationErrors('phone');
        $this->assertFalse(GroupJoinRequest::exists());

        $this->actingAs($existing);
        $this->getJson("/api/g/{$group->code}/join")->assertJsonPath('status', 'none');
        $this->postJson("/api/g/{$group->code}/join")->assertCreated();
        $this->postJson("/api/g/{$group->code}/join")->assertCreated();

        $this->assertSame(1, GroupJoinRequest::where('user_id', $existing->id)->count());
        $this->getJson("/api/g/{$group->code}/join")->assertJsonPath('status', 'pending');
    }

    public function test_thanh_vien_roi_xin_vao_bi_bao_da_o_trong_nhom(): void
    {
        $group = $this->makeGroup();
        $member = $this->memberOf($group);

        $this->actingAs($member)->getJson("/api/g/{$group->code}/join")->assertJsonPath('status', 'member');
        $this->postJson("/api/g/{$group->code}/join")->assertJsonValidationErrors('group');
    }

    public function test_qtv_thay_danh_sach_cho_duyet_va_link(): void
    {
        $group = $this->makeGroup();
        Contact::forceCreate(['group_id' => $group->id, 'full_name' => 'Mai (danh bạ)', 'phone' => '0933000111']);
        $request = $this->pendingRequest($group, ['name' => 'Trần Thị Mai']);

        $this->actingAs($this->ownerOf($group))
            ->getJson($this->adminUrl($group, 'join-requests'))
            ->assertOk()
            ->assertJsonPath('requests.0.id', $request->id)
            ->assertJsonPath('requests.0.name', 'Trần Thị Mai')
            ->assertJsonPath('requests.0.contact.full_name', 'Mai (danh bạ)')
            ->assertJsonPath('link.enabled', true)
            ->assertJsonPath('link.url', url("/{$group->code}/register"));

        $this->getJson($this->adminUrl($group, 'sepay/count'))->assertJsonPath('joins', 1);
    }

    public function test_duyet_tao_danh_ba_moi_va_vao_chien_dich_dang_mo(): void
    {
        $group = $this->makeGroup();
        $campaign = $this->openCampaign($group);
        $request = $this->pendingRequest($group, ['name' => 'Trần Thị Mai']);
        $owner = $this->ownerOf($group);

        $this->actingAs($owner)->postJson($this->adminUrl($group, "join-requests/{$request->id}/approve"))->assertNoContent();

        $contact = Contact::acrossGroups()->where('group_id', $group->id)->wherePhone('0933000111')->firstOrFail();
        $this->assertSame('Trần Thị Mai', $contact->full_name);
        $this->assertSame($request->user_id, $contact->user_id);
        $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $request->user_id, 'role' => 'member', 'status' => 'active', 'contact_id' => $contact->id]);
        $this->assertTrue(CampaignMember::where('campaign_id', $campaign->id)->where('contact_id', $contact->id)->exists());
        $this->assertDatabaseHas('group_join_requests', ['id' => $request->id, 'status' => 'approved', 'decided_by' => $owner->id, 'contact_id' => $contact->id]);

        // Duyệt lại lần nữa thì báo đã xử lý.
        $this->postJson($this->adminUrl($group, "join-requests/{$request->id}/approve"))->assertJsonValidationErrors('request');
    }

    public function test_duyet_gan_vao_dong_danh_ba_co_san_va_kich_hoat_lai_thanh_vien_cu(): void
    {
        $group = $this->makeGroup();
        $request = $this->pendingRequest($group);
        $contact = Contact::forceCreate(['group_id' => $group->id, 'full_name' => 'Mai cũ', 'phone' => '0933000111', 'status' => 'left']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $request->user_id, 'role' => GroupRole::Member, 'contact_id' => $contact->id, 'status' => 'removed']);

        $this->actingAs($this->ownerOf($group))->postJson($this->adminUrl($group, "join-requests/{$request->id}/approve"))->assertNoContent();

        $this->assertSame(1, Contact::acrossGroups()->where('group_id', $group->id)->wherePhone('0933000111')->count());
        $this->assertSame('active', $contact->fresh()->status);
        $this->assertSame($request->user_id, $contact->fresh()->user_id);
        $this->assertSame('active', GroupMember::where('user_id', $request->user_id)->value('status'));
    }

    public function test_duyet_tat_ca_chi_duyet_nhung_nguoi_dang_thay(): void
    {
        $group = $this->makeGroup();
        $a = $this->pendingRequest($group, ['phone' => '0933000111']);
        $b = $this->pendingRequest($group, ['phone' => '0933000222']);
        $late = $this->pendingRequest($group, ['phone' => '0933000333']);
        $other = $this->pendingRequest($this->makeGroup(), ['phone' => '0933000444']);

        $this->actingAs($this->ownerOf($group))
            ->postJson($this->adminUrl($group, 'join-requests/approve'), ['ids' => [$a->id, $b->id, $other->id]])
            ->assertOk()
            ->assertJsonPath('approved', 2)
            ->assertJsonPath('failed', []);

        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('approved', $b->fresh()->status);
        $this->assertSame('pending', $late->fresh()->status);
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertSame(2, GroupMember::where('group_id', $group->id)->where('role', 'member')->count());
    }

    public function test_tu_choi_khong_tao_gi_va_xin_lai_duoc(): void
    {
        $group = $this->makeGroup();
        $request = $this->pendingRequest($group);

        $this->actingAs($this->ownerOf($group))->postJson($this->adminUrl($group, "join-requests/{$request->id}/reject"))->assertNoContent();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse(GroupMember::where('user_id', $request->user_id)->exists());
        $this->assertFalse(Contact::acrossGroups()->where('group_id', $group->id)->wherePhone('0933000111')->exists());

        $this->actingAs($request->user)->postJson("/api/g/{$group->code}/join")->assertCreated();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_thanh_vien_thuong_va_nhom_khac_khong_duyet_duoc(): void
    {
        $group = $this->makeGroup();
        $request = $this->pendingRequest($group);

        $this->actingAs($this->memberOf($group))
            ->postJson($this->adminUrl($group, "join-requests/{$request->id}/approve"))
            ->assertForbidden();

        $other = $this->makeGroup();
        $this->actingAs($this->ownerOf($other))
            ->postJson($this->adminUrl($other, "join-requests/{$request->id}/approve"))
            ->assertNotFound();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_bat_tat_link_trong_cai_dat(): void
    {
        $group = $this->makeGroup();

        $this->actingAs($this->ownerOf($group))
            ->putJson($this->adminUrl($group, 'settings/join-link'), ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('join_link.enabled', false);

        $this->assertFalse($group->fresh()->join_link_enabled);
    }
}
