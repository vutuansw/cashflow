<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillScan;
use App\Models\Group;
use App\Services\Ai\BillScanService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** "Đọc bill chuyển khoản": tải ảnh lên, AI đọc, trả bản nháp để điền form. Xem plan/04 mục 1. */
class BillScanController extends Controller
{
    public function store(Request $request, Group $group, BillScanService $service)
    {
        $request->validate([
            'bill' => ['required', 'file', 'mimes:'.implode(',', config('cashflow.images.mimes')), 'max:'.config('cashflow.limits.photo_upload_max_kb')],
        ], [], ['bill' => 'ảnh bill']);

        $key = 'bill-scan:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('ai.vision.per_minute', 10))) {
            throw ValidationException::withMessages(['bill' => 'Bạn đọc bill nhanh quá, đợi một chút rồi thử lại.']);
        }
        RateLimiter::hit($key, 60);

        $scan = $service->scan($group, $request->user(), $request->file('bill'));

        return response()->json($this->present($group, $scan), 201);
    }

    public function show(Group $group, BillScan $scan)
    {
        return $this->present($group, $scan);
    }

    public function present(Group $group, BillScan $scan): array
    {
        $draft = $scan->draft ?? [];
        $readable = $scan->status === 'done' && ($draft['is_bill'] ?? false) && ! empty($draft['amount']);

        return [
            'id' => $scan->id,
            'status' => $scan->status,
            'bill_url' => $scan->path ? MediaUrl::bill($group, $scan) : null,
            'attached' => $scan->transaction_id !== null,
            'readable' => $readable,
            'draft' => $readable ? $draft : null,
            'message' => match (true) {
                $readable => 'AI đã đọc xong. Kiểm tra lại các ô có nhãn "AI đọc" rồi bấm Lưu.',
                $scan->status === 'done' && ! ($draft['is_bill'] ?? false) => 'Ảnh này không giống bill chuyển khoản, bạn nhập tay nhé. Ảnh vẫn được giữ làm chứng từ.',
                default => 'Không đọc được bill, bạn nhập tay nhé. Ảnh vẫn được giữ làm chứng từ.',
            },
        ];
    }
}
