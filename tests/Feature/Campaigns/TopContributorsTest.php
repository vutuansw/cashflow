<?php

namespace Tests\Feature\Campaigns;

use App\Enums\GroupRole;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Transactions\GroupStats;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Box "Top đóng góp" trên trang nhóm và QTV ẩn phiếu thu khỏi top. Xem plan/22. */
class TopContributorsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function fund(Group $group): int
    {
        return Category::acrossGroups()->where('group_id', $group->id)->where('name', 'Đóng quỹ năm')->value('id');
    }

    private function campaign(Group $group, ?int $amount, string $slug = 'thu-quy-2026'): Campaign
    {
        return Campaign::forceCreate([
            'group_id' => $group->id, 'category_id' => $this->fund($group), 'name' => "Chiến dịch {$slug}", 'slug' => $slug,
            'transfer_code' => 'TOP'.random_int(100000, 999999), 'amount_per_member' => $amount, 'status' => 'open',
        ]);
    }

    /** Người trong danh sách phải đóng của chiến dịch (danh bạ mới tự vào chiến dịch đang mở). */
    private function person(Campaign $campaign, string $name, array $member = []): Contact
    {
        $contact = Contact::forceCreate(['group_id' => $campaign->group_id, 'full_name' => $name]);
        CampaignMember::updateOrCreate(['campaign_id' => $campaign->id, 'contact_id' => $contact->id], $member);

        return $contact;
    }

    private function pay(Campaign $campaign, ?Contact $contact, int $amount, string $at = '2026-11-05 08:00', array $extra = []): Transaction
    {
        return Transaction::forceCreate($extra + [
            'group_id' => $campaign->group_id, 'type' => 'income', 'category_id' => $campaign->category_id, 'campaign_id' => $campaign->id,
            'contact_id' => $contact?->id, 'amount' => $amount, 'occurred_at' => $at, 'title' => 'Đóng quỹ',
        ]);
    }

    private function top(Group $group): ?array
    {
        return $this->getJson("/api/g/{$group->code}/summary")->assertOk()->json('top_contributors');
    }

    public function test_chien_dich_co_muc_chi_nguoi_vuot_muc_len_top(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $c = $this->campaign($group, 200_000);
        $hoa = $this->person($c, 'Nguyễn Thị Hoa');
        $nam = $this->person($c, 'Bùi Văn Nam');
        $ha = $this->person($c, 'Phạm Văn Hà');
        $mien = $this->person($c, 'Người được miễn', ['exempt' => true]);
        $rieng = $this->person($c, 'Mức riêng', ['expected_amount' => 600_000]);
        $dung = $this->person($c, 'Đóng đúng mức');

        $this->pay($c, $hoa, 300_000, '2026-11-01 08:00');
        $this->pay($c, $hoa, 200_000, '2026-11-03 08:00');
        $this->pay($c, $nam, 400_000, '2026-11-02 08:00');
        $this->pay($c, $ha, 400_000, '2026-11-04 08:00');
        $this->pay($c, $mien, 300_000);
        $this->pay($c, $rieng, 500_000);
        $this->pay($c, $dung, 200_000);
        $this->pay($c, null, 250_000, extra: ['payer_name' => 'Khách vãng lai']);

        $top = $this->top($group);
        $this->assertSame(200_000, $top['campaign']['amount_per_member']);
        // Nam và Hà cùng 400.000: Nam đóng xong sớm hơn đứng trước. Mức riêng 600.000 đóng 500.000 không lên top.
        $this->assertSame(
            [['Nguyễn Thị Hoa', 500_000], ['Bùi Văn Nam', 400_000], ['Phạm Văn Hà', 400_000], ['Người được miễn', 300_000], ['Khách vãng lai', 250_000]],
            array_map(fn ($p) => [$p['name'], $p['amount']], $top['people']),
        );
        $this->assertSame([1, 2, 3, 4, 5], array_column($top['people'], 'rank'));
        // Khách không thấy id phiếu, không biết có phiếu bị ẩn.
        $this->assertArrayNotHasKey('transaction_ids', $top['people'][0]);
        $this->assertArrayNotHasKey('hidden_count', $top);
    }

    public function test_an_phieu_dong_bu_khoi_top_van_tinh_la_da_dong(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $c = $this->campaign($group, 200_000);
        $nam = $this->person($c, 'Bùi Văn Nam');
        $hoa = $this->person($c, 'Nguyễn Thị Hoa');
        $bu = $this->pay($c, $nam, 400_000);
        $this->pay($c, $hoa, 500_000);

        $admin = $this->memberOf($group, GroupRole::Admin);
        $this->actingAs($admin);
        $top = $this->top($group);
        $this->assertSame(['Nguyễn Thị Hoa', 'Bùi Văn Nam'], array_column($top['people'], 'name'));
        $this->assertSame([$bu->id], $top['people'][1]['transaction_ids']);
        $this->assertSame(0, $top['hidden_count']);

        $this->getJson($this->adminUrl($group, "campaigns/{$c->id}/top-transactions?contact_id={$nam->id}"))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $bu->id)->assertJsonPath('0.exclude_from_top', false);

        $this->putJson($this->adminUrl($group, "transactions/{$bu->id}/top"), ['exclude' => true])->assertOk()->assertJsonPath('exclude_from_top', true);

        $top = $this->top($group);
        $this->assertSame(['Nguyễn Thị Hoa'], array_column($top['people'], 'name'));
        $this->assertSame(1, $top['hidden_count']);
        // Tiến độ chiến dịch không đổi: phiếu ẩn vẫn là tiền đã thu.
        $this->getJson("/api/g/{$group->code}/summary")->assertJsonPath('campaigns.0.collected', 900_000);
        $this->getJson($this->adminUrl($group, "campaigns/{$c->id}/top-transactions?hidden=1"))->assertJsonCount(1)->assertJsonPath('0.id', $bu->id);
        $this->getJson($this->adminUrl($group, "transactions/{$bu->id}"))->assertJsonPath('histories.0.changes.0.field', 'Không tính vào Top đóng góp');

        $this->putJson($this->adminUrl($group, "transactions/{$bu->id}/top"), ['exclude' => false])->assertOk();
        $this->assertCount(2, $this->top($group)['people']);
    }

    public function test_chien_dich_tuy_tam_lay_nguoi_tren_trung_binh(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $c = $this->campaign($group, null);
        $a = $this->person($c, 'An');
        $b = $this->person($c, 'Bình');
        $d = $this->person($c, 'Dũng');
        $this->person($c, 'Chưa đóng');
        $this->pay($c, $a, 100_000);
        $this->pay($c, $b, 200_000);
        $this->pay($c, $d, 300_000);

        $top = $this->top($group);
        $this->assertNull($top['campaign']['amount_per_member']);
        $this->assertSame(200_000, $top['campaign']['average']);
        $this->assertSame(['Dũng'], array_column($top['people'], 'name'));
    }

    public function test_khong_ai_du_dieu_kien_thi_khong_co_box(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $c = $this->campaign($group, null);
        $this->pay($c, $this->person($c, 'An'), 200_000);
        $this->pay($c, $this->person($c, 'Bình'), 200_000);
        $this->assertNull($this->top($group));

        $c->update(['status' => 'closed']);
        $fixed = $this->campaign($group, 200_000, 'khac');
        $this->pay($fixed, $this->person($fixed, 'Đúng mức'), 200_000);
        app(GroupStats::class)->forget($group);
        $this->assertNull($this->top($group));
    }

    public function test_quyen_an_phieu_va_nhom_rieng_tu(): void
    {
        $group = $this->makeGroup(['visibility' => 'private']);
        $c = $this->campaign($group, 200_000);
        $t = $this->pay($c, $this->person($c, 'Hoa'), 500_000);

        $this->getJson("/api/g/{$group->code}/summary")->assertForbidden();

        $member = $this->memberOf($group, GroupRole::Member);
        $member->update(['avatar_path' => 'avatars/abc.webp']);
        $this->pay($c, Contact::where('user_id', $member->id)->first(), 300_000);
        $this->actingAs($member);
        $people = $this->top($group)['people'];
        $this->assertSame('Hoa', $people[0]['name']);
        // Người chưa có tài khoản: không ảnh, không trang thành viên. Thành viên có tài khoản: có cả hai.
        $this->assertNull($people[0]['avatar_url']);
        $this->assertNull($people[0]['member_handle']);
        $this->assertSame("/media/u/{$member->id}/avatar/abc", $people[1]['avatar_url']);
        $this->assertNotNull($people[1]['member_handle']);
        $this->putJson($this->adminUrl($group, "transactions/{$t->id}/top"), ['exclude' => true])->assertForbidden();

        $this->actingAs($this->ownerOf($group));
        $expense = Transaction::forceCreate([
            'group_id' => $group->id, 'type' => 'expense', 'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('type', 'expense')->value('id'),
            'amount' => 100_000, 'occurred_at' => '2026-11-05', 'title' => 'Chi',
        ]);
        $this->putJson($this->adminUrl($group, "transactions/{$expense->id}/top"), ['exclude' => true])->assertJsonValidationErrors('exclude');
    }

    public function test_form_phieu_thu_luu_khong_tinh_top(): void
    {
        $group = $this->makeGroup();
        $c = $this->campaign($group, 200_000);
        $hoa = $this->person($c, 'Hoa');
        $this->actingAs($this->ownerOf($group));

        $id = $this->post($this->adminUrl($group, 'transactions'), [
            'type' => 'income', 'category_id' => $c->category_id, 'campaign_id' => $c->id, 'contact_id' => $hoa->id,
            'amount' => '400.000', 'occurred_at' => '2026-11-05 08:00', 'exclude_from_top' => '1',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('exclude_from_top', true)->json('id');

        // Bỏ chiến dịch thì cờ không còn nghĩa, tự về false.
        $this->post($this->adminUrl($group, "transactions/{$id}"), [
            'type' => 'income', 'category_id' => $c->category_id, 'campaign_id' => '', 'contact_id' => $hoa->id,
            'amount' => '400.000', 'occurred_at' => '2026-11-05 08:00', 'exclude_from_top' => '1',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('exclude_from_top', false);
    }
}
