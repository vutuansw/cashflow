<?php

namespace Tests\Feature\Campaigns;

use App\Models\BillScan;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\PaymentClaim;
use App\Models\SepayTransaction;
use App\Models\Transaction;
use App\Services\Ai\FakeBillExtractor;
use App\Services\Campaigns\CampaignService;
use App\Support\BankReference;
use App\Support\CurrentGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Thành viên tự gửi bill, chống trùng bill. Xem plan/05 mục 5. */
class PaymentClaimsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private Group $group;

    private Campaign $campaign;

    private int $minh;

    private int $size = 600;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Queue::fake();
        Mail::fake();
        FakeBillExtractor::reset();

        $this->group = $this->makeGroup(['code' => 'chi-doan-a12', 'visibility' => 'public']);
        $this->group->update(['bank_code' => 'MBBank', 'bank_account_no' => '0123456789', 'bank_account_name' => 'NGUYEN VAN AN']);
        app(CurrentGroup::class)->set($this->group);
        $this->minh = Contact::create(['full_name' => 'Trần Văn Minh'])->id;
        $this->campaign = app(CampaignService::class)->create($this->group, $this->ownerOf($this->group), [
            'name' => 'Thu quỹ năm 2026', 'category_id' => Category::where('name', 'Đóng quỹ năm')->value('id'), 'transfer_code' => 'QNA12N26', 'amount_per_member' => 500000,
        ]);
        app(CurrentGroup::class)->set(null);
    }

    /** Ảnh giả mỗi lần một cỡ khác nhau để file (và SHA-256) khác nhau. */
    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('bill.jpg', $this->size++, 900);
    }

    private function aiReads(array $override = []): void
    {
        FakeBillExtractor::$responses[] = $override + [
            'is_bank_receipt' => true, 'amount' => 500000, 'transaction_datetime' => '2026-11-08 14:02',
            'screen_type' => 'outgoing', 'counterparty_name' => 'NGUYEN VAN AN', 'note' => 'QNA12N26 Tran Van Minh',
            'bank_name' => 'MB Bank', 'reference_code' => 'FT26314000001',
        ];
    }

    private function send(array $data = [], ?UploadedFile $bill = null, string $code = 'chi-doan-a12')
    {
        return $this->post("/api/g/{$code}/campaigns/{$this->campaign->slug}/claims", $data + [
            'contact_id' => $this->minh, 'amount' => 500000, 'bill' => $bill ?? $this->image(),
        ], ['Accept' => 'application/json']);
    }

    private function sepay(array $attributes = []): SepayTransaction
    {
        return SepayTransaction::forceCreate($attributes + [
            'group_id' => $this->group->id, 'sepay_id' => random_int(1, 999999), 'transaction_date' => '2026-11-08 14:02:37',
            'transfer_type' => 'in', 'amount' => 500000, 'content' => 'QNA12N26 Tran Van Minh', 'reference_code' => 'FT26314000001',
            'raw' => [], 'matched_campaign_id' => $this->campaign->id, 'suggested_contact_id' => $this->minh, 'status' => 'pending',
        ]);
    }

    private function claims(string $status = 'pending'): array
    {
        return $this->getJson($this->adminUrl($this->group, "claims?status={$status}"))->assertOk()->json('data');
    }

    public function test_khach_gui_bill_chua_tinh_da_dong_duyet_ra_phieu_co_bill(): void
    {
        $this->aiReads();
        $this->send(['note' => 'Đóng quỹ năm'])->assertCreated()->assertJsonPath('replaced', false);

        $claim = PaymentClaim::acrossGroups()->sole();
        $this->assertSame('pending', $claim->status);
        $scan = BillScan::acrossGroups()->find($claim->bill_scan_id);
        $this->assertSame(64, strlen($scan->file_hash));
        $this->assertSame('done', $scan->status); // AI đọc sau khi trả lời người gửi
        $this->assertSame('FT26314000001', $scan->reference_norm);

        // Chưa duyệt thì chưa tính là đã đóng, nhưng tên hiện "Chờ xác nhận".
        $page = $this->getJson("/api/g/chi-doan-a12/campaigns/{$this->campaign->slug}")->assertOk()->assertJsonPath('collected', 0)->json();
        $this->assertTrue(collect($page['people'])->firstWhere('contact_id', $this->minh)['pending_claim']);

        $this->actingAs($this->ownerOf($this->group));
        $this->getJson($this->adminUrl($this->group, 'sepay/count'))->assertJsonPath('pending', 1)->assertJsonPath('claims', 1);
        [$item] = $this->claims();
        $this->assertSame('Trần Văn Minh', $item['name']);
        $this->assertSame(500000, $item['ai']['amount']);
        $this->assertNull($item['duplicate']);
        $this->assertStringContainsString("/media/chi-doan-a12/b/{$scan->id}", $item['bill_url']);

        $id = $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"))->assertOk()->json('transaction_id');
        $t = Transaction::acrossGroups()->find($id);
        $this->assertSame([$this->campaign->id, $this->minh, 500000, '2026-11-08 14:02'], [$t->campaign_id, $t->contact_id, $t->amount, $t->occurred_at->format('Y-m-d H:i')]);
        $this->assertSame($id, $scan->fresh()->transaction_id);
        $this->assertSame('approved', $claim->fresh()->status);
        $this->getJson("/api/g/chi-doan-a12/campaigns/{$this->campaign->slug}")->assertJsonPath('collected', 500000);
        $this->getJson($this->adminUrl($this->group, 'sepay/count'))->assertJsonPath('pending', 0);
    }

    public function test_nhom_private_chan_khach_gioi_han_lan_gui_va_o_bay(): void
    {
        $this->group->update(['visibility' => 'private']);
        $this->send()->assertForbidden();
        $this->group->update(['visibility' => 'public']);

        // Ô bẫy: trả như thành công nhưng không lưu gì.
        $this->send(['website' => 'http://spam'])->assertCreated();
        $this->assertSame(0, PaymentClaim::acrossGroups()->count());

        for ($i = 0; $i < 4; $i++) {
            $this->send(['contact_id' => null, 'payer_name' => "Khách {$i}"])->assertCreated();
        }
        $this->send(['contact_id' => null, 'payer_name' => 'Khách 5'])->assertUnprocessable()->assertJsonValidationErrors('bill');
        $this->assertSame(4, PaymentClaim::acrossGroups()->count());
    }

    public function test_gui_lai_dung_anh_bi_chan_moi_nguoi_mot_bill_cho(): void
    {
        $bill = $this->image();
        $this->send([], $bill)->assertCreated();
        $this->send([], $bill)->assertUnprocessable()->assertJsonPath('errors.bill.0', 'Bill này đã được gửi trước đó.');

        // Gửi ảnh khác: thay bill cũ, vẫn chỉ một yêu cầu chờ.
        $old = BillScan::acrossGroups()->sole();
        $this->send(['amount' => 300000])->assertCreated()->assertJsonPath('replaced', true);
        $this->assertSame(1, PaymentClaim::acrossGroups()->count());
        $this->assertSame(300000, PaymentClaim::acrossGroups()->sole()->amount);
        $this->assertNull($old->fresh()->path);

        // Thiếu tên, không ảnh cũng không số tiền (plan/23).
        $this->send(['contact_id' => null, 'bill' => null, 'amount' => null])->assertUnprocessable()
            ->assertJsonValidationErrors(['payer_name'])
            ->assertJsonPath('errors.amount.0', 'Chọn ảnh bill, hoặc nhập số tiền nếu quên chụp bill.');
    }

    public function test_gui_kem_anh_khong_khai_so_tien_duyet_theo_ai_hoac_muc_phai_dong(): void
    {
        // Có ảnh, không khai số tiền: AI đọc 450.000, duyệt theo AI, không cảnh báo lệch (plan/23).
        $this->aiReads(['amount' => 450000]);
        $this->send(['amount' => null])->assertCreated();
        $claim = PaymentClaim::acrossGroups()->sole();
        $this->assertNull($claim->amount);

        $this->actingAs($this->ownerOf($this->group));
        [$item] = $this->claims();
        $this->assertSame([null, 450000, 'ai', []], [$item['amount'], $item['approve_amount'], $item['amount_source'], $item['warnings']]);
        $id = $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"))->assertOk()->json('transaction_id');
        $this->assertSame(450000, Transaction::acrossGroups()->find($id)->amount);

        // AI không đọc được số tiền: duyệt theo mức người đó phải đóng.
        auth('web')->logout();
        $this->aiReads(['is_bank_receipt' => false, 'amount' => null, 'reference_code' => null]);
        $this->send(['amount' => null])->assertCreated();
        $claim = PaymentClaim::acrossGroups()->where('status', 'pending')->sole();
        $this->actingAs($this->ownerOf($this->group));
        [$item] = $this->claims();
        $this->assertSame([500000, 'expected'], [$item['approve_amount'], $item['amount_source']]);
        $id = $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"))->assertOk()->json('transaction_id');
        $this->assertSame(500000, Transaction::acrossGroups()->find($id)->amount);
    }

    public function test_quen_chup_bill_gui_so_tien(): void
    {
        $this->send(['bill' => null, 'amount' => 300000, 'note' => 'Chuyển lúc 9h sáng'])->assertCreated();
        $claim = PaymentClaim::acrossGroups()->sole();
        $this->assertSame([300000, null], [$claim->amount, $claim->bill_scan_id]);
        $this->assertSame(0, BillScan::acrossGroups()->count());
        $this->assertSame([], FakeBillExtractor::$calls); // không có ảnh thì không gọi AI

        // Gửi lại không ảnh: thay bản chờ cũ.
        $this->send(['bill' => null, 'amount' => 250000])->assertCreated()->assertJsonPath('replaced', true);
        $this->assertSame(250000, PaymentClaim::acrossGroups()->sole()->amount);

        $this->actingAs($this->ownerOf($this->group));
        [$item] = $this->claims();
        $this->assertSame(['none', null, 250000, 'claimed'], [$item['ai']['state'], $item['bill_url'], $item['approve_amount'], $item['amount_source']]);
        $id = $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"))->assertOk()->json('transaction_id');
        $this->assertSame(250000, Transaction::acrossGroups()->find($id)->amount);
    }

    public function test_khong_co_so_tien_nao_phai_nhap_tay(): void
    {
        // Khách ngoài danh bạ, ảnh không đọc được, không khai: không có số để duyệt nhanh.
        $this->aiReads(['is_bank_receipt' => false, 'amount' => null, 'reference_code' => null]);
        $this->send(['contact_id' => null, 'payer_name' => 'Người lạ', 'amount' => null])->assertCreated();
        $claim = PaymentClaim::acrossGroups()->sole();

        $this->actingAs($this->ownerOf($this->group));
        [$item] = $this->claims();
        $this->assertSame([null, null], [$item['approve_amount'], $item['amount_source']]);
        $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"))->assertUnprocessable()
            ->assertJsonPath('errors.claim.0', 'Bill này chưa có số tiền, bấm "Nhập số tiền" để duyệt.');
    }

    public function test_trung_ma_giao_dich_voi_sepay_dang_cho_chi_ghep(): void
    {
        $this->sepay(['reference_code' => 'MBVCB.FT26314000001']);
        $this->aiReads();
        $this->send()->assertCreated();
        $claim = PaymentClaim::acrossGroups()->sole();

        $this->actingAs($this->ownerOf($this->group));
        [$item] = $this->claims();
        $this->assertSame('strong', $item['duplicate']['level']);
        $this->assertNull($item['duplicate']['transaction_id']);

        $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"), ['mode' => 'new', 'confirm' => true])->assertUnprocessable();
        // Qua form "Sửa" cũng không được tạo phiếu thứ hai.
        $this->post($this->adminUrl($this->group, 'transactions'), [
            'type' => 'income', 'category_id' => $this->campaign->category_id, 'amount' => 500000, 'occurred_at' => '2026-11-08 14:02',
            'campaign_id' => $this->campaign->id, 'contact_id' => $this->minh, 'payment_claim_id' => $claim->id,
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('payment_claim_id');

        $id = $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"), ['mode' => 'merge'])->assertOk()->json('transaction_id');
        $this->assertSame(1, Transaction::acrossGroups()->count());
        $t = Transaction::acrossGroups()->find($id);
        $this->assertSame('sepay', $t->source->value ?? $t->source);
        $this->assertSame('approved', SepayTransaction::acrossGroups()->sole()->status);
        $this->assertSame($id, BillScan::acrossGroups()->sole()->transaction_id);
    }

    public function test_trung_ma_voi_sepay_da_duyet_gan_bill_vao_phieu_da_co(): void
    {
        $sepay = $this->sepay();
        $this->actingAs($this->ownerOf($this->group));
        $id = $this->postJson($this->adminUrl($this->group, "sepay/{$sepay->id}/approve"))->assertOk()->json('transaction_id');

        $this->aiReads();
        $this->send()->assertCreated();
        $claim = PaymentClaim::acrossGroups()->sole();
        [$item] = $this->claims();
        $this->assertSame(['strong', $id], [$item['duplicate']['level'], $item['duplicate']['transaction_id']]);

        $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"), ['mode' => 'merge'])->assertOk()->assertJsonPath('transaction_id', $id);
        $this->assertSame(1, Transaction::acrossGroups()->count());
        $this->assertSame($id, BillScan::acrossGroups()->sole()->transaction_id);
        $this->getJson("/api/g/chi-doan-a12/campaigns/{$this->campaign->slug}")->assertJsonPath('collected', 500000);
    }

    public function test_khop_mem_phai_xac_nhan_moi_tach_phieu(): void
    {
        // Không có mã giao dịch, cùng số tiền, lệch 10 phút.
        $this->sepay(['reference_code' => null, 'transaction_date' => '2026-11-08 14:12:00']);
        $this->aiReads(['reference_code' => null]);
        $this->send()->assertCreated();
        $claim = PaymentClaim::acrossGroups()->sole();

        $this->actingAs($this->ownerOf($this->group));
        $this->assertSame('soft', $this->claims()[0]['duplicate']['level']);
        $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"))->assertUnprocessable()->assertJsonValidationErrors('confirm');

        // Người khác cùng số tiền, cùng lúc: không coi là trùng.
        $lan = Contact::forceCreate(['group_id' => $this->group->id, 'full_name' => 'Nguyễn Thị Lan'])->id;
        $this->aiReads(['reference_code' => null, 'note' => 'QNA12N26 Lan']);
        $this->send(['contact_id' => $lan])->assertCreated();
        $other = collect($this->claims())->firstWhere('contact_id', $lan);
        $this->assertNull($other['duplicate']);

        $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/approve"), ['mode' => 'merge'])->assertOk();
        $this->assertSame(1, Transaction::acrossGroups()->count());
        $this->assertSame('approved', SepayTransaction::acrossGroups()->sole()->status);
    }

    public function test_duyet_sepay_tu_gan_bill_thanh_vien_dang_cho(): void
    {
        $this->aiReads();
        $this->send()->assertCreated();
        $sepay = $this->sepay(['reference_code' => 'MBVCB.FT26314000001']);

        $this->actingAs($this->ownerOf($this->group));
        $id = $this->postJson($this->adminUrl($this->group, "sepay/{$sepay->id}/approve"))->assertOk()->json('transaction_id');

        $claim = PaymentClaim::acrossGroups()->sole();
        $this->assertSame(['approved', $id], [$claim->status, $claim->transaction_id]);
        $this->assertSame($id, BillScan::acrossGroups()->sole()->transaction_id);
        $this->getJson($this->adminUrl($this->group, 'sepay/count'))->assertJsonPath('pending', 0);
    }

    public function test_bill_qtv_doc_trung_ma_khong_ra_phieu_thu_hai(): void
    {
        $sepay = $this->sepay();
        $this->actingAs($this->ownerOf($this->group));
        $this->postJson($this->adminUrl($this->group, "sepay/{$sepay->id}/approve"))->assertOk();

        $this->aiReads();
        $scan = $this->post($this->adminUrl($this->group, 'bill-scans'), ['bill' => $this->image()], ['Accept' => 'application/json'])->assertCreated()->json();
        $this->assertNotNull($scan['duplicate']);

        $this->post($this->adminUrl($this->group, 'transactions'), [
            'type' => 'income', 'category_id' => $this->campaign->category_id, 'amount' => 500000, 'occurred_at' => '2026-11-08 14:02',
            'campaign_id' => $this->campaign->id, 'contact_id' => $this->minh, 'bill_scan_id' => $scan['id'],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('bill_scan_id');
        $this->assertSame(1, Transaction::acrossGroups()->count());
    }

    public function test_sua_qua_form_bo_qua_khoi_phuc_va_nhom_khac_khong_thay(): void
    {
        $this->aiReads(['amount' => 450000]);
        $this->send()->assertCreated();
        $claim = PaymentClaim::acrossGroups()->sole();

        $other = $this->makeGroup();
        $this->actingAs($this->ownerOf($other));
        $this->getJson($this->adminUrl($other, 'claims'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->adminUrl($other, "claims/{$claim->id}"))->assertNotFound();

        $this->actingAs($this->ownerOf($this->group));
        $show = $this->getJson($this->adminUrl($this->group, "claims/{$claim->id}"))->assertOk()->json();
        $this->assertSame(450000, $show['draft']['amount']);
        $this->assertContains('Số tiền AI đọc khác số người gửi khai.', $show['warnings']);

        $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/ignore"), ['reason' => 'Ảnh mờ'])->assertNoContent();
        $this->assertSame('Ảnh mờ', $this->claims('ignored')[0]['ignore_reason']);
        $this->postJson($this->adminUrl($this->group, "claims/{$claim->id}/restore"))->assertNoContent();

        $this->post($this->adminUrl($this->group, 'transactions'), [
            'type' => 'income', 'category_id' => $this->campaign->category_id, 'amount' => 450000, 'occurred_at' => '2026-11-08 14:02',
            'campaign_id' => $this->campaign->id, 'contact_id' => $this->minh, 'payment_claim_id' => $claim->id,
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertNotNull(BillScan::acrossGroups()->sole()->transaction_id);
    }

    public function test_so_ma_giao_dich(): void
    {
        $this->assertSame('MBVCB3278907687', BankReference::normalize('MBVCB.3278907687'));
        $this->assertNull(BankReference::normalize('FT1'));
        $this->assertTrue(BankReference::same('MBVCB3278907687', '3278907687'));
        $this->assertFalse(BankReference::same('MBVCB3278907687', '7687'));
        $this->assertFalse(BankReference::same('FT26314000001', 'FT26314000002'));
    }
}
