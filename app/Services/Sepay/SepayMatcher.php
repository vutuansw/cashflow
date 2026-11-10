<?php

namespace App\Services\Sepay;

use App\Services\Ai\BillDraftResolver;

/**
 * Nhận diện giao dịch SePay: chiến dịch theo mã trong nội dung, người đóng
 * theo tên trong nội dung (rồi tới phần mô tả ngân hàng gửi kèm). Dùng chung
 * bộ quy tắc với AI đọc bill (BillDraftResolver) để hai nơi đoán giống nhau.
 * Xem plan/05 mục 3 bước 6.
 */
class SepayMatcher
{
    public function __construct(private readonly BillDraftResolver $resolver) {}

    /** @return array<string, mixed> bản nháp như BillDraftResolver::resolve */
    public function draft(string $transferType, int $amount, string $occurredAt, ?string $content, ?string $description): array
    {
        $raw = [
            'is_bank_receipt' => true,
            'amount' => $amount,
            'transaction_datetime' => $occurredAt,
            'screen_type' => $transferType === 'in' ? 'incoming' : 'outgoing',
            'note' => $content,
        ];

        $draft = $this->resolver->resolve($raw);

        // Không ra người từ nội dung thì thử phần mô tả (thường có tên người chuyển).
        if ($draft['contact_id'] === null && $draft['contact_candidates'] === [] && $description && $description !== $content) {
            $other = $this->resolver->resolve(['note' => $description] + $raw);
            $draft['contact_id'] = $other['contact_id'];
            $draft['contact_candidates'] = $other['contact_candidates'];
            if ($other['contact_id'] !== null) {
                $draft['ai_fields'][] = 'contact_id';
            }
        }

        // Nội dung CK không phải tên người: bỏ tên tự do resolver đoán ra.
        $draft['payer_name'] = null;
        $draft['ai_fields'] = array_values(array_diff($draft['ai_fields'], ['payer_name']));

        return $draft;
    }
}
