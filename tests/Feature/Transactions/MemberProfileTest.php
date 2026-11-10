<?php

namespace Tests\Feature\Transactions;

use App\Enums\GroupRole;
use App\Models\Category;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Transaction;
use App\Models\User;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Trang thành viên và link tên → trang thành viên. Xem plan/08 mục 1, 5, 6. */
class MemberProfileTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function memberRow(Group $group, User $user): GroupMember
    {
        return GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->first();
    }

    private function makeTx(Group $group, array $attributes): Transaction
    {
        $type = $attributes['type'] ?? 'income';

        return Transaction::forceCreate($attributes + [
            'group_id' => $group->id,
            'type' => $type,
            'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('type', $type)->value('id'),
            'amount' => 500_000,
            'occurred_at' => now()->subDay(),
            'title' => 'Đóng quỹ',
        ]);
    }

    public function test_trang_thanh_vien_co_tom_tat_va_timeline_rieng(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $lan = $this->memberOf($group, GroupRole::Member, ['name' => 'Lan']);
        $lan->update(['bio' => 'Thành viên chi đoàn']);
        $other = $this->memberOf($group);
        $m = $this->memberRow($group, $lan);

        $campaign = $this->actingAs($this->ownerOf($group))->postJson($this->adminUrl($group, 'campaigns'), [
            'name' => 'Thu quỹ năm', 'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('type', 'income')->value('id'),
            'amount_per_member' => 500000,
        ])->assertCreated()->json('id');
        auth()->logout();

        $this->makeTx($group, ['contact_id' => $m->contact_id, 'campaign_id' => $campaign, 'title' => 'Lan đóng quỹ']);
        $this->makeTx($group, ['contact_id' => $m->contact_id, 'type' => 'expense', 'title' => 'Thăm ốm Lan']);
        $this->makeTx($group, ['contact_id' => $m->contact_id, 'title' => 'Phiếu ẩn', 'show_on_timeline' => false]);
        $this->makeTx($group, ['contact_id' => $this->memberRow($group, $other)->contact_id, 'title' => 'Người khác']);

        $base = "/api/g/{$group->code}/members/{$m->id}";
        $this->getJson($base)->assertOk()
            ->assertJsonPath('member.name', 'Lan')
            ->assertJsonPath('member.bio', 'Thành viên chi đoàn')
            ->assertJsonPath('member.role_label', GroupRole::Member->label())
            ->assertJsonPath('member.is_me', false)
            ->assertJsonPath('stats.campaigns_total', 1)
            ->assertJsonPath('stats.campaigns_paid', 1)
            ->assertJsonPath('stats.open_campaigns.0.status', 'paid')
            ->assertJsonMissingPath('member.phone');
        $this->assertStringNotContainsString($lan->phone, $this->getJson($base)->getContent());

        $titles = collect($this->getJson("{$base}/timeline")->assertOk()->json('data'))->pluck('title')->all();
        $this->assertSame(['Lan đóng quỹ', 'Thăm ốm Lan'], collect($titles)->sort()->values()->all());

        $this->actingAs($lan)->getJson($base)->assertJsonPath('member.is_me', true);
    }

    public function test_quyen_xem_trang_thanh_vien(): void
    {
        $group = $this->makeGroup(['visibility' => 'private']);
        $lan = $this->memberOf($group);
        $m = $this->memberRow($group, $lan);
        $url = "/api/g/{$group->code}/members/{$m->id}";

        $this->getJson($url)->assertForbidden();
        $this->actingAs($this->ownerOf($this->makeGroup()))->getJson($url)->assertForbidden();
        $this->actingAs($this->memberOf($group))->getJson($url)->assertOk();

        // Mã thành viên của nhóm khác không mở được qua nhóm này.
        $foreign = $this->makeGroup();
        $this->getJson("/api/g/{$group->code}/members/{$this->memberRow($foreign, $this->ownerOf($foreign))->id}")->assertNotFound();

        // Đã rời nhóm: thành viên thường không thấy, QTV vẫn thấy.
        $m->update(['status' => 'removed']);
        $this->getJson($url)->assertNotFound();
        $this->actingAs($this->ownerOf($group))->getJson($url)->assertOk()->assertJsonPath('member.removed', true);
    }

    public function test_ten_tren_timeline_binh_luan_va_chien_dich_co_link_thanh_vien(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $lan = $this->memberOf($group, GroupRole::Member, ['name' => 'Lan']);
        $m = $this->memberRow($group, $lan);
        $post = $this->makeTx($group, ['contact_id' => $m->contact_id, 'type' => 'expense', 'title' => 'Thăm ốm']);
        $this->makeTx($group, ['payer_name' => 'Người ngoài', 'title' => 'Ủng hộ']);

        $this->actingAs($lan)->postJson("/api/g/{$group->code}/posts/{$post->id}/comments", ['body' => 'Cảm ơn cả nhóm'])
            ->assertCreated()
            ->assertJsonPath('user.name', 'Lan')
            ->assertJsonPath('user.member_id', $m->id);

        $feed = collect($this->getJson("/api/g/{$group->code}/timeline")->json('data'))->keyBy('title');
        $this->assertSame($m->id, $feed['Thăm ốm']['person_member_id']);
        $this->assertSame($m->id, $feed['Thăm ốm']['comments'][0]['user']['member_id']);
        $this->assertNull($feed['Ủng hộ']['person_member_id']);
    }

    /** Lối vào "trang của tôi" từ timeline và /ca-nhan. Xem plan/08 mục 10b. */
    public function test_nguoi_xem_biet_member_id_cua_minh(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $lan = $this->memberOf($group);
        $m = $this->memberRow($group, $lan);

        $this->getJson("/api/g/{$group->code}")->assertOk()->assertJsonPath('viewer.member_id', null);

        $this->actingAs($lan)->getJson("/api/g/{$group->code}")->assertOk()->assertJsonPath('viewer.member_id', $m->id);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('data.groups.0.code', $group->code)
            ->assertJsonPath('data.groups.0.member_id', $m->id);

        // Người của nhóm khác xem nhóm public: không có member_id.
        $other = $this->ownerOf($this->makeGroup());
        $this->actingAs($other)->getJson("/api/g/{$group->code}")->assertOk()
            ->assertJsonPath('viewer.logged_in', true)
            ->assertJsonPath('viewer.member_id', null);
    }
}
