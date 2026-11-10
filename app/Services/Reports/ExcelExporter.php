<?php

namespace App\Services\Reports;

use App\Models\Group;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ghi báo cáo ra file .xlsx bằng PhpSpreadsheet: tiền định dạng #,##0, dòng
 * tổng in đậm, cố định hàng tiêu đề. Tên file wepo-{ma-nhom}-{bao-cao}-{ky}.xlsx.
 * Xem plan/03 mục 7.
 */
class ExcelExporter
{
    private const MONEY = '#,##0';

    public function ledger(Group $group, array $report, string $period, string $file): StreamedResponse
    {
        $rows = [['Số dư đầu kỳ', '', '', '', '', null, null, $report['opening']]];
        foreach ($report['rows'] as $r) {
            $rows[] = [$this->date($r['date']), $r['title'], $r['category'], $r['person'], $r['campaign'], $r['income'] ?: null, $r['expense'] ?: null, $r['balance']];
        }
        $rows[] = ['Cộng phát sinh', '', '', '', '', $report['income'], $report['expense'], null];
        $rows[] = ['Số dư cuối kỳ', '', '', '', '', null, null, $report['closing']];

        return $this->download($group, 'so-quy', $period, "Sổ quỹ {$period}",
            ['Ngày', 'Nội dung', 'Loại', 'Người đóng/nhận', 'Chiến dịch', 'Thu', 'Chi', 'Tồn'],
            $rows, moneyColumns: [6, 7, 8], boldRows: [0, count($rows) - 2, count($rows) - 1], dateColumn: 1, widths: [17, 38, 16, 24, 22, 14, 14, 15], file: $file);
    }

    public function categories(Group $group, array $report, string $period, string $file): StreamedResponse
    {
        $rows = [];
        foreach (['income' => 'THU', 'expense' => 'CHI'] as $type => $label) {
            foreach ($report[$type]['rows'] as $r) {
                $rows[] = [$label, $r['category'], $r['count'], $r['total'], $r['percent'] / 100];
            }
            $rows[] = ["Tổng {$label}", '', null, $report[$type]['total'], null];
        }
        $bold = [];
        foreach ($rows as $i => $row) {
            if (str_starts_with((string) $row[0], 'Tổng')) {
                $bold[] = $i;
            }
        }

        return $this->download($group, 'theo-loai', $period, "Tổng hợp theo loại {$period}",
            ['Thu/Chi', 'Loại', 'Số phiếu', 'Số tiền', 'Tỷ lệ'],
            $rows, moneyColumns: [4], boldRows: $bold, percentColumn: 5, widths: [10, 26, 10, 16, 10], file: $file);
    }

    public function months(Group $group, array $report, string $period, string $file): StreamedResponse
    {
        $rows = array_map(fn ($r) => [$r['label'], $r['income'], $r['expense'], $r['diff']], $report['rows']);
        $rows[] = ['Tổng', $report['income'], $report['expense'], $report['income'] - $report['expense']];

        return $this->download($group, 'theo-thang', $period, "Thu chi theo tháng {$period}",
            ['Tháng', 'Thu', 'Chi', 'Chênh lệch'],
            $rows, moneyColumns: [2, 3, 4], boldRows: [count($rows) - 1], widths: [16, 16, 16, 16], file: $file);
    }

    public function campaign(Group $group, array $report): StreamedResponse
    {
        $rows = array_map(fn ($r) => [
            $r['name'].($r['in_list'] ? '' : ' (ngoài danh sách)'), $r['expected'], $r['paid'], $r['missing'],
            $r['last_paid_at'] ? $this->date($r['last_paid_at']) : null, $r['status'],
        ], $report['rows']);
        $rows[] = ['Tổng', $report['expected_total'], $report['collected'], array_sum(array_column($report['rows'], 'missing')), null, "{$report['members_paid']}/{$report['members_total']} người đủ"];

        $slug = Str::slug($report['campaign']['name']);

        return $this->download($group, "chien-dich-{$slug}", '', 'Chiến dịch '.$report['campaign']['name'],
            ['Họ tên', 'Phải đóng', 'Đã đóng', 'Còn thiếu', 'Ngày đóng gần nhất', 'Trạng thái'],
            $rows, moneyColumns: [2, 3, 4], boldRows: [count($rows) - 1], dateColumn: 5, widths: [30, 14, 14, 14, 18, 22], dateFormat: 'dd/mm/yyyy');
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<int>  $moneyColumns  cột (1-based) định dạng tiền
     * @param  list<int>  $boldRows  dòng dữ liệu (0-based) in đậm
     */
    private function download(
        Group $group, string $name, string $period, string $title, array $headers, array $rows,
        array $moneyColumns = [], array $boldRows = [], ?int $dateColumn = null, ?int $percentColumn = null, array $widths = [],
        string $dateFormat = 'dd/mm/yyyy hh:mm', string $file = '',
    ): StreamedResponse {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('WePo')->setTitle($title);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title), 0, 31));

        // Dòng 1: tên nhóm + tên báo cáo; dòng 3: tiêu đề cột; dữ liệu từ dòng 4.
        $sheet->setCellValue('A1', "{$group->name} · {$title}");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->fromArray($headers, null, 'A3');
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $header = $sheet->getStyle("A3:{$last}3");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F3EC');
        $sheet->freezePane('A4');

        $sheet->fromArray($rows, null, 'A4', true);
        $end = 3 + max(count($rows), 1);

        foreach ($moneyColumns as $col) {
            $c = Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle("{$c}4:{$c}{$end}")->getNumberFormat()->setFormatCode(self::MONEY);
        }
        if ($dateColumn) {
            $c = Coordinate::stringFromColumnIndex($dateColumn);
            $sheet->getStyle("{$c}4:{$c}{$end}")->getNumberFormat()->setFormatCode($dateFormat);
            $sheet->getStyle("{$c}4:{$c}{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        if ($percentColumn) {
            $c = Coordinate::stringFromColumnIndex($percentColumn);
            $sheet->getStyle("{$c}4:{$c}{$end}")->getNumberFormat()->setFormatCode('0.0%');
        }
        foreach ($boldRows as $i) {
            $sheet->getStyle('A'.(4 + $i).":{$last}".(4 + $i))->getFont()->setBold(true);
        }
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        }
        $this->footer($sheet, $end + 2);

        $filename = 'wepo-'.$group->code.'-'.$name.($file !== '' ? '-'.$file : '').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function footer(Worksheet $sheet, int $row): void
    {
        $sheet->setCellValue("A{$row}", 'Xuất từ WePo lúc '.now()->format('d/m/Y H:i'));
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');
    }

    /** "2026-11-08 14:02" thành số ngày của Excel để lọc/sắp xếp được. */
    private function date(string $value): float
    {
        return ExcelDate::PHPToExcel(new \DateTime($value, new \DateTimeZone(config('app.timezone'))));
    }
}
