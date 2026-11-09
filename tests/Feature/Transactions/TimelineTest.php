<?php

namespace Tests\Feature\Transactions;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\TransactionPhoto;
use App\Support\CurrentGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    private function addTransaction(Group $group, array $attributes): Transaction
    {
        $category = Category::acrossGroups()->where('group_id', $group->id)
            ->where('type', $attributes['type'] ?? 'expense')->orderBy('id')->firstOrFail();

        return Transaction::forceCreate($attributes + [
            'group_id' => $group->id,
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 100_000,
            'occurred_at' => '2026-11-01 08:00',
            'title' => 'Phiếu',
        ]);
    }

    public function test_nhom_public_khach_xem_duoc_timeline_va_anh(): void
    {
        $group = $this->makeGroup(['code' => 'nhomdemo', 'visibility' => 'public']);
        $this->actingAs($this->ownerOf($group));
        $id = $this->post($this->adminUrl($group, 'transactions'), [
            'type' => 'expense', 'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('name', 'Liên hoan')->value('id'),
            'amount' => 900_000, 'occurred_at' => '2026-11-05 19:00', 'title' => 'Liên hoan 20/11',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
            'bill' => UploadedFile::fake()->image('bill.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json();
        auth()->logout();

        $this->getJson('/api/g/nhomdemo')->assertOk()
            ->assertJsonPath('can_view', true)
            ->assertJsonPath('viewer.logged_in', false)
            ->assertJsonPath('members_count', 1);

        $feed = $this->getJson('/api/g/nhomdemo/timeline')->assertOk()
            ->assertJsonPath('data.0.kind', 'transaction')
            ->assertJsonPath('data.0.title', 'Liên hoan 20/11')
            ->assertJsonPath('data.0.amount', 900_000)
            ->assertJsonMissingPath('data.0.bills')
            ->json();

        $this->get($feed['data'][0]['photos'][0]['sm'])->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Robots-Tag', 'noindex');

        // Ảnh bill là chứng từ: khách và thành viên thường không xem được.
        $this->get($id['bills'][0]['url'])->assertNotFound();
        $this->actingAs($this->memberOf($group))->get($id['bills'][0]['url'])->assertNotFound();
        $this->actingAs($this->ownerOf($group))->get($id['bills'][0]['url'])->assertOk();
    }

    public function test_nhom_private_chan_khach_nhung_van_hien_ten(): void
    {
        $group = $this->makeGroup(['code' => 'chidoan-a12', 'visibility' => 'private']);
        $transaction = $this->addTransaction($group, ['title' => 'Bí mật']);
        $photo = TransactionPhoto::create(['transaction_id' => $transaction->id, 'path' => 'x.webp', 'thumb_path' => 'x_sm.webp']);
        Storage::disk('media')->put('x_sm.webp', 'webp');

        $this->getJson('/api/g/chidoan-a12')->assertOk()
            ->assertJsonPath('name', 'Chi đoàn A12')
            ->assertJsonPath('can_view', false)
            ->assertJsonMissingPath('members_count');
        $this->getJson('/api/g/chidoan-a12/timeline')->assertForbidden()->assertJsonPath('code', 'private_group');
        $this->getJson('/api/g/chidoan-a12/summary')->assertForbidden();
        $this->get("/media/chidoan-a12/p/{$photo->id}/sm")->assertNotFound();

        // Người đăng nhập nhưng không thuộc nhóm cũng bị chặn.
        $outsider = $this->ownerOf($this->makeGroup());
        $this->actingAs($outsider)->getJson('/api/g/chidoan-a12/timeline')->assertForbidden();

        $member = $this->memberOf($group);
        $this->actingAs($member)->getJson('/api/g/chidoan-a12/timeline')->assertOk()->assertJsonPath('data.0.title', 'Bí mật');
        $this->actingAs($member)->get("/media/chidoan-a12/p/{$photo->id}/sm")->assertOk();
        $this->assertStringContainsString('private', $this->actingAs($member)->get("/media/chidoan-a12/p/{$photo->id}/sm")->headers->get('Cache-Control'));
    }

    public function test_an_khoi_timeline_va_phieu_da_xoa_khong_hien(): void
    {
        $group = $this->makeGroup();
        $this->addTransaction($group, ['title' => 'Hiện']);
        $this->addTransaction($group, ['title' => 'Ẩn', 'show_on_timeline' => false]);
        $this->addTransaction($group, ['title' => 'Đã xóa', 'deleted_at' => now()]);

        $titles = collect($this->getJson("/api/g/{$group->code}/timeline")->json('data'))->pluck('title')->all();
        $this->assertSame(['Hiện'], $titles);

        // Ẩn khỏi timeline nhưng vẫn tính vào số dư.
        $this->getJson("/api/g/{$group->code}/summary")->assertJsonPath('expense', 200_000);
    }

    public function test_gop_phieu_thu_chien_dich_theo_ngay_va_phan_trang(): void
    {
        $group = $this->makeGroup();
        app(CurrentGroup::class)->set($group);
        $category = Category::where('type', 'income')->firstOrFail();
        $campaign = Campaign::forceCreate([
            'group_id' => $group->id, 'category_id' => $category->id, 'name' => 'Thu quỹ năm 2026', 'slug' => 'thu-quy-2026',
            'transfer_code' => 'QT'.random_int(1000, 9999), 'amount_per_member' => 500_000,
        ]);
        $an = Contact::forceCreate(['group_id' => $group->id, 'full_name' => 'Lê Văn An']);

        foreach (['08:00', '09:00', '10:00'] as $i => $time) {
            $this->addTransaction($group, [
                'type' => 'income', 'category_id' => $category->id, 'campaign_id' => $campaign->id,
                'contact_id' => $i === 0 ? $an->id : null, 'payer_name' => $i === 0 ? null : "Người {$i}",
                'amount' => 500_000, 'occurred_at' => "2026-11-03 {$time}",
            ]);
        }
        // Ngày khác chỉ một người đóng: hiện như phiếu thường.
        $this->addTransaction($group, ['type' => 'income', 'category_id' => $category->id, 'campaign_id' => $campaign->id, 'amount' => 500_000, 'occurred_at' => '2026-11-04 08:00', 'title' => 'Một mình']);
        for ($i = 0; $i < 20; $i++) {
            $this->addTransaction($group, ['title' => "Chi {$i}", 'occurred_at' => '2026-10-01 08:00']);
        }

        $page1 = $this->getJson("/api/g/{$group->code}/timeline")->assertOk()->json();
        $this->assertTrue($page1['has_more']);
        $this->assertCount(20, $page1['data']);
        $this->assertSame('transaction', $page1['data'][0]['kind']);
        $this->assertSame('Một mình', $page1['data'][0]['title']);
        $this->assertSame('campaign_day', $page1['data'][1]['kind']);
        $this->assertSame(3, $page1['data'][1]['count']);
        $this->assertSame(1_500_000, $page1['data'][1]['total']);
        $this->assertSame('Thu quỹ năm 2026', $page1['data'][1]['campaign']['name']);
        $this->assertContains('Lê Văn An', array_column($page1['data'][1]['payers'], 'name'));

        $page2 = $this->getJson("/api/g/{$group->code}/timeline?page=2")->json();
        $this->assertFalse($page2['has_more']);
        $this->assertCount(2, $page2['data']);

        $this->getJson("/api/g/{$group->code}/timeline?type=income")->assertJsonCount(2, 'data');

        $this->getJson("/api/g/{$group->code}/summary")->assertOk()
            ->assertJsonPath('campaigns.0.collected', 2_000_000)
            ->assertJsonPath('campaigns.0.members_total', 0);
    }

    public function test_trang_nhom_co_open_graph_va_noindex(): void
    {
        $group = $this->makeGroup(['code' => 'nhomdemo', 'name' => 'Nhóm Demo']);

        $this->get('/nhomdemo')->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('<meta property="og:title" content="Nhóm Demo · Quỹ nhóm">', false);

        $this->get('/')->assertOk()->assertDontSee('noindex');
        $this->get('/khong-co-nhom')->assertOk()->assertSee('<title>Quỹ nhóm</title>', false);
    }

    public function test_anh_bia_cong_khai_ca_nhom_private(): void
    {
        $group = $this->makeGroup(['visibility' => 'private']);
        $this->actingAs($this->ownerOf($group));

        $url = $this->post($this->adminUrl($group, 'settings/images'), [
            'kind' => 'cover', 'image' => UploadedFile::fake()->image('cover.jpg', 2400, 1600),
        ], ['Accept' => 'application/json'])->assertOk()->json('cover_url');
        auth()->logout();

        $this->assertStringContainsString('immutable', $this->get($url)->assertOk()->headers->get('Cache-Control'));
        $this->get(preg_replace('#/[^/]+$#', '/doan-mo', $url))->assertNotFound();
        $this->getJson("/api/g/{$group->code}")->assertJsonPath('cover_url', $url);

        $path = Group::find($group->id)->cover_path;
        [$w, $h] = getimagesizefromstring(Storage::disk('media')->get($path));
        $this->assertSame([1600, 533], [$w, $h]);
    }
}
