<?php

namespace App\Console\Commands;

use App\Services\Ai\BillDraftResolver;
use App\Services\Ai\BillExtractor;
use Illuminate\Console\Command;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Chạy thử model đọc bill thật trên bộ ảnh mẫu, in kết quả, độ khớp và chi
 * phí từng ảnh. Xem plan/04 mục 7.
 *
 * Mỗi ảnh có thể kèm file kỳ vọng cùng tên, đuôi .json, ví dụ mb-tham-om.jpg
 * và mb-tham-om.json: {"amount": 500000, "screen_type": "outgoing",
 * "transaction_datetime": "2026-11-08 14:02", "note_contains": "tham om"}.
 */
class AiEvalCommand extends Command
{
    protected $signature = 'cashflow:ai-eval {dir=tests/fixtures/bills : thư mục ảnh bill mẫu} {--model= : model thay cho ai.vision.model}';

    protected $description = 'Chạy thử AI đọc bill trên bộ ảnh mẫu, in độ chính xác và chi phí';

    public function handle(BillExtractor $extractor): int
    {
        if (config('ai.driver') === 'fake') {
            $this->warn('AI_DRIVER=fake: kết quả là dữ liệu giả. Đặt AI_DRIVER=openrouter và OPENROUTER_API_KEY để thử model thật.');
        }

        $dir = base_path($this->argument('dir'));
        $files = glob($dir.'/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG}', GLOB_BRACE) ?: [];
        if ($files === []) {
            $this->error("Không có ảnh nào trong {$dir}.");

            return self::FAILURE;
        }

        $model = $this->option('model') ?: (string) config('ai.vision.model');
        $images = new ImageManager(new Driver);
        $rows = [];
        $checked = 0;
        $passed = 0;
        $totalCost = 0.0;

        foreach ($files as $file) {
            $name = basename($file);
            $started = microtime(true);
            try {
                $max = (int) config('ai.vision.max_dimension', 1600);
                $webp = (string) $images->read($file)->scaleDown($max, $max)->toWebp(80);
                $result = $extractor->extract('data:image/webp;base64,'.base64_encode($webp), $model);
                if (BillDraftResolver::needsRetry($result['data']) && config('ai.vision.fallback_model')) {
                    $result = $extractor->extract('data:image/webp;base64,'.base64_encode($webp), (string) config('ai.vision.fallback_model'));
                    $result['model'] .= ' (dự phòng)';
                }
            } catch (Throwable $e) {
                $rows[] = [$name, 'LỖI', mb_substr($e->getMessage(), 0, 60), '', '', '', '', ''];

                continue;
            }

            $data = $result['data'];
            $cost = (float) ($result['usage']['cost'] ?? 0);
            $totalCost += $cost;
            $verdict = '';

            $expectFile = preg_replace('/\.[^.]+$/', '.json', $file);
            if (is_file($expectFile)) {
                $expect = json_decode((string) file_get_contents($expectFile), true) ?: [];
                $misses = $this->compare($expect, $data);
                $checked++;
                $passed += $misses === [] ? 1 : 0;
                $verdict = $misses === [] ? 'ĐÚNG' : 'SAI: '.implode(', ', $misses);
            }

            $rows[] = [
                $name,
                $result['model'],
                number_format((int) ($data['amount'] ?? 0), 0, ',', '.'),
                $data['screen_type'] ?? '-',
                $data['transaction_datetime'] ?? '-',
                mb_substr((string) ($data['counterparty_name'] ?? '-'), 0, 24),
                mb_substr((string) ($data['note'] ?? '-'), 0, 30),
                sprintf('$%.5f · %.1fs %s', $cost, microtime(true) - $started, $verdict),
            ];
        }

        $this->table(['Ảnh', 'Model', 'Số tiền', 'Loại màn', 'Ngày giờ', 'Đối tác', 'Nội dung', 'Chi phí · thời gian · kết quả'], $rows);
        if ($checked > 0) {
            $this->info("Khớp kỳ vọng: {$passed}/{$checked} ảnh.");
        }
        $this->info(sprintf('Tổng chi phí: $%.5f (~%s đ).', $totalCost, number_format($totalCost * config('ai.usd_vnd'), 0, ',', '.')));

        return self::SUCCESS;
    }

    /** @return list<string> các trường không khớp */
    private function compare(array $expect, array $data): array
    {
        $misses = [];
        if (isset($expect['amount']) && (int) ($data['amount'] ?? 0) !== (int) $expect['amount']) {
            $misses[] = 'số tiền';
        }
        if (array_key_exists('screen_type', $expect) && ($data['screen_type'] ?? null) !== $expect['screen_type']) {
            $misses[] = 'loại màn';
        }
        if (isset($expect['transaction_datetime']) && ! str_starts_with((string) ($data['transaction_datetime'] ?? ''), $expect['transaction_datetime'])) {
            $misses[] = 'ngày giờ';
        }
        if (isset($expect['note_contains']) && ! str_contains(mb_strtolower((string) ($data['note'] ?? '')), mb_strtolower($expect['note_contains']))) {
            $misses[] = 'nội dung';
        }

        return $misses;
    }
}
