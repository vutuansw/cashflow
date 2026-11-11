<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tên sản phẩm
    |--------------------------------------------------------------------------
    |
    | "WePo" là tên hiển thị, "Quỹ nhóm" là dòng phụ nói app làm gì; tên kỹ
    | thuật "cashflow" nằm ở thư mục, namespace và database, không đổi theo
    | tên sản phẩm. Xem plan/plan.md và plan/09.
    |
    */

    'brand' => [
        'name' => env('APP_NAME', 'WePo'),
        // Tiêu đề trang mặc định: "<tagline> - <name>".
        'tagline' => 'Quản lý quỹ nhóm cùng nhau',
        'description' => 'Quản lý quỹ tiền thu chi của hội nhóm theo dòng thời gian.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Landing page trang chủ
    |--------------------------------------------------------------------------
    |
    | Xem plan/14. demo_group: mã nhóm công khai cho nút "Xem nhóm mẫu", để trống
    | thì ẩn nút. Server thật tạo nhóm demo bằng tay, không chạy DemoSeeder.
    |
    */
    'landing' => [
        'demo_group' => env('LANDING_DEMO_GROUP'),
        'description' => 'Mỗi khoản thu chi là một bài đăng trên dòng thời gian của nhóm. Đóng quỹ bằng mã QR, ngân hàng báo về là WePo tự ghi sổ, AI đọc bill và tự làm báo cáo.',
        'image' => '/landing/og.png',
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
    | Mã nhóm nằm ngay sau domain (wepo.hipotech.vn/chidoan-a12) nên không
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
            'groups', 'profile', 'forgot-password', 'reset-password', 'change-password',
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
        // Bài đăng thành viên (plan/21): số ảnh mỗi bài, số bài mỗi giờ mỗi người.
        'photos_per_status' => 10,
        'statuses_per_hour' => 10,
        'photo_upload_max_kb' => 10 * 1024,
    ],

    // Từ khóa (đã bỏ dấu) trong nội dung chuyển khoản để đoán loại thu chi khi
    // AI đọc bill, xem plan/04 mục 4. Khóa là tên loại mặc định; loại nhóm tự
    // thêm thì so theo chính tên loại. Cụm dài khớp trước cụm ngắn.
    'bill_keywords' => [
        'Đám hiếu' => ['dam hieu', 'vieng', 'phung', 'tang le', 'dam tang', 'chia buon', 'hieu'],
        'Đám hỉ' => ['dam cuoi', 'dam hi', 'dam hy', 'mung cuoi', 'cuoi', 'hy su', 'hi', 'hy'],
        'Thăm ốm' => ['tham om', 'om dau', 'nam vien', 'benh vien', 'benh', 'om'],
        'Thăm đẻ' => ['tham de', 'sinh con', 'em be', 'mung em be', 'day thang', 'de'],
        'Liên hoan' => ['lien hoan', 'an uong', 'di an', 'tiec', 'nhau', 'buffet', 'lau'],
        'Đóng quỹ năm' => ['dong quy', 'nop quy', 'quy nam', 'quy'],
    ],

    // Xử lý ảnh tải lên, xem plan/03 mục 3.
    'images' => [
        'large' => 1600,
        'thumb' => 480,
        'cover' => 1600,
        'avatar' => 400,
        'quality' => 80,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    // Phiếu xóa mềm khôi phục được trong bao nhiêu ngày.
    'restore_days' => 30,

    // Cache số liệu tổng hợp của timeline/dashboard (giây).
    'stats_cache_seconds' => 300,

    // Bao lâu thì service_status của nhóm (đọc từ cashflow_system) được cache.
    'service_status_cache_seconds' => 60,

];
