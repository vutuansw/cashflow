<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nhà cung cấp
    |--------------------------------------------------------------------------
    |
    | Đọc bill chuyển khoản đi qua OpenRouter, cùng cách làm với www/farm.
    | driver = fake dùng cho máy dev chưa có khóa và cho toàn bộ test.
    | Xem plan/04.
    |
    */

    'driver' => env('AI_DRIVER', 'openrouter'),

    'api_key' => env('OPENROUTER_API_KEY', ''),

    'url' => env('OPENROUTER_URL') ?: 'https://openrouter.ai/api/v1/chat/completions',

    /*
    |--------------------------------------------------------------------------
    | Model đọc ảnh bill
    |--------------------------------------------------------------------------
    |
    | Đây là nguồn sự thật duy nhất cho model, không đặt giá trị dự phòng ở
    | chỗ khác trong code. Dùng '?:' thay cho tham số mặc định của env():
    | .env.example khai sẵn các khóa ở dạng rỗng, mà khóa rỗng thì env() trả
    | chuỗi rỗng chứ không rơi về mặc định.
    |
    | Số đo bên FarmGo: gemini-3.1-flash-lite ~$0,001/ảnh, 3-6 giây, đọc đúng
    | bill chuyển khoản. fallback_model chỉ gọi đúng một lần khi lượt đầu
    | không ra số tiền.
    |
    */

    'vision' => [
        'model' => env('AI_VISION_MODEL') ?: 'google/gemini-3.1-flash-lite',
        'fallback_model' => env('AI_VISION_FALLBACK_MODEL') ?: 'google/gemini-3.7-flash',
        'timeout' => 60,
        'max_dimension' => 1600,
        'max_bytes' => 12 * 1024 * 1024,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
    ],

    // Quy đổi chi phí hiển thị ở /system.
    'usd_vnd' => (int) (env('AI_USD_VND') ?: 26000),

];
