<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gọi OpenRouter đọc ảnh bill, dựa trên CashflowVisionExtractor của www/farm:
 * chỉ đọc cái nhìn thấy, temperature 0, bóc JSON chịu được khối mã. Thêm
 * is_bank_receipt, giờ giao dịch và mã tham chiếu cho quỹ nhóm.
 */
class OpenRouterBillExtractor implements BillExtractor
{
    public function extract(string $dataUrl, string $model): array
    {
        if (AiProvider::apiKey() === '') {
            throw new RuntimeException('Chưa cấu hình khóa OpenRouter (OPENROUTER_API_KEY).');
        }

        $response = Http::withHeaders(AiProvider::headers())
            ->timeout((int) config('ai.vision.timeout', 60))
            ->post(AiProvider::url(), [
                'model' => $model,
                'temperature' => 0,
                // Để OpenRouter trả chi phí thật của lượt gọi trong usage.cost.
                'usage' => ['include' => true],
                'messages' => [
                    ['role' => 'system', 'content' => self::prompt()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ['type' => 'text', 'text' => 'Đọc ảnh này và trả về JSON theo đúng cấu trúc đã mô tả.'],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter trả lỗi HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            throw new RuntimeException('Không đọc được phản hồi của model đọc ảnh.');
        }

        return [
            'model' => (string) ($response->json('model') ?: $model),
            'data' => self::decodeJson($content),
            'usage' => (array) $response->json('usage', []),
        ];
    }

    public static function prompt(): string
    {
        return <<<'TEXT'
Bạn đọc ảnh chụp màn hình giao dịch chuyển khoản (ứng dụng ngân hàng hoặc ví điện tử ở Việt Nam) và trả về JSON.

Quy tắc:
- Chỉ đọc những gì nhìn thấy. Ô nào không có trên ảnh thì trả null, tuyệt đối không suy đoán.
- "is_bank_receipt": true nếu đây là màn hình xác nhận chuyển tiền / nhận tiền / biến động số dư; false nếu là ảnh khác.
- "amount": số tiền giao dịch, số nguyên VND, bỏ mọi dấu phân cách hàng nghìn và chữ "VND", "đ".
- "transaction_datetime": ngày giờ thực hiện giao dịch in trên ảnh (không phải giờ điện thoại ở thanh trạng thái), dạng "YYYY-MM-DD HH:MM"; không có giờ thì "YYYY-MM-DD".
- "screen_type":
  - "outgoing": màn hình ĐÃ CHUYỂN TIỀN ĐI ("Chuyển tiền thành công", "Giao dịch thành công"); chỉ in tên người NHẬN (nhãn có thể là "Người nhận", "Người thụ hưởng", "Tên tài khoản nhận").
  - "incoming": màn hình VỪA NHẬN TIỀN ("+500.000", "bạn vừa nhận được tiền từ..."); in tên người CHUYỂN tới.
  - null nếu không chắc.
- "counterparty_name"/"counterparty_account": tên và số tài khoản của phía đối tác (người nhận nếu outgoing, người chuyển nếu incoming), KHÔNG phải chủ tài khoản đang xem.
- "note": nội dung/lời nhắn chuyển khoản, đọc nguyên văn, giữ cả mã viết liền và tên người tự ghi.
- "bank_name": tên ngân hàng hoặc ví thực hiện giao dịch.
- "reference_code": mã giao dịch / mã tham chiếu nếu có.

Trả về DUY NHẤT một object JSON, không giải thích, không bọc trong khối mã:
{
  "is_bank_receipt": boolean,
  "amount": number|null,
  "transaction_datetime": string|null,
  "screen_type": "outgoing"|"incoming"|null,
  "counterparty_name": string|null,
  "counterparty_account": string|null,
  "note": string|null,
  "bank_name": string|null,
  "reference_code": string|null
}
TEXT;
    }

    /** Model đôi khi bọc JSON trong khối mã hoặc viết thêm câu dẫn dù đã dặn không. */
    public static function decodeJson(string $content): array
    {
        $content = trim((string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', trim($content)));
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $content = substr($content, $start, $end - $start + 1);
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Model đọc ảnh trả về nội dung không phải JSON.');
        }

        return $decoded;
    }
}
