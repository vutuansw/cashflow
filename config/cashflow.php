<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tên sản phẩm
    |--------------------------------------------------------------------------
    |
    | "Quỹ nhóm" là tên hiển thị; tên kỹ thuật "cashflow" nằm ở thư mục,
    | namespace và database, không đổi theo tên sản phẩm. Xem plan/plan.md.
    |
    */

    'brand' => [
        'name' => env('APP_NAME', 'Quỹ nhóm'),
        'description' => 'Quản lý quỹ tiền thu chi của hội nhóm theo dòng thời gian.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Loại thu chi mặc định
    |--------------------------------------------------------------------------
    |
    | Seed vào bảng categories khi lập nhóm mới, xem plan/03 mục 1. Nhóm sửa
    | tên, thêm, ẩn được; đây chỉ là bộ khởi đầu.
    |
    */

    'default_categories' => [
        'income' => [
            ['name' => 'Đóng quỹ năm', 'icon' => 'piggy-bank', 'color' => '#16a34a'],
        ],
        'expense' => [
            ['name' => 'Liên hoan', 'icon' => 'utensils', 'color' => '#f59e0b'],
            ['name' => 'Đám hiếu', 'icon' => 'flower-2', 'color' => '#64748b'],
            ['name' => 'Đám hỉ', 'icon' => 'heart', 'color' => '#ec4899'],
            ['name' => 'Thăm ốm', 'icon' => 'hospital', 'color' => '#0ea5e9'],
            ['name' => 'Thăm đẻ', 'icon' => 'baby', 'color' => '#a855f7'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mã nhóm
    |--------------------------------------------------------------------------
    |
    | Mã nhóm nằm ngay sau domain (quynhom.hipotech.vn/chidoan-a12) nên không
    | được trùng đường dẫn của ứng dụng. Xem plan/plan.md mục 4.
    |
    */

    'group_code' => [
        'pattern' => '/^[a-z0-9](?:[a-z0-9-]{1,30})[a-z0-9]$/',
        'reserved' => [
            'system', 'api', 'up', 'nhom', 'dang-nhap', 'dang-ky', 'dang-xuat', 'quen-mat-khau',
            'dat-lai-mat-khau', 'doi-mat-khau', 'ca-nhan', 'gioi-thieu', 'lien-he', 'dieu-khoan',
            'build', 'storage', 'media', 'assets', 'fonts', 'icons', 'images', 'offline',
            'manifest', 'sw', 'favicon', 'robots', 'sitemap', 'admin', 'quan-tri', 'webhooks',
            'sepay', 'login', 'logout', 'register', 'www', 'mail', 'help', 'huong-dan',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bảo mật dữ liệu cá nhân
    |--------------------------------------------------------------------------
    |
    | SĐT, địa chỉ, số tài khoản mã hóa bằng APP_KEY (cast encrypted). Riêng
    | SĐT có thêm cột hash HMAC bằng khóa PHONE_HASH_KEY để đăng nhập và tra
    | trùng. Mất một trong hai khóa là mất dữ liệu: sao lưu cả hai ra ngoài
    | server. Xem plan/01 mục 3.
    |
    */

    'phone_hash_key' => env('PHONE_HASH_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Giới hạn
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // Số nhóm một tài khoản được tự tạo; /system ghi đè bằng platform_settings.
        'groups_per_user' => 5,
        'photos_per_transaction' => 10,
        'photo_upload_max_kb' => 10 * 1024,
    ],

    // Bao lâu thì service_status của nhóm (đọc từ cashflow_system) được cache.
    'service_status_cache_seconds' => 60,

];
