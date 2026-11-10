<?php

namespace Tests\Feature\Ai;

use App\Enums\GroupRole;
use App\Models\BillScan;
use App\Models\Contact;
use App\Models\System\GroupService;
use App\Services\Ai\FakeBillExtractor;
use App\Services\Ai\OpenRouterBillExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class BillScanTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        FakeBillExtractor::reset();
        config(['ai.vision.model' => 'model-re', 'ai.vision.fallback_model' => 'model-manh']);
    }

    private function bill(): UploadedFile
    {
        return UploadedFile::fake()->image('bill.jpg', 1080, 2340);
    }

    public function test_doc_bill_ra_ban_nhap_va_luu_thanh_phieu_ai(): void
    {
        $group = $this->makeGroup();
        $admin = $this->memberOf($group, GroupRole::Admin);
        $lan = Contact::forceCreate(['group_id' => $group->id, 'full_name' => 'Nguyễn Thị Lan']);

        $scan = $this->actingAs($admin)->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('readable', true)
            ->assertJsonPath('draft.type', 'expense')
            ->assertJsonPath('draft.amount', 500000)
            ->assertJsonPath('draft.contact_id', $lan->id)
            ->json();

        $this->assertSame(['model-re'], FakeBillExtractor::$calls);
        $row = BillScan::acrossGroups()->find($scan['id']);
        $this->assertSame(1000, $row->prompt_tokens);
        $this->assertEquals(0.0001, (float) $row->cost_usd);
        $this->assertNull($row->transaction_id);
        $this->get($scan['bill_url'])->assertOk();

        // Bấm Lưu: phiếu nguồn ai_bill, bill gắn vào phiếu.
        $draft = $scan['draft'];
        $id = $this->postJson($this->adminUrl($group, 'transactions'), [
            'type' => $draft['type'], 'category_id' => $draft['category_id'], 'amount' => $draft['amount'],
            'occurred_at' => $draft['occurred_at'], 'contact_id' => $draft['contact_id'], 'bill_scan_id' => $scan['id'],
        ])->assertCreated()->assertJsonPath('source', 'ai_bill')->assertJsonCount(1, 'bills')->json('id');

        $this->assertSame($id, $row->refresh()->transaction_id);

        // Một bill không gắn được vào hai phiếu.
        $this->postJson($this->adminUrl($group, 'transactions'), [
            'type' => 'expense', 'category_id' => $draft['category_id'], 'amount' => 1, 'occurred_at' => now()->toDateString(), 'bill_scan_id' => $scan['id'],
        ])->assertJsonValidationErrors('bill_scan_id');
    }

    public function test_doc_lai_bang_model_du_phong_mot_lan(): void
    {
        $group = $this->makeGroup();
        FakeBillExtractor::$responses = [
            ['is_bank_receipt' => true, 'amount' => null, 'note' => 'mo qua'],
            FakeBillExtractor::sample(),
        ];

        $this->actingAs($this->ownerOf($group))->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('draft.amount', 500000);

        $this->assertSame(['model-re', 'model-manh'], FakeBillExtractor::$calls);
        $scan = BillScan::acrossGroups()->latest('id')->first();
        $this->assertTrue($scan->fallback_used);
        $this->assertSame('model-manh', $scan->model);
        $this->assertSame(2000, $scan->prompt_tokens);
    }

    public function test_ai_loi_van_giu_anh_lam_chung_tu(): void
    {
        $group = $this->makeGroup();
        FakeBillExtractor::$responses = [new RuntimeException('timeout')];

        $this->actingAs($this->ownerOf($group))->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('readable', false)
            ->assertJsonPath('draft', null)
            ->assertJsonPath('message', 'Không đọc được bill, bạn nhập tay nhé. Ảnh vẫn được giữ làm chứng từ.');

        $scan = BillScan::acrossGroups()->latest('id')->first();
        $this->assertSame('timeout', $scan->error);
        Storage::disk('media')->assertExists($scan->path);
    }

    public function test_anh_khong_phai_bill(): void
    {
        $group = $this->makeGroup();
        FakeBillExtractor::$responses = [['is_bank_receipt' => false]];

        $this->actingAs($this->ownerOf($group))->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('readable', false)->assertJsonPath('status', 'done');
        $this->assertSame(['model-re'], FakeBillExtractor::$calls);
    }

    public function test_het_han_muc_thang_va_quyen(): void
    {
        $group = $this->makeGroup();
        GroupService::query()->where('group_id', $group->id)->update(['ai_monthly_quota' => 1]);
        $owner = $this->ownerOf($group);

        $this->actingAs($owner)->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($owner)->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('bill');

        $this->actingAs($this->memberOf($group))->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json'])->assertForbidden();

        // Nhóm khác không xem được lượt đọc của nhóm này.
        $other = $this->makeGroup();
        $id = BillScan::acrossGroups()->where('group_id', $group->id)->value('id');
        $this->actingAs($this->ownerOf($other))->getJson($this->adminUrl($other, "bill-scans/{$id}"))->assertNotFound();
        $this->actingAs($owner)->getJson($this->adminUrl($group, "bill-scans/{$id}"))->assertOk();
    }

    public function test_openrouter_gui_dung_header_va_boc_json(): void
    {
        config(['ai.api_key' => 'sk-or-test', 'ai.url' => 'https://openrouter.test/chat']);
        Http::fake(['openrouter.test/*' => Http::response([
            'model' => 'google/gemini-3.1-flash-lite',
            'choices' => [['message' => ['content' => "```json\n{\"is_bank_receipt\": true, \"amount\": 120000, \"note\": \"tien hoa\"}\n```"]]],
            'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 60, 'cost' => 0.00012],
        ])]);

        $result = (new OpenRouterBillExtractor)->extract('data:image/webp;base64,AAAA', 'google/gemini-3.1-flash-lite');

        $this->assertSame(120000, $result['data']['amount']);
        $this->assertSame(0.00012, $result['usage']['cost']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-or-test')
            && $request->hasHeader('X-Title')
            && $request['temperature'] === 0
            && $request['messages'][1]['content'][0]['image_url']['url'] === 'data:image/webp;base64,AAAA');
    }

    public function test_don_anh_bill_mo_coi_sau_7_ngay(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group))->post($this->adminUrl($group, 'bill-scans'), ['bill' => $this->bill()], ['Accept' => 'application/json']);
        $scan = BillScan::acrossGroups()->latest('id')->first();
        $path = $scan->path;
        BillScan::acrossGroups()->whereKey($scan->id)->update(['created_at' => now()->subDays(8)]);

        $this->artisan('cashflow:prune-bills')->assertSuccessful();

        Storage::disk('media')->assertMissing($path);
        $this->assertNull($scan->refresh()->path);
        $this->assertNotNull($scan->model);
    }
}
