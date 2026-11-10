<?php

namespace Tests\Feature\Transactions;

use App\Enums\GroupRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Transaction;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class InteractionsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function makePost(Group $group, array $attributes = []): Transaction
    {
        return Transaction::forceCreate($attributes + [
            'group_id' => $group->id,
            'type' => 'expense',
            'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('type', 'expense')->value('id'),
            'amount' => 100_000,
            'occurred_at' => '2026-11-01 08:00',
            'title' => 'Thăm ốm',
        ]);
    }

    public function test_tha_tim_bat_tat_chi_thanh_vien(): void
    {
        $group = $this->makeGroup();
        $post = $this->makePost($group);
        $url = "/api/g/{$group->code}/posts/{$post->id}/heart";

        $this->postJson($url)->assertUnauthorized();
        $this->actingAs($this->ownerOf($this->makeGroup()))->postJson($url)->assertForbidden();

        $member = $this->memberOf($group);
        $this->actingAs($member)->postJson($url)->assertOk()->assertJson(['hearted' => true, 'hearts_count' => 1]);
        $this->getJson("/api/g/{$group->code}/timeline")->assertJsonPath('data.0.hearted', true)->assertJsonPath('data.0.hearts_count', 1);
        $this->actingAs($member)->postJson($url)->assertOk()->assertJson(['hearted' => false, 'hearts_count' => 0]);
    }

    public function test_khong_tha_tim_phieu_an_hoac_cua_nhom_khac(): void
    {
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        $hidden = $this->makePost($group, ['show_on_timeline' => false]);
        $foreign = $this->makePost($other);
        $member = $this->memberOf($group);

        $this->actingAs($member)->postJson("/api/g/{$group->code}/posts/{$hidden->id}/heart")->assertNotFound();
        $this->actingAs($member)->postJson("/api/g/{$group->code}/posts/{$foreign->id}/heart")->assertNotFound();
    }

    public function test_binh_luan_sua_an_xoa(): void
    {
        $group = $this->makeGroup();
        $post = $this->makePost($group);
        $author = $this->memberOf($group);
        $someone = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);
        $base = "/api/g/{$group->code}";

        $comment = $this->actingAs($author)->postJson("{$base}/posts/{$post->id}/comments", ['body' => '  Chúc bạn mau khỏe  '])
            ->assertCreated()->assertJsonPath('body', 'Chúc bạn mau khỏe')->assertJsonPath('can_edit', true)->json('id');

        $this->actingAs($someone)->putJson("{$base}/comments/{$comment}", ['body' => 'Sửa trộm'])->assertForbidden();
        $this->actingAs($someone)->deleteJson("{$base}/comments/{$comment}")->assertForbidden();
        $this->actingAs($someone)->postJson("{$base}/comments/{$comment}/hide")->assertForbidden();
        $this->actingAs($author)->putJson("{$base}/comments/{$comment}", ['body' => 'Chúc bạn Lan mau khỏe'])->assertOk();

        $this->actingAs($admin)->postJson("{$base}/comments/{$comment}/hide")->assertOk()->assertJsonPath('hidden', true);

        // Bình luận bị ẩn: người khác không thấy, người viết thấy kèm nhãn, QTV thấy.
        auth()->logout();
        $this->getJson("{$base}/posts/{$post->id}/comments")->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($someone)->getJson("{$base}/posts/{$post->id}/comments")->assertJsonCount(0, 'data');
        $this->actingAs($author)->getJson("{$base}/posts/{$post->id}/comments")->assertJsonCount(1, 'data')->assertJsonPath('data.0.hidden', true);
        $this->actingAs($admin)->getJson("{$base}/posts/{$post->id}/comments")->assertJsonCount(1, 'data');
        $this->getJson("{$base}/timeline")->assertJsonPath('data.0.comments_count', 0);

        $this->actingAs($admin)->deleteJson("{$base}/comments/{$comment}")->assertNoContent();
        $this->actingAs($admin)->getJson("{$base}/posts/{$post->id}/comments")->assertJsonCount(0, 'data');
    }

    public function test_binh_luan_toi_da_10_lan_moi_phut(): void
    {
        $group = $this->makeGroup();
        $post = $this->makePost($group);
        $member = $this->memberOf($group);
        $this->actingAs($member);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/g/{$group->code}/posts/{$post->id}/comments", ['body' => "Bình luận {$i}"])->assertCreated();
        }
        $this->postJson("/api/g/{$group->code}/posts/{$post->id}/comments", ['body' => 'Thêm'])->assertUnprocessable();
    }

    public function test_khach_xem_binh_luan_nhom_public_nhung_khong_viet_duoc(): void
    {
        $group = $this->makeGroup();
        $post = $this->makePost($group);

        $this->getJson("/api/g/{$group->code}/posts/{$post->id}/comments")->assertOk();
        $this->postJson("/api/g/{$group->code}/posts/{$post->id}/comments", ['body' => 'Xin chào'])->assertUnauthorized();
    }

    public function test_timeline_kem_5_binh_luan_moi_nhat_va_xem_them_binh_luan_cu_hon(): void
    {
        $group = $this->makeGroup();
        $post = $this->makePost($group);
        $member = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);
        $ids = [];
        for ($i = 1; $i <= 17; $i++) {
            $ids[] = Comment::create(['transaction_id' => $post->id, 'user_id' => $member->id, 'body' => "Bình luận {$i}"])->id;
        }
        // Bình luận mới nhất bị ẩn: khách không thấy, QTV vẫn thấy.
        Comment::whereKey(end($ids))->update(['hidden_at' => now()]);
        $base = "/api/g/{$group->code}";

        $card = $this->getJson("{$base}/timeline")->assertOk()->json('data.0');
        $this->assertSame(['Bình luận 12', 'Bình luận 13', 'Bình luận 14', 'Bình luận 15', 'Bình luận 16'], array_column($card['comments'], 'body'));
        $this->assertTrue($card['comments_more']);
        $this->assertFalse($card['comments'][0]['can_edit']);

        $this->actingAs($admin)->getJson("{$base}/timeline")->assertJsonPath('data.0.comments.4.body', 'Bình luận 17')->assertJsonPath('data.0.comments.4.hidden', true);
        auth()->logout();

        // "Xem thêm": 10 bình luận cũ hơn bình luận cũ nhất đang hiện, rồi phần còn lại.
        $more = $this->getJson("{$base}/posts/{$post->id}/comments?before={$ids[11]}")->assertOk();
        $this->assertSame(array_map(fn ($n) => "Bình luận {$n}", range(2, 11)), array_column($more->json('data'), 'body'));
        $more->assertJsonPath('has_more', true);
        $this->getJson("{$base}/posts/{$post->id}/comments?before={$ids[1]}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Bình luận 1')->assertJsonPath('has_more', false);
    }
}
