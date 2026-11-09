<?php

namespace Tests\Feature\Transactions;

use App\Enums\GroupRole;
use App\Models\BillScan;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\TransactionHistory;
use App\Models\TransactionPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    private function category(Group $group, string $name): Category
    {
        return Category::acrossGroups()->where('group_id', $group->id)->where('name', $name)->firstOrFail();
    }

    public function test_tao_phieu_chi_co_anh_su_kien_va_anh_bill(): void
    {
        $group = $this->makeGroup(['opening_balance' => 2_000_000]);
        $admin = $this->memberOf($group, GroupRole::Admin);
        $lan = Contact::forceCreate(['group_id' => $group->id, 'full_name' => 'Nguyễn Thị Lan']);

        $response = $this->actingAs($admin)->post($this->adminUrl($group, 'transactions'), [
            'type' => 'expense',
            'category_id' => $this->category($group, 'Thăm ốm')->id,
            'amount' => '500.000',
            'occurred_at' => '2026-11-08 09:30',
            'contact_id' => $lan->id,
            'description' => 'Cả lớp đi thăm',
            'photos' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('b.png', 800, 600)],
            'bill' => UploadedFile::fake()->image('bill.jpg', 1000, 2000),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('title', 'Thăm ốm Nguyễn Thị Lan')
            ->assertJsonPath('amount', 500000)
            ->assertJsonPath('person', 'Nguyễn Thị Lan')
            ->assertJsonPath('source', 'manual')
            ->assertJsonCount(2, 'photos')
            ->assertJsonCount(1, 'bills')
            ->assertJsonPath('histories.0.action', 'created');

        $photo = TransactionPhoto::firstOrFail();
        $this->assertSame(1600, $photo->width);
        $this->assertSame(1067, $photo->height);
        $this->assertStringStartsWith("groups/{$group->id}/photos/", $photo->path);
        Storage::disk('media')->assertExists([$photo->path, $photo->thumb_path]);
        $this->assertSame('image/webp', Storage::disk('media')->mimeType($photo->path));

        $bill = BillScan::acrossGroups()->firstOrFail();
        $this->assertSame($response->json('id'), $bill->transaction_id);
        Storage::disk('media')->assertExists($bill->path);

        $this->getJson("/api/g/{$group->code}/summary")->assertOk()->assertJsonPath('balance', 1_500_000);
    }

    public function test_thanh_vien_va_nhom_khac_khong_tao_duoc_phieu(): void
    {
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        $member = $this->memberOf($group);
        $payload = ['type' => 'income', 'category_id' => $this->category($group, 'Đóng quỹ năm')->id, 'amount' => 100000, 'occurred_at' => '2026-11-01 08:00'];

        $this->actingAs($member)->postJson($this->adminUrl($group, 'transactions'), $payload)->assertForbidden();

        // QTV nhóm khác dùng loại của nhóm này: loại coi như không tồn tại.
        $this->actingAs($this->ownerOf($other))->postJson($this->adminUrl($other, 'transactions'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_loai_phai_dung_thu_hay_chi_va_chien_dich_chi_cho_phieu_thu(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $this->postJson($this->adminUrl($group, 'transactions'), [
            'type' => 'income', 'category_id' => $this->category($group, 'Liên hoan')->id, 'amount' => 1000, 'occurred_at' => '2026-11-01',
        ])->assertJsonValidationErrors('category_id');

        $this->postJson($this->adminUrl($group, 'transactions'), [
            'type' => 'expense', 'category_id' => $this->category($group, 'Liên hoan')->id, 'amount' => 0, 'occurred_at' => '2026-11-01',
        ])->assertJsonValidationErrors('amount');
    }

    public function test_sua_phieu_ghi_lich_su_va_doi_anh(): void
    {
        $group = $this->makeGroup();
        $owner = $this->ownerOf($group);
        $this->actingAs($owner);

        $id = $this->post($this->adminUrl($group, 'transactions'), [
            'type' => 'expense', 'category_id' => $this->category($group, 'Liên hoan')->id, 'amount' => 1_200_000,
            'occurred_at' => '2026-11-02 19:00', 'title' => 'Liên hoan cuối năm',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $photos = TransactionPhoto::where('transaction_id', $id)->orderBy('sort_order')->get();
        $removedPath = $photos[0]->path;

        $response = $this->post($this->adminUrl($group, "transactions/{$id}"), [
            'type' => 'expense', 'category_id' => $this->category($group, 'Liên hoan')->id, 'amount' => 1_350_000,
            'occurred_at' => '2026-11-02 19:00', 'title' => 'Liên hoan cuối năm',
            'remove_photo_ids' => [$photos[0]->id],
            'photos' => [UploadedFile::fake()->image('c.jpg')],
            'photo_order' => ['n:0', "p:{$photos[1]->id}"],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('amount', 1_350_000)->assertJsonCount(2, 'photos');
        $this->assertNotEquals($photos[1]->id, $response->json('photos.0.id'));
        Storage::disk('media')->assertMissing($removedPath);

        $history = TransactionHistory::where('transaction_id', $id)->where('action', 'updated')->firstOrFail();
        $this->assertSame(['amount' => 1_350_000], $history->changes['after']);
        $this->assertSame(['amount' => 1_200_000], $history->changes['before']);
        $this->assertEquals(['photos_removed' => 1, 'photos_added' => 1], $history->changes['media']);
        $response->assertJsonPath('histories.0.changes.0.field', 'Số tiền')
            ->assertJsonPath('histories.0.changes.0.to', '1.350.000 đ');
    }

    public function test_xoa_mem_khoi_phuc_va_so_du_dung(): void
    {
        $group = $this->makeGroup(['opening_balance' => 1_000_000]);
        $this->actingAs($this->ownerOf($group));

        $income = $this->postJson($this->adminUrl($group, 'transactions'), [
            'type' => 'income', 'category_id' => $this->category($group, 'Đóng quỹ năm')->id, 'amount' => 300_000, 'occurred_at' => '2026-10-01 08:00', 'payer_name' => 'Bạn Hà',
        ])->assertCreated()->assertJsonPath('title', 'Đóng quỹ năm Bạn Hà')->json('id');
        $this->postJson($this->adminUrl($group, 'transactions'), [
            'type' => 'expense', 'category_id' => $this->category($group, 'Đám hỉ')->id, 'amount' => 500_000, 'occurred_at' => '2026-10-05 08:00',
        ])->assertCreated();

        $summary = fn () => $this->getJson("/api/g/{$group->code}/summary?year=2026")->json();
        $this->assertSame(800_000, $summary()['balance']);

        $this->deleteJson($this->adminUrl($group, "transactions/{$income}"))->assertNoContent();
        $this->assertSame(500_000, $summary()['balance']);
        $this->assertNotNull(Transaction::acrossGroups()->withTrashed()->find($income)->deleted_by);

        $this->getJson($this->adminUrl($group, 'transactions?trashed=1'))->assertOk()->assertJsonPath('data.0.id', $income);

        $this->postJson($this->adminUrl($group, "transactions/{$income}/restore"))->assertOk()->assertJsonPath('deleted_at', null);
        $this->assertSame(800_000, $summary()['balance']);
        $this->assertSame(['created', 'deleted', 'restored'], TransactionHistory::where('transaction_id', $income)->orderBy('id')->pluck('action')->all());

        $s = $summary();
        $this->assertSame(300_000, $s['income']);
        $this->assertSame(500_000, $s['expense']);
        $this->assertSame(300_000, $s['months'][9]['income']);
        $this->assertSame('Đám hỉ', $s['expense_by_category'][0]['name']);
    }

    public function test_khong_khoi_phuc_phieu_xoa_qua_30_ngay(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));
        $id = $this->postJson($this->adminUrl($group, 'transactions'), [
            'type' => 'expense', 'category_id' => $this->category($group, 'Đám hiếu')->id, 'amount' => 200_000, 'occurred_at' => '2026-09-01',
        ])->json('id');
        $this->deleteJson($this->adminUrl($group, "transactions/{$id}"));
        Transaction::acrossGroups()->withTrashed()->whereKey($id)->update(['deleted_at' => now()->subDays(31)]);

        $this->postJson($this->adminUrl($group, "transactions/{$id}/restore"))->assertUnprocessable();
    }

    public function test_danh_sach_loc_va_tong(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));
        $thu = $this->category($group, 'Đóng quỹ năm')->id;
        $chi = $this->category($group, 'Liên hoan')->id;

        foreach ([['income', $thu, 100_000, '2026-01-10'], ['income', $thu, 200_000, '2026-02-10'], ['expense', $chi, 50_000, '2026-02-15']] as [$type, $cat, $amount, $date]) {
            $this->postJson($this->adminUrl($group, 'transactions'), ['type' => $type, 'category_id' => $cat, 'amount' => $amount, 'occurred_at' => $date, 'title' => "Phiếu {$amount}"])->assertCreated();
        }

        $this->getJson($this->adminUrl($group, 'transactions?from=2026-02-01&to=2026-02-28'))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('totals.income', 200_000)
            ->assertJsonPath('totals.expense', 50_000)
            ->assertJsonPath('data.0.title', 'Phiếu 50000');

        $this->getJson($this->adminUrl($group, 'transactions?type=income&q=100000'))->assertJsonCount(1, 'data');
        $this->getJson($this->adminUrl($group, 'transactions/options'))->assertOk()->assertJsonCount(6, 'categories');
    }
}
