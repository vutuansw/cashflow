<?php

namespace Tests\Feature\Campaigns;

use App\Jobs\SendSepayDigest;
use App\Mail\SepayDigestMail;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\SepayTransaction;
use App\Models\System\GroupService;
use App\Models\Transaction;
use App\Services\Campaigns\CampaignService;
use App\Services\GroupServiceStatus;
use App\Support\CurrentGroup;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class SepayTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private Group $group;

    private string $key;

    private int $campaignId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->group = $this->makeGroup(['code' => 'chi-doan-a12']);
        $this->group->update(['bank_code' => 'MBBank', 'bank_account_no' => '0123456789']);
        $this->actingAs($this->ownerOf($this->group));
        $this->key = $this->postJson($this->adminUrl($this->group, 'settings/sepay'), ['enabled' => true])->assertOk()->json('sepay.api_key');

        app(CurrentGroup::class)->set($this->group);
        Contact::create(['full_name' => 'Trần Văn Minh']);
        $this->campaignId = app(CampaignService::class)->create($this->group, $this->ownerOf($this->group), [
            'name' => 'Thu quỹ năm 2026', 'category_id' => Category::where('name', 'Đóng quỹ năm')->value('id'), 'transfer_code' => 'QNA12N26', 'amount_per_member' => 500000,
        ])->id;
    }

    private function hook(array $payload, ?string $key = null, string $code = 'chi-doan-a12')
    {
        return $this->postJson("/api/webhooks/sepay/{$code}", $payload + [
            'id' => 92704, 'gateway' => 'MBBank', 'transactionDate' => '2026-11-08 14:02:37', 'accountNumber' => '0123456789',
            'content' => 'qna12 n26 Tran Van Minh', 'transferType' => 'in', 'transferAmount' => 500000, 'referenceCode' => 'MBVCB.1', 'description' => '',
        ], ['Authorization' => 'Apikey '.($key ?? $this->key)]);
    }

    public function test_api_key_bat_va_tao_lai(): void
    {
        $this->assertSame(40, strlen($this->key));
        $settings = $this->getJson($this->adminUrl($this->group, 'settings'))->json('sepay');
        $this->assertTrue($settings['enabled']);
        $this->assertStringEndsWith('/api/webhooks/sepay/chi-doan-a12', $settings['webhook_url']);

        $new = $this->postJson($this->adminUrl($this->group, 'settings/sepay'), ['enabled' => true, 'rotate' => true])->json('sepay.api_key');
        $this->assertNotSame($this->key, $new);
        $this->hook([], $this->key)->assertUnauthorized();
        $this->hook([], $new)->assertOk()->assertExactJson(['success' => true]);
    }

    public function test_nhan_dien_chien_dich_va_nguoi_dong_chong_trung(): void
    {
        $this->hook([])->assertOk()->assertExactJson(['success' => true]);
        $this->hook([])->assertOk(); // SePay gửi lại

        $this->assertSame(1, SepayTransaction::count());
        $t = SepayTransaction::first();
        $this->assertSame('pending', $t->status);
        $this->assertSame($this->campaignId, $t->matched_campaign_id);
        $this->assertSame(Contact::where('full_name', 'Trần Văn Minh')->value('id'), $t->suggested_contact_id);
        $this->assertFalse($t->account_mismatch);
        $this->assertNotNull(Group::find($this->group->id)->sepay_last_hook_at);
        Queue::assertPushed(SendSepayDigest::class, 1);

        // Chưa duyệt thì chưa tính là đã đóng.
        $this->getJson($this->adminUrl($this->group, "campaigns/{$this->campaignId}"))->assertJsonPath('collected', 0);
    }

    public function test_sai_key_nhom_khoa_nhom_khong_co(): void
    {
        $this->hook([], 'sai')->assertUnauthorized();
        $this->hook([], null, 'khong-co-nhom')->assertNotFound();
        GroupService::query()->where('group_id', $this->group->id)->update(['service_status' => 'locked']);
        app(GroupServiceStatus::class)->forget($this->group->id);
        $this->hook([])->assertStatus(423);
        $this->assertSame(0, SepayTransaction::count());
    }

    public function test_khac_tai_khoan_va_tien_ra(): void
    {
        $this->hook(['id' => 1, 'accountNumber' => '999888'])->assertOk();
        $this->hook(['id' => 2, 'transferType' => 'out', 'transferAmount' => 1000000, 'content' => 'Mung dam cuoi Hung'])->assertOk();

        $this->assertTrue(SepayTransaction::where('sepay_id', 1)->first()->account_mismatch);
        $out = SepayTransaction::where('sepay_id', 2)->first();
        $this->assertNull($out->matched_campaign_id);

        $this->postJson($this->adminUrl($this->group, "sepay/{$out->id}/approve"))->assertUnprocessable();
        $draft = $this->getJson($this->adminUrl($this->group, "sepay/{$out->id}"))->assertOk()->json('draft');
        $this->assertSame('expense', $draft['type']);
        $this->assertSame(Category::where('name', 'Đám hỉ')->value('id'), $draft['category_id']);
    }

    public function test_duyet_mot_cham_xoa_phieu_ve_hang_cho_khoi_phuc_gan_lai(): void
    {
        $this->hook([]);
        $sepay = SepayTransaction::first();

        $this->getJson($this->adminUrl($this->group, 'sepay'))->assertJsonPath('data.0.ready', true)->assertJsonPath('counts.pending', 1);
        $txId = $this->postJson($this->adminUrl($this->group, "sepay/{$sepay->id}/approve"))->assertOk()->assertJsonPath('pending', 0)->json('transaction_id');

        $tx = Transaction::find($txId);
        $this->assertSame('sepay', $tx->source);
        $this->assertSame(500000, $tx->amount);
        $this->assertSame('2026-11-08 14:02', $tx->occurred_at->format('Y-m-d H:i'));
        $this->getJson($this->adminUrl($this->group, "campaigns/{$this->campaignId}"))->assertJsonPath('collected', 500000);
        $this->postJson($this->adminUrl($this->group, "sepay/{$sepay->id}/approve"))->assertUnprocessable();

        $this->deleteJson($this->adminUrl($this->group, "transactions/{$txId}"))->assertNoContent();
        $this->assertSame('pending', $sepay->refresh()->status);

        $this->postJson($this->adminUrl($this->group, "transactions/{$txId}/restore"))->assertOk()->assertJsonPath('source', 'sepay');
        $this->assertSame('approved', $sepay->refresh()->status);
        $this->assertSame($sepay->id, Transaction::find($txId)->sepay_transaction_id);
    }

    public function test_duyet_qua_form_tien_ra_va_bo_qua_khoi_phuc(): void
    {
        $this->hook(['id' => 5, 'transferType' => 'out', 'transferAmount' => 300000, 'content' => 'Tham om']);
        $out = SepayTransaction::first();

        // Sai chiều tiền.
        $this->postJson($this->adminUrl($this->group, 'transactions'), [
            'type' => 'income', 'category_id' => Category::where('name', 'Đóng quỹ năm')->value('id'), 'amount' => 300000, 'occurred_at' => '2026-11-08', 'sepay_transaction_id' => $out->id,
        ])->assertJsonValidationErrors('type');

        $this->postJson($this->adminUrl($this->group, 'transactions'), [
            'type' => 'expense', 'category_id' => Category::where('name', 'Thăm ốm')->value('id'), 'amount' => 300000, 'occurred_at' => '2026-11-08', 'sepay_transaction_id' => $out->id,
        ])->assertCreated()->assertJsonPath('source', 'sepay');
        $this->assertSame('approved', $out->refresh()->status);

        $this->hook(['id' => 6, 'content' => 'Lai tien gui', 'transferAmount' => 1200]);
        $lai = SepayTransaction::where('sepay_id', 6)->first();
        $this->postJson($this->adminUrl($this->group, "sepay/{$lai->id}/ignore"), ['reason' => 'Tiền lãi'])->assertOk();
        $this->getJson($this->adminUrl($this->group, 'sepay?status=ignored'))->assertJsonPath('data.0.ignore_reason', 'Tiền lãi');
        $this->postJson($this->adminUrl($this->group, "sepay/{$lai->id}/restore"))->assertOk()->assertJsonPath('pending', 1);
    }

    public function test_duyet_hang_loat(): void
    {
        Contact::create(['full_name' => 'Lê Thị Hoa']);
        $this->hook(['id' => 11]);
        $this->hook(['id' => 12, 'content' => 'QNA12N26 Le Thi Hoa']);
        $this->hook(['id' => 13, 'content' => 'chuyen tien']);
        $ids = SepayTransaction::pluck('id')->all();

        $this->postJson($this->adminUrl($this->group, 'sepay/approve'), ['ids' => $ids])->assertOk()
            ->assertJsonPath('approved', 2)->assertJsonCount(1, 'failed')->assertJsonPath('pending', 1);
    }

    public function test_email_gop_gui_nguoi_nhan_thong_bao(): void
    {
        Mail::fake();
        $this->group->update(['notify_emails' => ['thuquy@example.test']]);
        $this->hook(['id' => 21]);
        $this->hook(['id' => 22, 'transferAmount' => 200000]);

        (new SendSepayDigest($this->group->id))->handle();

        Mail::assertSent(SepayDigestMail::class, fn ($m) => $m->hasTo('thuquy@example.test') && $m->items->count() === 2);

        // Tắt thông báo thì không gửi.
        Mail::fake();
        $this->putJson($this->adminUrl($this->group, 'settings/notifications'), ['sepay' => false])->assertOk();
        $this->hook(['id' => 23]);
        (new SendSepayDigest($this->group->id))->handle();
        Mail::assertNothingSent();
    }

    public function test_lenh_gia_lap(): void
    {
        $this->artisan('cashflow:sepay-simulate', ['group' => 'chi-doan-a12', 'amount' => 500000, 'content' => 'QNA12N26 Tran Van Minh'])->assertSuccessful();
        $this->assertSame(1, SepayTransaction::count());
    }

    public function test_khong_co_ma_thi_doan_chien_dich_duy_nhat_dang_mo(): void
    {
        // Khách quét QR không nội dung: app ngân hàng điền "TEN chuyen tien".
        $this->hook(['id' => 21, 'content' => 'TRAN VAN MINH chuyen tien'])->assertOk();
        $t = SepayTransaction::where('sepay_id', 21)->first();
        $this->assertSame($this->campaignId, $t->matched_campaign_id);
        $this->assertTrue($t->campaign_guessed);
        $this->assertSame(Contact::where('full_name', 'Trần Văn Minh')->value('id'), $t->suggested_contact_id);

        $item = collect($this->getJson($this->adminUrl($this->group, 'sepay'))->assertOk()->json('data'))->firstWhere('id', $t->id);
        $this->assertTrue($item['campaign_guessed']);

        // Không vào Duyệt cả loạt; duyệt riêng thì được.
        $this->postJson($this->adminUrl($this->group, 'sepay/approve'), ['ids' => [$t->id]])->assertOk()
            ->assertJsonPath('approved', 0)->assertJsonPath('failed.0.id', $t->id);
        $this->postJson($this->adminUrl($this->group, "sepay/{$t->id}/approve"))->assertOk();
        $this->assertSame($this->campaignId, Transaction::sole()->campaign_id);

        // Có mã thì không phải đoán; tiền ra thì không gán chiến dịch.
        $this->hook(['id' => 22])->assertOk();
        $this->assertFalse(SepayTransaction::where('sepay_id', 22)->first()->campaign_guessed);
        $this->hook(['id' => 23, 'transferType' => 'out', 'content' => 'chuyen tien'])->assertOk();
        $this->assertNull(SepayTransaction::where('sepay_id', 23)->first()->matched_campaign_id);

        // Hai chiến dịch đang mở: không đoán.
        app(CurrentGroup::class)->set($this->group);
        app(CampaignService::class)->create($this->group, $this->ownerOf($this->group), [
            'name' => 'Quỹ khuyến học', 'category_id' => Category::where('name', 'Đóng quỹ năm')->value('id'), 'transfer_code' => 'KH2026',
        ]);
        $this->hook(['id' => 24, 'content' => 'TRAN VAN MINH chuyen tien'])->assertOk();
        $other = SepayTransaction::where('sepay_id', 24)->first();
        $this->assertNull($other->matched_campaign_id);
        $this->assertFalse($other->campaign_guessed);
    }

    public function test_nhan_ten_va_ma_hoa_thuong_co_dau_khong_dau(): void
    {
        $minh = Contact::where('full_name', 'Trần Văn Minh')->value('id');
        $cases = [
            31 => 'TRAN VAN MINH QNA12N26',          // nội dung QR app tạo: in hoa, không dấu, tên trước
            32 => 'trần văn minh qna12n26',          // thường, có dấu
            33 => 'TRẦN VĂN MINH QNA12N26',          // hoa, có dấu
            34 => 'Tran van Minh - Qna12 N26',       // lẫn lộn, mã bị tách
            35 => 'qna12n26 tRần vĂn mInH ck',
        ];
        foreach ($cases as $id => $content) {
            $this->hook(['id' => $id, 'content' => $content])->assertOk();
            $t = SepayTransaction::where('sepay_id', $id)->first();
            $this->assertSame($this->campaignId, $t->matched_campaign_id, $content);
            $this->assertFalse($t->campaign_guessed, $content);
            $this->assertSame($minh, $t->suggested_contact_id, $content);
        }

        // Tên có "Đ": viết "D" hay "Đ", hoa hay thường đều ra.
        app(CurrentGroup::class)->set($this->group);
        $duc = Contact::create(['full_name' => 'Đỗ Văn Đức'])->id;
        foreach ([41 => 'DO VAN DUC QNA12N26', 42 => 'đỗ văn đức QNA12N26', 43 => 'ĐỖ VĂN ĐỨC qna12n26'] as $id => $content) {
            $this->hook(['id' => $id, 'content' => $content])->assertOk();
            $this->assertSame($duc, SepayTransaction::where('sepay_id', $id)->first()->suggested_contact_id, $content);
        }
    }
}
