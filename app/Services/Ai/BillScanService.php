<?php

namespace App\Services\Ai;

use App\Models\BillScan;
use App\Models\Group;
use App\Models\System\GroupService;
use App\Models\User;
use App\Services\Media\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Một lượt "Đọc bill": lưu ảnh làm chứng từ, kiểm tra hạn mức, gọi AI (đọc lại
 * bằng model dự phòng tối đa một lần), suy ra bản nháp, ghi bill_scans kèm
 * token và chi phí. Chạy đồng bộ vì người dùng đang chờ form. Xem plan/04.
 *
 * Ảnh luôn được giữ dù AI đọc lỗi: người dùng nhập tay và vẫn gắn ảnh làm chứng từ.
 * Phiếu chỉ được tạo khi người dùng bấm Lưu (TransactionWriter, bill_scan_id).
 */
class BillScanService
{
    public function __construct(
        private readonly BillExtractor $extractor,
        private readonly BillDraftResolver $resolver,
        private readonly ImageStore $images,
    ) {}

    public function scan(Group $group, User $user, UploadedFile $file): BillScan
    {
        $this->ensureQuota($group);

        $scan = BillScan::forceCreate([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'path' => $this->images->storeBill($group, $file),
            'status' => 'processing',
        ]);

        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'cost' => 0.0];
        $model = (string) config('ai.vision.model');
        $fallbackUsed = false;

        try {
            $dataUrl = 'data:image/webp;base64,'.base64_encode(ImageStore::disk()->get($scan->path));

            $result = $this->extractor->extract($dataUrl, $model);
            $this->addUsage($usage, $result['usage']);

            $fallback = (string) config('ai.vision.fallback_model');
            if (BillDraftResolver::needsRetry($result['data']) && $fallback !== '' && $fallback !== $model) {
                $fallbackUsed = true;
                $model = $fallback;
                $result = $this->extractor->extract($dataUrl, $model);
                $this->addUsage($usage, $result['usage']);
            }

            $scan->forceFill([
                'status' => 'done',
                'model' => $result['model'] ?: $model,
                'fallback_used' => $fallbackUsed,
                'result' => $result['data'],
                'draft' => $this->resolver->resolve($result['data']),
            ]);
        } catch (Throwable $e) {
            Log::warning('Đọc bill lỗi', ['bill_scan_id' => $scan->id, 'group_id' => $group->id, 'error' => $e->getMessage()]);
            $scan->forceFill([
                'status' => 'failed',
                'model' => $model,
                'fallback_used' => $fallbackUsed,
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }

        $scan->forceFill([
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'cost_usd' => $usage['cost'],
        ])->save();

        return $scan;
    }

    /** Lượt đã gọi AI trong tháng này của nhóm (tính cả lượt lỗi vì vẫn tốn tiền). */
    public static function usedThisMonth(Group $group): int
    {
        return BillScan::acrossGroups()
            ->where('group_id', $group->id)
            ->whereNotNull('model')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /** Mặc định không giới hạn; /system đặt ai_monthly_quota cho từng nhóm (plan/04 mục 6). */
    public static function quota(Group $group): ?int
    {
        try {
            return GroupService::query()->where('group_id', $group->id)->value('ai_monthly_quota');
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureQuota(Group $group): void
    {
        $quota = self::quota($group);
        if ($quota !== null && self::usedThisMonth($group) >= $quota) {
            throw ValidationException::withMessages(['bill' => 'Nhóm đã dùng hết lượt đọc bill tháng này. Bạn vẫn nhập tay được.']);
        }
    }

    private function addUsage(array &$total, array $usage): void
    {
        $total['prompt_tokens'] += (int) ($usage['prompt_tokens'] ?? 0);
        $total['completion_tokens'] += (int) ($usage['completion_tokens'] ?? 0);
        $total['cost'] += (float) ($usage['cost'] ?? 0);
    }
}
