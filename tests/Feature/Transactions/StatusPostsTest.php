<?php

namespace Tests\Feature\Transactions;

use App\Enums\GroupRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Reaction;
use App\Models\StatusPost;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Bài đăng thành viên, xem plan/21. */
class StatusPostsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    private function url(Group $group, string $path = ''): string
    {
        return "/api/g/{$group->code}/statuses".($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    public function test_thanh_vien_dang_chu_anh_hoac_ca_hai(): void
    {
        $group = $this->makeGroup();
        $member = $this->memberOf($group);

        $this->actingAs($member)->postJson($this->url($group), ['body' => "  Chào cả nhóm\r\nhôm nay đi chơi  "])
            ->assertCreated()
            ->assertJsonPath('kind', 'status')
            ->assertJsonPath('body', "Chào cả nhóm\nhôm nay đi chơi")
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('author.name', $member->name)
            ->assertJsonPath('can_edit', true);

        $res = $this->actingAs($member)->post($this->url($group), [
            'photos' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('b.png', 800, 600)],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(2, 'photos');

        $post = StatusPost::acrossGroups()->findOrFail($res->json('id'));
        $this->assertNull($post->body);
        $this->assertSame(1600, $post->photos[0]->width);
        $this->assertStringStartsWith("groups/{$group->id}/posts/", $post->photos[0]->path);
        Storage::disk('media')->assertExists([$post->photos[0]->path, $post->photos[0]->thumb_path]);
        $this->assertStringStartsWith("/media/{$group->code}/s/", $res->json('photos.0.sm'));

        $this->actingAs($member)->post($this->url($group), [
            'body' => 'Ảnh liên hoan',
            'photos' => [UploadedFile::fake()->image('c.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(1, 'photos');
    }

    public function test_bai_rong_qua_dai_qua_nhieu_anh_bi_tu_choi(): void
    {
        $group = $this->makeGroup();
        $member = $this->memberOf($group);

        $this->actingAs($member)->postJson($this->url($group), ['body' => '   '])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs($member)->postJson($this->url($group), ['body' => str_repeat('a', 5001)])->assertUnprocessable();

        $photos = array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg", 10, 10), range(1, 11));
        $this->actingAs($member)->post($this->url($group), ['photos' => $photos], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photos');

        $this->assertSame(0, StatusPost::acrossGroups()->count());
    }

    public function test_khach_va_nguoi_nhom_khac_khong_dang_duoc(): void
    {
        $group = $this->makeGroup();

        $this->postJson($this->url($group), ['body' => 'Xin chào'])->assertUnauthorized();
        $this->actingAs($this->ownerOf($this->makeGroup()))->postJson($this->url($group), ['body' => 'Xin chào'])->assertForbidden();
    }

    public function test_che_do_cho_duyet_va_tat(): void
    {
        $group = $this->makeGroup();
        $member = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);

        $this->actingAs($member)->getJson("/api/g/{$group->code}")
            ->assertJsonPath('viewer.can_post', true)->assertJsonPath('viewer.post_needs_review', false);

        $group->update(['post_mode' => 'review']);
        $this->actingAs($member)->getJson("/api/g/{$group->code}")->assertJsonPath('viewer.post_needs_review', true);
        $pending = $this->actingAs($member)->postJson($this->url($group), ['body' => 'Chờ duyệt'])
            ->assertCreated()->assertJsonPath('status', 'pending')->json('id');
        $this->assertNull(StatusPost::acrossGroups()->find($pending)->published_at);

        // Bài chờ duyệt: người khác không thấy, không thả tim được; người đăng và QTV thấy.
        $someone = $this->memberOf($group);
        $this->actingAs($someone)->getJson($this->url($group, $pending))->assertNotFound();
        $this->actingAs($member)->getJson($this->url($group, $pending))->assertOk();
        $this->actingAs($admin)->getJson($this->url($group, $pending))->assertOk();
        $this->actingAs($member)->postJson($this->url($group, "{$pending}/heart"))->assertNotFound();

        // QTV đăng ở chế độ chờ duyệt vẫn hiện ngay.
        $this->actingAs($admin)->getJson("/api/g/{$group->code}")->assertJsonPath('viewer.post_needs_review', false);
        $this->actingAs($admin)->postJson($this->url($group), ['body' => 'Thông báo'])->assertCreated()->assertJsonPath('status', 'published');

        $group->update(['post_mode' => 'off']);
        $this->actingAs($member)->getJson("/api/g/{$group->code}")->assertJsonPath('viewer.can_post', false);
        $this->actingAs($member)->postJson($this->url($group), ['body' => 'Tắt rồi'])->assertForbidden();
        $this->actingAs($admin)->postJson($this->url($group), ['body' => 'QTV vẫn đăng'])->assertCreated();
    }

    public function test_qtv_doi_che_do_dang_bai_trong_cai_dat(): void
    {
        $group = $this->makeGroup();
        $owner = $this->ownerOf($group);

        $settings = $this->actingAs($owner)->getJson($this->adminUrl($group, 'settings'))->assertJsonPath('post_mode', 'open')->json();
        $payload = collect($settings)->only(['name', 'description', 'rules', 'visibility', 'opening_balance', 'opening_balance_date', 'bank_code', 'bank_account_no', 'bank_account_name', 'notify_emails'])->all();

        $this->actingAs($owner)->putJson($this->adminUrl($group, 'settings'), $payload + ['post_mode' => 'review'])->assertOk()->assertJsonPath('post_mode', 'review');
        $this->actingAs($owner)->putJson($this->adminUrl($group, 'settings'), $payload + ['post_mode' => 'lung-tung'])->assertUnprocessable();
        // Không gửi post_mode thì giữ nguyên.
        $this->actingAs($owner)->putJson($this->adminUrl($group, 'settings'), $payload)->assertOk()->assertJsonPath('post_mode', 'review');
    }

    public function test_sua_xoa_an_bai(): void
    {
        $group = $this->makeGroup();
        $author = $this->memberOf($group);
        $someone = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);

        $id = $this->actingAs($author)->post($this->url($group), [
            'body' => 'Bài gốc',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $photos = StatusPost::acrossGroups()->findOrFail($id)->photos;

        $this->actingAs($someone)->postJson($this->url($group, $id), ['body' => 'Sửa trộm'])->assertForbidden();
        $this->actingAs($admin)->postJson($this->url($group, $id), ['body' => 'QTV sửa'])->assertForbidden();

        $this->actingAs($author)->post($this->url($group, $id), [
            'body' => 'Bài đã sửa',
            'remove_photo_ids' => [$photos[0]->id],
            'photos' => [UploadedFile::fake()->image('c.jpg')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('body', 'Bài đã sửa')->assertJsonCount(2, 'photos')
            ->assertJsonPath('photos.0.id', $photos[1]->id);
        Storage::disk('media')->assertMissing($photos[0]->path);

        // Bỏ hết ảnh và chữ thì thành bài rỗng.
        $this->actingAs($author)->postJson($this->url($group, $id), ['body' => '', 'remove_photo_ids' => StatusPost::acrossGroups()->findOrFail($id)->photos->pluck('id')->all()])
            ->assertUnprocessable();

        // Ẩn: chỉ QTV; người khác không thấy, người đăng thấy kèm nhãn.
        $this->actingAs($someone)->postJson($this->url($group, "{$id}/hide"))->assertForbidden();
        $this->actingAs($admin)->postJson($this->url($group, "{$id}/hide"))->assertOk()->assertJsonPath('hidden', true);
        $this->actingAs($someone)->getJson($this->url($group, $id))->assertNotFound();
        $this->actingAs($author)->getJson($this->url($group, $id))->assertOk()->assertJsonPath('hidden', true);
        $this->actingAs($admin)->postJson($this->url($group, "{$id}/hide"), ['hidden' => false])->assertOk()->assertJsonPath('hidden', false);

        $this->actingAs($someone)->deleteJson($this->url($group, $id))->assertForbidden();
        $this->actingAs($admin)->deleteJson($this->url($group, $id))->assertNoContent();
        $this->assertSoftDeleted('status_posts', ['id' => $id]);

        $mine = $this->actingAs($author)->postJson($this->url($group), ['body' => 'Bài khác'])->json('id');
        $this->actingAs($author)->deleteJson($this->url($group, $mine))->assertNoContent();
    }

    public function test_bai_nhom_khac_khong_dung_duoc(): void
    {
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        $foreign = $this->actingAs($this->ownerOf($other))->postJson($this->url($other), ['body' => 'Nhóm khác'])->json('id');
        $member = $this->memberOf($group);

        $this->actingAs($member)->getJson($this->url($group, $foreign))->assertNotFound();
        $this->actingAs($member)->postJson($this->url($group, "{$foreign}/heart"))->assertNotFound();
        $this->actingAs($member)->postJson($this->url($group, "{$foreign}/comments"), ['body' => 'Hi'])->assertNotFound();
        $this->actingAs($this->ownerOf($group))->deleteJson($this->url($group, $foreign))->assertNotFound();
    }

    public function test_tim_va_binh_luan_tren_bai_dang_khong_lan_voi_phieu(): void
    {
        $group = $this->makeGroup();
        $author = $this->memberOf($group);
        $member = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);

        $id = $this->actingAs($author)->postJson($this->url($group), ['body' => 'Đi chơi'])->json('id');
        // Phiếu có cùng id để chắc tim/bình luận không lẫn sang nhau.
        $transaction = Transaction::forceCreate([
            'id' => $id,
            'group_id' => $group->id,
            'type' => 'expense',
            'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('type', 'expense')->value('id'),
            'amount' => 100_000,
            'occurred_at' => '2026-11-01 08:00',
            'title' => 'Thăm ốm',
        ]);

        $this->actingAs($member)->postJson($this->url($group, "{$id}/heart"))->assertOk()->assertJson(['hearted' => true, 'hearts_count' => 1]);
        $this->actingAs($member)->getJson("/api/g/{$group->code}/posts/{$transaction->id}")->assertJsonPath('hearts_count', 0);
        $this->assertSame(1, Reaction::whereNotNull('status_post_id')->whereNull('transaction_id')->count());

        $comment = $this->actingAs($member)->postJson($this->url($group, "{$id}/comments"), ['body' => 'Vui quá'])->assertCreated()->json('id');
        $this->actingAs($member)->getJson($this->url($group, "{$id}/comments"))->assertJsonCount(1, 'data');
        $this->actingAs($member)->getJson("/api/g/{$group->code}/posts/{$transaction->id}/comments")->assertJsonCount(0, 'data');

        $this->actingAs($author)->getJson($this->url($group, $id))
            ->assertJsonPath('hearts_count', 1)->assertJsonPath('comments_count', 1)->assertJsonPath('comments.0.body', 'Vui quá');

        // Sửa, ẩn, xóa bình luận của bài đăng đi qua đúng các route cũ.
        $this->actingAs($member)->putJson("/api/g/{$group->code}/comments/{$comment}", ['body' => 'Vui ghê'])->assertOk();
        $this->actingAs($admin)->postJson("/api/g/{$group->code}/comments/{$comment}/hide")->assertOk()->assertJsonPath('hidden', true);
        $this->actingAs($member)->deleteJson("/api/g/{$group->code}/comments/{$comment}")->assertNoContent();
        $this->assertSoftDeleted('comments', ['id' => $comment]);
        $this->assertSame(0, Comment::whereNotNull('transaction_id')->count());
    }

    public function test_anh_bai_dang_kiem_quyen(): void
    {
        $group = $this->makeGroup(['visibility' => 'private']);
        $member = $this->memberOf($group);
        $someone = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);

        $res = $this->actingAs($member)->post($this->url($group), ['photos' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])->assertCreated();
        $sm = $res->json('photos.0.sm');

        $this->actingAs($someone)->get($sm)->assertOk()->assertHeader('Content-Type', 'image/webp');
        auth()->logout();
        $this->get($sm)->assertNotFound();

        // Bài chờ duyệt: chỉ người đăng và QTV xem được ảnh.
        $group->update(['post_mode' => 'review']);
        $pending = $this->actingAs($member)->post($this->url($group), ['photos' => [UploadedFile::fake()->image('b.jpg')]], ['Accept' => 'application/json'])->json('photos.0.lg');
        $this->actingAs($someone)->get($pending)->assertNotFound();
        $this->actingAs($member)->get($pending)->assertOk();
        $this->actingAs($admin)->get($pending)->assertOk();
    }

    public function test_gioi_han_so_bai_moi_gio(): void
    {
        config(['cashflow.limits.statuses_per_hour' => 2]);
        $group = $this->makeGroup();
        $member = $this->memberOf($group);

        $this->actingAs($member)->postJson($this->url($group), ['body' => '1'])->assertCreated();
        $this->actingAs($member)->postJson($this->url($group), ['body' => '2'])->assertCreated();
        $this->actingAs($member)->postJson($this->url($group), ['body' => '3'])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    // ---- S2: bài đăng trên timeline nhóm và timeline thành viên ----

    private function addTransaction(Group $group, array $attributes = []): Transaction
    {
        return Transaction::forceCreate($attributes + [
            'group_id' => $group->id,
            'type' => 'expense',
            'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('type', $attributes['type'] ?? 'expense')->orderBy('id')->value('id'),
            'amount' => 100_000,
            'occurred_at' => '2026-11-01 08:00',
            'title' => 'Phiếu',
        ]);
    }

    private function addStatus(Group $group, User $user, array $attributes = []): StatusPost
    {
        return StatusPost::acrossGroups()->forceCreate($attributes + [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'body' => 'Bài đăng',
            'status' => 'published',
            'published_at' => '2026-11-01 08:00',
        ]);
    }

    public function test_timeline_nhom_xen_bai_dang_voi_phieu_theo_thoi_gian(): void
    {
        $group = $this->makeGroup(['code' => 'nhomxen']);
        $member = $this->memberOf($group);
        $this->addTransaction($group, ['title' => 'Phiếu 3/11', 'occurred_at' => '2026-11-03 09:00']);
        $this->addStatus($group, $member, ['body' => 'Bài 4/11', 'published_at' => '2026-11-04 09:00']);
        $this->addTransaction($group, ['title' => 'Phiếu 2/11', 'occurred_at' => '2026-11-02 09:00']);
        $this->addStatus($group, $member, ['body' => 'Bài 1/11', 'published_at' => '2026-11-01 09:00']);

        $data = $this->getJson('/api/g/nhomxen/timeline')->assertOk()->assertJsonMissingPath('pending_mine')->json('data');
        $this->assertSame(['status', 'transaction', 'transaction', 'status'], array_column($data, 'kind'));
        $this->assertSame('Bài 4/11', $data[0]['body']);
        $this->assertSame($member->name, $data[0]['author']['name']);
        $this->assertSame('Phiếu 3/11', $data[1]['title']);

        // Lọc thu/chi hoặc loại: chỉ còn phiếu. Lọc năm: vẫn có bài đăng.
        $this->assertSame(['transaction', 'transaction'], array_column($this->getJson('/api/g/nhomxen/timeline?type=expense')->json('data'), 'kind'));
        $this->assertCount(4, $this->getJson('/api/g/nhomxen/timeline?year=2026')->json('data'));
        $this->assertCount(0, $this->getJson('/api/g/nhomxen/timeline?year=2025')->json('data'));
    }

    public function test_timeline_nhom_phan_trang_khong_trung_khong_sot(): void
    {
        $group = $this->makeGroup(['code' => 'nhomtrang']);
        $member = $this->memberOf($group);
        // 25 phiếu và 25 bài, cùng giờ với nhau từng cặp để thử thứ tự phụ.
        for ($i = 1; $i <= 25; $i++) {
            $at = sprintf('2026-10-%02d 08:00', $i);
            $this->addTransaction($group, ['title' => "Phiếu {$i}", 'occurred_at' => $at]);
            $this->addStatus($group, $member, ['body' => "Bài {$i}", 'published_at' => $at]);
        }

        $seen = [];
        $page = 1;
        do {
            $res = $this->getJson("/api/g/nhomtrang/timeline?page={$page}")->assertOk()->json();
            foreach ($res['data'] as $item) {
                $seen[] = $item['kind'].':'.$item['id'];
            }
            $page++;
        } while ($res['has_more']);

        $this->assertSame(4, $page);
        $this->assertCount(50, $seen);
        $this->assertCount(50, array_unique($seen));
    }

    public function test_timeline_nhom_an_bai_chua_hien_va_bai_cho_duyet_cua_minh_o_dau(): void
    {
        $group = $this->makeGroup(['code' => 'nhomloc']);
        $member = $this->memberOf($group);
        $someone = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);
        $this->addStatus($group, $member, ['body' => 'Đang hiện']);
        $this->addStatus($group, $member, ['body' => 'Đã ẩn', 'hidden_at' => now()]);
        $this->addStatus($group, $member, ['body' => 'Chờ duyệt', 'status' => 'pending', 'published_at' => null]);
        $this->addStatus($group, $member, ['body' => 'Bị từ chối', 'status' => 'rejected', 'published_at' => null]);
        $this->addStatus($group, $member, ['body' => 'Đã xóa', 'deleted_at' => now()]);

        $bodies = fn ($res) => array_column($res->json('data'), 'body');

        $this->assertSame(['Đang hiện'], $bodies($this->getJson('/api/g/nhomloc/timeline')));
        $this->assertSame(['Đang hiện'], $bodies($this->actingAs($someone)->getJson('/api/g/nhomloc/timeline')->assertJsonCount(0, 'pending_mine')));
        $this->assertEqualsCanonicalizing(['Đang hiện', 'Đã ẩn'], $bodies($this->actingAs($admin)->getJson('/api/g/nhomloc/timeline')));

        $mine = $this->actingAs($member)->getJson('/api/g/nhomloc/timeline')
            ->assertJsonCount(1, 'pending_mine')->assertJsonPath('pending_mine.0.body', 'Chờ duyệt')->assertJsonPath('pending_mine.0.status', 'pending');
        $this->assertSame(['Đang hiện'], $bodies($mine));
        $this->actingAs($member)->getJson('/api/g/nhomloc/timeline?page=2')->assertJsonMissingPath('pending_mine');
    }

    public function test_timeline_thanh_vien_co_ca_phieu_va_bai_dang_cua_nguoi_do(): void
    {
        $group = $this->makeGroup(['code' => 'nhomtv']);
        $member = $this->memberOf($group);
        $someone = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);
        $handle = GroupMember::where('user_id', $member->id)->value('handle');
        $contactId = GroupMember::where('user_id', $member->id)->value('contact_id');

        $this->addTransaction($group, ['title' => 'Thăm ốm', 'contact_id' => $contactId, 'occurred_at' => '2026-11-02 08:00']);
        $this->addStatus($group, $member, ['body' => 'Cảm ơn cả nhóm', 'published_at' => '2026-11-03 08:00']);
        $this->addStatus($group, $member, ['body' => 'Đã ẩn', 'published_at' => '2026-11-04 08:00', 'hidden_at' => now()]);
        $this->addStatus($group, $member, ['body' => 'Chờ duyệt', 'status' => 'pending', 'published_at' => null]);
        $this->addStatus($group, $someone, ['body' => 'Bài người khác']);

        // Bài của người này ở nhóm khác không lẫn vào.
        $other = $this->makeGroup();
        GroupMember::create(['group_id' => $other->id, 'user_id' => $member->id, 'role' => GroupRole::Member]);
        $this->addStatus($other, $member, ['body' => 'Nhóm khác']);

        $url = "/api/g/nhomtv/members/{$handle}/timeline";
        $this->assertSame(['Cảm ơn cả nhóm', 'Thăm ốm'], array_map(fn ($i) => $i['body'] ?? $i['title'], $this->getJson($url)->json('data')));
        $this->assertSame(['status', 'transaction'], array_column($this->actingAs($someone)->getJson($url)->json('data'), 'kind'));
        $this->assertCount(3, $this->actingAs($admin)->getJson($url)->json('data'));

        // Chính chủ thấy cả bài chờ duyệt và bài bị ẩn (bài chờ duyệt xếp theo giờ gửi, mới nhất).
        $mine = $this->actingAs($member)->getJson($url)->json('data');
        $this->assertSame(['Chờ duyệt', 'Đã ẩn', 'Cảm ơn cả nhóm', 'Thăm ốm'], array_map(fn ($i) => $i['body'] ?? $i['title'], $mine));
    }

    public function test_tai_khoan_chua_gan_danh_ba_van_co_bai_dang(): void
    {
        $group = $this->makeGroup(['code' => 'nhomkdb']);
        $user = User::factory()->create();
        $member = GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => GroupRole::Member]);
        $this->addStatus($group, $user, ['body' => 'Chào mọi người']);

        $this->getJson("/api/g/nhomkdb/members/{$member->handle}/timeline")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Chào mọi người');
    }

    // ---- S4: duyệt bài ----

    public function test_qtv_duyet_va_tu_choi_bai(): void
    {
        $group = $this->makeGroup(['code' => 'nhomduyet']);
        $group->update(['post_mode' => 'review']);
        $member = $this->memberOf($group);
        $admin = $this->memberOf($group, GroupRole::Admin);
        $a = $this->actingAs($member)->postJson($this->url($group), ['body' => 'Bài A'])->json('id');
        $b = $this->actingAs($member)->postJson($this->url($group), ['body' => 'Bài B'])->json('id');

        // Chỉ QTV vào hàng chờ.
        $this->actingAs($member)->getJson($this->adminUrl($group, 'statuses'))->assertForbidden();
        $this->actingAs($member)->postJson($this->adminUrl($group, "statuses/{$a}/approve"))->assertForbidden();

        $this->actingAs($admin)->getJson($this->adminUrl($group, 'statuses'))->assertOk()
            ->assertJsonPath('data.0.body', 'Bài A')->assertJsonPath('data.1.body', 'Bài B')
            ->assertJsonPath('counts.pending', 2)->assertJsonPath('post_mode', 'review');
        $this->actingAs($admin)->getJson($this->adminUrl($group, 'sepay/count'))->assertJsonPath('posts', 2);
        $this->actingAs($admin)->getJson($this->adminUrl($group, 'overview'))->assertJsonPath('pending_posts', 2);
        $this->actingAs($admin)->getJson('/api/g/nhomduyet/timeline')->assertJsonPath('pending_review', 2);
        $this->actingAs($member)->getJson('/api/g/nhomduyet/timeline')->assertJsonMissingPath('pending_review');

        // Duyệt sau vài phút: bài không bị tính là "đã sửa".
        $this->travel(5)->minutes();
        $this->actingAs($admin)->postJson($this->adminUrl($group, "statuses/{$a}/approve"))->assertOk()->assertJson(['pending' => 1]);
        $approved = StatusPost::acrossGroups()->find($a);
        $this->assertSame('published', $approved->status);
        $this->assertNotNull($approved->published_at);
        $this->assertSame($admin->id, $approved->reviewed_by);
        $this->getJson($this->url($group, $a))->assertJsonPath('edited', false);
        $this->assertSame(['Bài A'], array_column($this->getJson('/api/g/nhomduyet/timeline')->json('data'), 'body'));

        $this->actingAs($admin)->postJson($this->adminUrl($group, "statuses/{$b}/reject"), ['reason' => '  Không liên quan nhóm  '])->assertOk()->assertJson(['pending' => 0]);
        $this->actingAs($admin)->postJson($this->adminUrl($group, "statuses/{$b}/reject"))->assertNotFound();
        $this->actingAs($admin)->postJson($this->adminUrl($group, "statuses/{$a}/approve"))->assertNotFound();

        // Người đăng thấy bài bị từ chối kèm lý do, chỉ xóa được.
        $handle = GroupMember::where('user_id', $member->id)->value('handle');
        $mine = collect($this->actingAs($member)->getJson("/api/g/nhomduyet/members/{$handle}/timeline")->json('data'))->firstWhere('id', $b);
        $this->assertSame('rejected', $mine['status']);
        $this->assertSame('Không liên quan nhóm', $mine['reject_reason']);
        $this->assertFalse($mine['edited']);
        $this->assertFalse($mine['can_edit']);
        $this->assertTrue($mine['can_delete']);
        $this->actingAs($member)->postJson($this->url($group, $b), ['body' => 'Sửa'])->assertForbidden();

        // Tab "Không duyệt" và duyệt lại.
        $this->actingAs($admin)->getJson($this->adminUrl($group, 'statuses?status=rejected'))->assertJsonCount(1, 'data')->assertJsonPath('counts.rejected', 1);
        $this->actingAs($admin)->postJson($this->adminUrl($group, "statuses/{$b}/approve"))->assertOk();
        $this->assertNull(StatusPost::acrossGroups()->find($b)->reject_reason);
    }

    public function test_khong_duyet_bai_nhom_khac(): void
    {
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        $other->update(['post_mode' => 'review']);
        $foreign = $this->actingAs($this->memberOf($other))->postJson($this->url($other), ['body' => 'Nhóm khác'])->json('id');

        $this->actingAs($this->ownerOf($group))->postJson($this->adminUrl($group, "statuses/{$foreign}/approve"))->assertNotFound();
        $this->actingAs($this->ownerOf($group))->getJson($this->adminUrl($group, 'statuses'))->assertJsonCount(0, 'data');
    }
}
