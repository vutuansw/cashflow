<?php

namespace Tests\Feature\Ai;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Services\Ai\BillDraftResolver;
use App\Support\CurrentGroup;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Bộ JSON mẫu theo plan/04 mục 7. */
class BillDraftResolverTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = $this->makeGroup();
        app(CurrentGroup::class)->set($this->group);

        foreach (['Nguyễn Thị Lan', 'Trần Văn Minh', 'Lê Thị Hoa', 'Phạm Thị Hoa'] as $name) {
            Contact::create(['full_name' => $name]);
        }
    }

    private function resolve(array $raw): array
    {
        return app(BillDraftResolver::class)->resolve($raw + ['is_bank_receipt' => true]);
    }

    private function categoryId(string $name): int
    {
        return Category::where('name', $name)->value('id');
    }

    public function test_chuyen_khoan_di_tham_om(): void
    {
        $draft = $this->resolve([
            'amount' => '500.000', 'transaction_datetime' => now()->subDay()->format('Y-m-d').' 14:02', 'screen_type' => 'outgoing',
            'counterparty_name' => 'NGUYEN THI LAN', 'note' => 'Tham om ban Lan',
        ]);

        $this->assertSame('expense', $draft['type']);
        $this->assertSame(500000, $draft['amount']);
        $this->assertSame(now()->subDay()->format('Y-m-d').' 14:02', $draft['occurred_at']);
        $this->assertSame($this->categoryId('Thăm ốm'), $draft['category_id']);
        $this->assertSame(Contact::where('full_name', 'Nguyễn Thị Lan')->value('id'), $draft['contact_id']);
        $this->assertSame('Thăm ốm Nguyễn Thị Lan', $draft['title']);
        $this->assertSame('Tham om ban Lan', $draft['description']);
        $this->assertEqualsCanonicalizing(['amount', 'occurred_at', 'type', 'category_id', 'contact_id'], $draft['ai_fields']);
    }

    public function test_chuyen_khoan_vao_co_ma_chien_dich(): void
    {
        $campaign = Campaign::create([
            'category_id' => $this->categoryId('Đóng quỹ năm'), 'name' => 'Thu quỹ năm 2026', 'slug' => 'thu-quy-2026', 'transfer_code' => 'QNA12N26',
        ]);

        // Ảnh do người đóng chụp từ app của họ (màn chuyển đi), nội dung có mã viết tách.
        $draft = $this->resolve([
            'amount' => 500000, 'screen_type' => 'outgoing', 'counterparty_name' => 'NGUYEN VAN AN',
            'note' => 'qna12 n26 Tran Van Minh dong quy',
        ]);

        $this->assertSame('income', $draft['type']);
        $this->assertSame($campaign->id, $draft['campaign_id']);
        $this->assertSame($this->categoryId('Đóng quỹ năm'), $draft['category_id']);
        $this->assertSame(Contact::where('full_name', 'Trần Văn Minh')->value('id'), $draft['contact_id']);
    }

    public function test_ten_trung_hai_nguoi_thi_goi_y_danh_sach(): void
    {
        $draft = $this->resolve(['amount' => 200000, 'screen_type' => 'incoming', 'counterparty_name' => 'THI HOA', 'note' => 'chuyen tien']);

        $this->assertNull($draft['contact_id']);
        $this->assertEqualsCanonicalizing(['Lê Thị Hoa', 'Phạm Thị Hoa'], array_column($draft['contact_candidates'], 'name'));
        $this->assertSame('income', $draft['type']);
        $this->assertNull($draft['category_id']);
    }

    public function test_nguoi_ngoai_danh_ba_dien_ten_tu_do(): void
    {
        $draft = $this->resolve(['amount' => 1800000, 'screen_type' => 'outgoing', 'counterparty_name' => 'NHA HANG LAU NUONG HOANG GIA', 'note' => 'Lien hoan cuoi nam']);

        $this->assertNull($draft['contact_id']);
        $this->assertSame('Nha Hang Lau Nuong Hoang Gia', $draft['payer_name']);
        $this->assertSame($this->categoryId('Liên hoan'), $draft['category_id']);
        $this->assertSame('Liên hoan Nha Hang Lau Nuong Hoang Gia', $draft['title']);
    }

    public function test_khong_chac_thu_hay_chi_thi_de_trong(): void
    {
        $draft = $this->resolve(['amount' => 300000, 'screen_type' => null, 'note' => 'CHUYEN TIEN']);

        $this->assertNull($draft['type']);
        $this->assertNull($draft['category_id']);
        $this->assertNotContains('type', $draft['ai_fields']);
    }

    public function test_tu_khoa_cum_dai_thang_va_khong_khop_giua_chu(): void
    {
        // "chi" chứa "hi" nhưng không được hiểu là Đám hỉ; "dam cuoi" khớp Đám hỉ.
        $this->assertNull($this->resolve(['amount' => 1, 'screen_type' => 'outgoing', 'note' => 'chi tien'])['category_id']);
        $this->assertSame($this->categoryId('Đám hỉ'), $this->resolve(['amount' => 1, 'screen_type' => 'outgoing', 'note' => 'Mung dam cuoi Hung'])['category_id']);
        $this->assertSame($this->categoryId('Đám hiếu'), $this->resolve(['amount' => 1, 'screen_type' => 'outgoing', 'note' => 'Vieng bac nha Nam'])['category_id']);
    }

    public function test_anh_khong_phai_bill_va_ngay_sai(): void
    {
        $draft = app(BillDraftResolver::class)->resolve(['is_bank_receipt' => false, 'amount' => null]);
        $this->assertFalse($draft['is_bill']);
        $this->assertSame([], $draft['ai_fields']);

        $this->assertNull($this->resolve(['amount' => 1000, 'transaction_datetime' => now()->addYear()->format('Y-m-d H:i')])['occurred_at']);
    }

    /**
     * Nhóm có STK quỹ, danh bạ và chiến dịch giống nhóm thật; nội dung bill
     * lấy từ JSON AI đọc bill thật (plan/26), tên và STK đã thay.
     */
    private function fundGroup(): void
    {
        $this->group->forceFill(['bank_code' => 'Vietcombank', 'bank_account_no' => '1012345678', 'bank_account_name' => 'NGUYEN MINH HIEN'])->save();
        app(CurrentGroup::class)->set($this->group->fresh());
        foreach (['Nguyễn Minh Hiển', 'Vũ Đức Tuấn', 'Nguyễn Thị Kim Hoa', 'Trần Thị Tư', 'Vũ Văn Thường', 'Nguyễn Văn Thường B', 'Đặng Thị Thương Thương'] as $name) {
            Contact::create(['full_name' => $name]);
        }
        Campaign::create(['category_id' => $this->categoryId('Đóng quỹ năm'), 'name' => 'Thu quỹ năm 2026', 'slug' => 'thu-quy-2026', 'transfer_code' => 'CHIDOAN26']);
    }

    /** Màn "Chuyển tiền thành công" trên app người đóng, chuyển tới STK quỹ. */
    private function intoFund(string $note, array $raw = []): array
    {
        return $this->resolve($raw + [
            'amount' => 200000, 'screen_type' => 'outgoing', 'counterparty_name' => 'NGUYEN MINH HIEN',
            'counterparty_account' => '1012345678', 'note' => $note,
        ]);
    }

    private function contactId(string $name): int
    {
        return Contact::where('full_name', $name)->value('id');
    }

    public function test_chuyen_vao_stk_quy_la_phieu_thu_va_lay_ten_trong_noi_dung(): void
    {
        $this->fundGroup();
        $campaign = Campaign::where('transfer_code', 'CHIDOAN26')->value('id');

        // Không có mã chiến dịch: trước đây thành phiếu chi và chọn chủ TK quỹ.
        $draft = $this->intoFund('VU DUC TUAN chuyen tien 2026');
        $this->assertSame('income', $draft['type']);
        $this->assertSame($campaign, $draft['campaign_id']);
        $this->assertSame($this->categoryId('Đóng quỹ năm'), $draft['category_id']);
        $this->assertSame($this->contactId('Vũ Đức Tuấn'), $draft['contact_id']);
        $this->assertSame([], $draft['contact_candidates']);
        $this->assertNull($draft['payer_name']);

        // Đệm + tên, có mã chiến dịch.
        $this->assertSame($this->contactId('Nguyễn Thị Kim Hoa'), $this->intoFund('KIM HOA CHIDOAN26')['contact_id']);
        // Họ + tên viết đảo.
        $this->assertSame($this->contactId('Trần Thị Tư'), $this->intoFund('Tu Tran chuyen khoan nhanh qua Zalo')['contact_id']);
        // Tên lặp, không bị bậc "chỉ tên" làm nhiễu.
        $this->assertSame($this->contactId('Đặng Thị Thương Thương'), $this->intoFund('DANG THI THUONG THUONG chuyen tien')['contact_id']);
        // Chủ TK quỹ tự đóng thì vẫn nhận ra (tên nằm trong nội dung).
        $this->assertSame($this->contactId('Nguyễn Minh Hiển'), $this->intoFund('NGUYEN MINH HIEN dong quy')['contact_id']);
    }

    public function test_chi_khop_ten_goi_thi_hoi_khong_chon_san(): void
    {
        $this->fundGroup();

        $draft = $this->intoFund('Hung Thuong chuyen khoan nhanh qua Zalo');

        $this->assertNull($draft['contact_id']);
        $this->assertNull($draft['payer_name']);
        $this->assertNotContains($this->contactId('Nguyễn Minh Hiển'), array_column($draft['contact_candidates'], 'id'));
        $this->assertEqualsCanonicalizing(
            ['Vũ Văn Thường', 'Nguyễn Văn Thường B', 'Đặng Thị Thương Thương'],
            array_column($draft['contact_candidates'], 'name'),
        );
    }

    public function test_nhan_ra_stk_quy_khi_bi_che_hoac_chi_co_ten(): void
    {
        $this->fundGroup();

        // Số tài khoản bị che một phần.
        $this->assertSame('income', $this->intoFund('VU DUC TUAN', ['counterparty_account' => '1012***678'])['type']);
        // Không đọc được số tài khoản, tên người nhận trùng tên chủ TK quỹ.
        $this->assertSame('income', $this->intoFund('VU DUC TUAN', ['counterparty_account' => null])['type']);
        // Chuyển cho người khác: vẫn là chi, người nhận là đối tác.
        $draft = $this->resolve(['amount' => 500000, 'screen_type' => 'outgoing', 'counterparty_name' => 'VU VAN THUONG', 'counterparty_account' => '0987654321', 'note' => 'Tham om']);
        $this->assertSame('expense', $draft['type']);
        $this->assertSame($this->contactId('Vũ Văn Thường'), $draft['contact_id']);
        $this->assertNull($draft['campaign_id']);
    }

    public function test_nhieu_chien_dich_dang_mo_thi_khong_tu_gan(): void
    {
        $this->fundGroup();
        Campaign::create(['category_id' => $this->categoryId('Đóng quỹ năm'), 'name' => 'Quỹ khác', 'slug' => 'quy-khac', 'transfer_code' => 'KHAC26']);

        $draft = $this->intoFund('VU DUC TUAN chuyen tien');

        $this->assertSame('income', $draft['type']);
        $this->assertNull($draft['campaign_id']);
    }

    public function test_nhom_chua_khai_stk_thi_bill_chuyen_di_van_la_chi(): void
    {
        Contact::create(['full_name' => 'Vũ Đức Tuấn']);

        $draft = $this->resolve(['amount' => 200000, 'screen_type' => 'outgoing', 'counterparty_name' => 'NGUYEN MINH HIEN', 'counterparty_account' => '1012345678', 'note' => 'VU DUC TUAN chuyen tien']);

        $this->assertSame('expense', $draft['type']);
    }

    public function test_dieu_kien_doc_lai(): void
    {
        $this->assertTrue(BillDraftResolver::needsRetry(['is_bank_receipt' => true, 'amount' => null, 'note' => 'x']));
        $this->assertTrue(BillDraftResolver::needsRetry(['is_bank_receipt' => true, 'amount' => 500000]));
        $this->assertFalse(BillDraftResolver::needsRetry(['is_bank_receipt' => true, 'amount' => '500.000', 'note' => 'x']));
        $this->assertFalse(BillDraftResolver::needsRetry(['is_bank_receipt' => false]));
    }
}
