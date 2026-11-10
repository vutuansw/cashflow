<?php

namespace Tests\Feature\Campaigns;

use App\Enums\GroupRole;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Services\Campaigns\CampaignService;
use App\Support\CurrentGroup;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class CampaignsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function fund(Group $group): int
    {
        return Category::acrossGroups()->where('group_id', $group->id)->where('name', 'Đóng quỹ năm')->value('id');
    }

    private function contacts(Group $group, array $names): array
    {
        return array_map(fn ($n) => Contact::forceCreate(['group_id' => $group->id, 'full_name' => $n])->id, $names);
    }

    public function test_tao_chien_dich_mac_dinh_moi_nguoi_dang_tham_gia(): void
    {
        $group = $this->makeGroup(['code' => 'chi-doan-a12']);
        [$an, $binh] = $this->contacts($group, ['Lê Văn An', 'Trần Thị Bình']);
        Contact::forceCreate(['group_id' => $group->id, 'full_name' => 'Đã rời', 'status' => 'left']);

        $c = $this->actingAs($this->ownerOf($group))->postJson($this->adminUrl($group, 'campaigns'), [
            'name' => 'Thu quỹ năm 2026', 'category_id' => $this->fund($group), 'amount_per_member' => 500000, 'starts_on' => '2026-11-01',
        ])->assertCreated()
            ->assertJsonPath('slug', 'thu-quy-nam-2026')
            ->assertJsonPath('transfer_code', 'CHIDOAN26')
            ->assertJsonPath('members_total', 3) // chủ nhóm + 2 người, không có người đã rời
            ->assertJsonPath('expected_total', 1500000)
            ->json();

        // Đóng tiền mặt: Bình đủ, An thiếu.
        $this->postJson($this->adminUrl($group, "campaigns/{$c['id']}/cash"), ['contact_id' => $binh, 'amount' => 500000])->assertOk();
        $r = $this->postJson($this->adminUrl($group, "campaigns/{$c['id']}/cash"), ['contact_id' => $an, 'amount' => 300000])->assertOk()
            ->assertJsonPath('collected', 800000)
            ->assertJsonPath('members_paid', 1)
            ->assertJsonPath('members_partial', 1)
            ->json();
        $an_row = collect($r['people'])->firstWhere('contact_id', $an);
        $this->assertSame('partial', $an_row['status']);
        $this->assertArrayHasKey('phone', $an_row);

        // Mã trùng toàn hệ thống: nhóm khác cũng tên chi-doan-a12... không được dùng lại.
        $other = $this->makeGroup();
        $this->actingAs($this->ownerOf($other))->postJson($this->adminUrl($other, 'campaigns'), [
            'name' => 'Quỹ', 'category_id' => $this->fund($other), 'transfer_code' => 'chidoan26',
        ])->assertJsonValidationErrors('transfer_code');

        // Đã có giao dịch: không đổi mã, không xóa.
        $this->actingAs($this->ownerOf($group))->putJson($this->adminUrl($group, "campaigns/{$c['id']}"), [
            'name' => 'Thu quỹ năm 2026', 'category_id' => $this->fund($group), 'status' => 'open', 'transfer_code' => 'KHAC2026',
        ])->assertJsonValidationErrors('transfer_code');
        $this->deleteJson($this->adminUrl($group, "campaigns/{$c['id']}"))->assertUnprocessable();
    }

    public function test_danh_sach_phai_dong_mien_va_so_tien_rieng(): void
    {
        $group = $this->makeGroup();
        [$an, $binh, $chi] = $this->contacts($group, ['An', 'Bình', 'Chi']);
        $this->actingAs($this->ownerOf($group));

        $c = $this->postJson($this->adminUrl($group, 'campaigns'), [
            'name' => 'Mừng thọ', 'category_id' => $this->fund($group), 'amount_per_member' => 200000,
            'members' => [['contact_id' => $an], ['contact_id' => $binh, 'expected_amount' => 100000], ['contact_id' => $chi, 'exempt' => true]],
        ])->assertCreated()->assertJsonPath('members_total', 2)->assertJsonPath('expected_total', 300000)->json();

        $statuses = collect($c['people'])->pluck('status', 'name');
        $this->assertSame(['An' => 'unpaid', 'Bình' => 'unpaid', 'Chi' => 'exempt'], $statuses->all());

        $this->putJson($this->adminUrl($group, "campaigns/{$c['id']}"), [
            'name' => 'Mừng thọ', 'category_id' => $this->fund($group), 'status' => 'closed', 'members' => [['contact_id' => $an]],
        ])->assertOk()->assertJsonPath('status', 'closed')->assertJsonPath('members_total', 1);
    }

    public function test_trang_dong_quy_cong_khai_co_qr_khong_lo_sdt(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $group->update(['bank_code' => 'MBBank', 'bank_account_no' => '0123456789', 'bank_account_name' => 'NGUYEN VAN AN']);
        app(CurrentGroup::class)->set($group);
        $campaign = app(CampaignService::class)->create($group, $this->ownerOf($group), ['name' => 'Thu quỹ 2026', 'category_id' => $this->fund($group), 'amount_per_member' => 500000]);

        $r = $this->getJson("/api/g/{$group->code}/campaigns/{$campaign->slug}")->assertOk()
            ->assertJsonPath('transfer_code', $campaign->transfer_code)
            ->assertJsonPath('bank.account_no', '0123456789')
            ->json();
        $this->assertStringContainsString('qr.sepay.vn/img?acc=0123456789&bank=MBBank&amount=500000&des='.$campaign->transfer_code, $r['bank']['qr_url']);
        $this->assertArrayNotHasKey('phone', $r['people'][0]);
        $this->assertArrayNotHasKey('qr_download', $r['bank']);
        $this->get("/api/g/{$group->code}/campaigns/{$campaign->slug}/qr.png")->assertNotFound();

        // Đóng chiến dịch: không hiện QR nữa.
        $campaign->update(['status' => 'closed']);
        $this->getJson("/api/g/{$group->code}/campaigns/{$campaign->slug}")->assertJsonPath('bank', null)->assertJsonPath('transfer_code', null);

        // Nhóm private: khách bị chặn.
        $group->update(['visibility' => 'private']);
        $this->getJson("/api/g/{$group->code}/campaigns/{$campaign->slug}")->assertForbidden();
    }

    public function test_chi_chon_nguoi_duoc_mien_ten_khong_trung_nguoi_moi_tu_vao(): void
    {
        $group = $this->makeGroup();
        [$an, $binh] = $this->contacts($group, ['An', 'Bình']);
        $this->actingAs($this->ownerOf($group));

        $c = $this->postJson($this->adminUrl($group, 'campaigns'), [
            'name' => 'Thu quỹ 2026', 'category_id' => $this->fund($group), 'amount_per_member' => 200000, 'exempt_ids' => [$binh],
        ])->assertCreated()->assertJsonPath('members_total', 2)->json(); // chủ nhóm + An; Bình được miễn

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6,12}$/', $c['transfer_code']);
        $this->assertSame('exempt', collect($c['people'])->firstWhere('contact_id', $binh)['status']);

        // Tên trùng trong nhóm bị chặn; nhóm khác đặt trùng tên được.
        $this->postJson($this->adminUrl($group, 'campaigns'), ['name' => 'Thu quỹ 2026', 'category_id' => $this->fund($group)])
            ->assertJsonValidationErrors('name')->assertJsonPath('errors.name.0', 'Nhóm đã có chiến dịch tên này, đặt tên khác nhé.');
        $other = $this->makeGroup();
        $this->actingAs($this->ownerOf($other))->postJson($this->adminUrl($other, 'campaigns'), ['name' => 'Thu quỹ 2026', 'category_id' => $this->fund($other)])->assertCreated();

        // Người thêm vào danh bạ sau tự phải đóng chiến dịch đang mở.
        $this->actingAs($this->ownerOf($group))->postJson($this->adminUrl($group, 'contacts'), ['full_name' => 'Chi mới vào'])->assertCreated();
        $this->getJson($this->adminUrl($group, "campaigns/{$c['id']}"))->assertJsonPath('members_total', 3);

        // Sửa: bỏ miễn Bình, miễn An; tên giữ nguyên không bị báo trùng với chính nó.
        $this->putJson($this->adminUrl($group, "campaigns/{$c['id']}"), [
            'name' => 'Thu quỹ 2026', 'category_id' => $this->fund($group), 'status' => 'open', 'exempt_ids' => [$an],
        ])->assertOk()->assertJsonPath('members_total', 3);
    }

    public function test_thanh_vien_khong_quan_ly_chien_dich(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->memberOf($group, GroupRole::Member))
            ->postJson($this->adminUrl($group, 'campaigns'), ['name' => 'X', 'category_id' => $this->fund($group)])->assertForbidden();
    }

    public function test_sinh_ma_khong_trung(): void
    {
        $group = $this->makeGroup(['code' => 'ab']);
        app(CurrentGroup::class)->set($group);
        Campaign::forceCreate(['group_id' => $group->id, 'category_id' => $this->fund($group), 'name' => 'A', 'slug' => 'a', 'transfer_code' => 'ABQQ'.substr((string) now()->year, 2)]);

        $code = CampaignService::generateCode($group);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6,12}$/', $code);
        $this->assertSame('ABQQ'.substr((string) now()->year, 2).'B', $code); // trùng thì thêm chữ cái

        // Lấy trọn từng cụm, không cắt giữa chữ.
        $yy = substr((string) now()->year, 2);
        $this->assertSame("NHOMDEMO{$yy}", CampaignService::generateCode(new Group(['code' => 'nhomdemo'])));
        $this->assertSame("HOICUU{$yy}", CampaignService::generateCode(new Group(['code' => 'hoi-cuu-sinh-vien'])));
        $this->assertSame("DONGHUON{$yy}", CampaignService::generateCode(new Group(['code' => 'donghuongthaibinh'])));
        $this->assertNotSame('ABQQ'.substr((string) now()->year, 2), $code);
    }
}
