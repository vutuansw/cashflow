<?php

namespace App\Services\Ai;

/**
 * Nơi duy nhất quyết định gọi OpenRouter ở đâu và kèm header gì, như
 * www/farm. Model không thuộc lớp này, mỗi chỗ gọi tự đọc ở config/ai.php.
 */
class AiProvider
{
    public static function apiKey(): string
    {
        return trim((string) config('ai.api_key', ''), " \t\n\r\0\x0B\"'");
    }

    public static function url(): string
    {
        return (string) config('ai.url');
    }

    /** HTTP-Referer và X-Title để OpenRouter ghi nhận lượt gọi thuộc ứng dụng WePo. */
    public static function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.self::apiKey(),
            'Content-Type' => 'application/json',
            'HTTP-Referer' => (string) config('app.url'),
            'X-Title' => (string) config('app.name', 'WePo'),
        ];
    }
}
