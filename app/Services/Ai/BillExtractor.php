<?php

namespace App\Services\Ai;

/**
 * Đọc chữ trên ảnh bill chuyển khoản. Không biết gì về nhóm: việc suy ra thu
 * hay chi, loại, người là của BillDraftResolver. Xem plan/04 mục 2.
 */
interface BillExtractor
{
    /**
     * @param  string  $dataUrl  ảnh dạng data:image/...;base64,...
     * @return array{model: string, data: array<string, mixed>, usage: array{prompt_tokens?: int, completion_tokens?: int, cost?: float}}
     *
     * @throws \RuntimeException khi không gọi được hoặc phản hồi không phải JSON
     */
    public function extract(string $dataUrl, string $model): array;
}
