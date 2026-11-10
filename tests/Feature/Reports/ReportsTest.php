<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Campaigns\CampaignService;
use App\Support\CurrentGroup;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function add(Group $group, string $type, string $category, int $amount, string $at, array $extra = []): void
    {
        Transaction::forceCreate($extra + [
            'group_id' => $group->id, 'type' => $type, 'amount' => $amount, 'occurred_at' => $at, 'title' => "{$category} {$amount}",
            'category_id' => Category::acrossGroups()->where('group_id', $group->id)->where('name', $category)->value('id'),
        ]);
    }

    private function seedLedger(): Group
    {
        $group = $this->makeGroup(['code' => 'chi-doan-a12', 'opening_balance' => 1_000_000]);
        $this->add($group, 'income', 'Đóng quỹ năm', 2_000_000, '2025-12-20 09:00');
        $this->add($group, 'income', 'Đóng quỹ năm', 500_000, '2026-01-05 09:00');
        $this->add($group, 'expense', 'Liên hoan', 800_000, '2026-01-20 19:00');
        $this->add($group, 'expense', 'Thăm ốm', 200_000, '2026-03-02 10:00');
        $this->add($group, 'expense', 'Thăm ốm', 999_000, '2026-02-01 10:00', ['deleted_at' => now()]);

        return $group;
    }

    public function test_so_quy_so_du_dau_ky_ton_luy_ke_cuoi_ky(): void
    {
        $group = $this->seedLedger();
        $this->actingAs($this->ownerOf($group));

        $r = $this->getJson($this->adminUrl($group, 'reports/so-quy?from=2026-01-01&to=2026-03-31'))->assertOk()->json();
        $this->assertSame(3_000_000, $r['opening']);
        $this->assertSame([3_500_000, 2_700_000, 2_500_000], array_column($r['rows'], 'balance'));
        $this->assertSame(500_000, $r['income']);
        $this->assertSame(1_000_000, $r['expense']);
        $this->assertSame(2_500_000, $r['closing']);
        $this->assertSame('01/01/2026 – 31/03/2026', $r['period']);
    }

    public function test_theo_loai_va_theo_thang(): void
    {
        $group = $this->seedLedger();
        $this->actingAs($this->ownerOf($group));

        $c = $this->getJson($this->adminUrl($group, 'reports/theo-loai?from=2026-01-01&to=2026-12-31'))->assertOk()->json();
        $this->assertSame(1_000_000, $c['expense']['total']);
        $this->assertSame(['Liên hoan', 'Thăm ốm'], array_column($c['expense']['rows'], 'category'));
        $this->assertSame(80.0, (float) $c['expense']['rows'][0]['percent']);

        $m = $this->getJson($this->adminUrl($group, 'reports/theo-thang?from=2026-01-01&to=2026-03-31'))->assertOk()->json();
        $this->assertCount(3, $m['rows']);
        $this->assertSame(['income' => 500_000, 'expense' => 800_000, 'diff' => -300_000], array_intersect_key($m['rows'][0], array_flip(['income', 'expense', 'diff'])));
        $this->assertSame(0, $m['rows'][1]['expense']);
    }

    public function test_xuat_so_quy_excel_mo_duoc(): void
    {
        $group = $this->seedLedger();
        $this->actingAs($this->ownerOf($group));

        $response = $this->get($this->adminUrl($group, 'reports/so-quy?from=2026-01-01&to=2026-12-31&format=xlsx'))->assertOk();
        $response->assertDownload('wepo-chi-doan-a12-so-quy-2026.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'qn').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertStringContainsString('Sổ quỹ năm 2026', $sheet->getCell('A1')->getValue());
        $this->assertSame('Số dư đầu kỳ', $sheet->getCell('A4')->getValue());
        $this->assertEquals(3_000_000, $sheet->getCell('H4')->getValue());
        $this->assertSame('#,##0', $sheet->getStyle('H4')->getNumberFormat()->getFormatCode());
        $this->assertSame('Số dư cuối kỳ', $sheet->getCell('A9')->getValue());
        $this->assertEquals(2_500_000, $sheet->getCell('H9')->getValue());
        $this->assertTrue($sheet->getStyle('A9')->getFont()->getBold());
        $this->assertSame('A4', $sheet->getFreezePane());
        @unlink($path);
    }

    public function test_bao_cao_chien_dich_va_excel(): void
    {
        $group = $this->makeGroup();
        app(CurrentGroup::class)->set($group);
        Contact::create(['full_name' => 'Lê Văn An']);
        $campaign = app(CampaignService::class)->create($group, $this->ownerOf($group), [
            'name' => 'Thu quỹ 2026', 'category_id' => Category::where('name', 'Đóng quỹ năm')->value('id'), 'amount_per_member' => 500_000,
        ]);
        $this->actingAs($this->ownerOf($group));

        $r = $this->getJson($this->adminUrl($group, "reports/campaign?campaign_id={$campaign->id}"))->assertOk()->json();
        $this->assertSame(1_000_000, $r['expected_total']);
        $this->assertSame('Chưa đóng', $r['rows'][0]['status']);
        $this->assertSame(500_000, $r['rows'][0]['missing']);

        $this->get($this->adminUrl($group, "reports/campaign?campaign_id={$campaign->id}&format=xlsx"))->assertOk()
            ->assertDownload('wepo-'.$group->code.'-chien-dich-thu-quy-2026.xlsx');
    }

    public function test_thanh_vien_khong_xem_bao_cao(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->memberOf($group))->getJson($this->adminUrl($group, 'reports/so-quy'))->assertForbidden();
    }

    public function test_nhap_danh_ba_tu_file_excel(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $this->get($this->adminUrl($group, 'contacts/import/template'))->assertOk()->assertDownload('wepo-mau-danh-ba.xlsx');

        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['Họ tên', 'SĐT', 'Ngày sinh', 'Địa chỉ'],
            ['Nguyễn Thị Lan', 912345678, 34763, 'Hà Nội'], // Excel mất số 0, ngày là số sê-ri
            ['Trần Văn Minh', '0977100001', '05/06/1996', ''],
            ['', '', '', ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'qn').'.xlsx';
        (new Xlsx($book))->save($path);

        $rows = $this->post($this->adminUrl($group, 'contacts/import/file'), [
            'file' => new UploadedFile($path, 'danh-ba.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ], ['Accept' => 'application/json'])->assertOk()->json('rows');

        $this->assertCount(2, $rows);
        $this->assertSame('0912345678', $rows[0]['phone']);
        $this->assertSame('1995-03-05', $rows[0]['birthday']);
        $this->assertSame('1996-06-05', $rows[1]['birthday']);
        $this->assertSame([], $rows[0]['errors']);
        @unlink($path);
    }
}
