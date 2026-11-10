<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Group;
use App\Services\Reports\ExcelExporter;
use App\Services\Reports\ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Báo cáo: sổ quỹ, theo loại, theo tháng, chiến dịch. ?format=xlsx thì tải
 * file Excel, không thì trả JSON cho màn hình. Xem plan/03 mục 7.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportBuilder $reports, private readonly ExcelExporter $excel) {}

    public function show(Request $request, Group $group, string $type)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'campaign_id' => [$type === 'campaign' ? 'required' : 'nullable', 'integer'],
            'format' => ['nullable', 'in:json,xlsx'],
        ], ['to.after_or_equal' => 'Ngày kết thúc phải sau ngày bắt đầu.']);

        $from = Carbon::parse($data['from'] ?? now()->startOfYear()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->startOfDay();
        $xlsx = ($data['format'] ?? 'json') === 'xlsx';

        // Nhãn kỳ: cả năm thì "năm 2026", còn lại "01/01/2026 – 31/03/2026".
        $wholeYear = $from->isSameDay($from->copy()->startOfYear()) && ($to->isSameDay($to->copy()->endOfYear()) || $to->isToday()) && $from->year === $to->year;
        $period = $wholeYear ? "năm {$from->year}" : $from->format('d/m/Y').' – '.$to->format('d/m/Y');
        $file = $wholeYear ? (string) $from->year : $from->format('Ymd').'-'.$to->format('Ymd');

        return match ($type) {
            'so-quy' => $xlsx
                ? $this->excel->ledger($group, $this->reports->ledger($group, $from, $to), $period, $file)
                : $this->reports->ledger($group, $from, $to) + ['period' => $period],
            'theo-loai' => $xlsx
                ? $this->excel->categories($group, $this->reports->categories($from, $to), $period, $file)
                : $this->reports->categories($from, $to) + ['period' => $period],
            'theo-thang' => $xlsx
                ? $this->excel->months($group, $this->reports->months($from, $to), $period, $file)
                : $this->reports->months($from, $to) + ['period' => $period],
            'campaign' => $xlsx
                ? $this->excel->campaign($group, $this->reports->campaign(Campaign::findOrFail($data['campaign_id'])))
                : $this->reports->campaign(Campaign::findOrFail($data['campaign_id'])),
        };
    }
}
