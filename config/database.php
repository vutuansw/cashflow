<?php

use Pdo\Mysql;

$mysql = fn (string $prefix, string $database) => [
    'driver' => 'mysql',
    'host' => env("DB_{$prefix}HOST", env('DB_HOST', '127.0.0.1')),
    'port' => env("DB_{$prefix}PORT", env('DB_PORT', '3306')),
    'database' => $database,
    'username' => env("DB_{$prefix}USERNAME", env('DB_USERNAME', 'cashflow')),
    'password' => env("DB_{$prefix}PASSWORD", env('DB_PASSWORD', '')),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => 'InnoDB',
    // Cùng múi giờ với config('app.timezone'): ngày thu chi đọc thẳng ra là giờ Việt Nam.
    'timezone' => '+07:00',
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Quỹ nhóm có đúng hai connection, xem plan/01:
    |
    | - groups (mặc định): cashflow_groups, dữ liệu khách hàng của mọi nhóm,
    |   phân tách bằng group_id. Bảng hệ thống của Laravel (sessions, jobs,
    |   cache) cũng nằm ở đây.
    | - system: cashflow_system, quản trị viên HIPOTECH, trạng thái dịch vụ
    |   của từng nhóm, nhật ký quản trị. Model ở đây khai báo connection
    |   'system' tường minh.
    |
    | Hai database không có khóa ngoại nối nhau. Collation utf8mb4_unicode_ci
    | giống chatbot: chạy được trên cả MySQL 8 (máy dev) lẫn MariaDB (server).
    |
    */

    'default' => env('DB_CONNECTION', 'groups'),

    'connections' => [
        'groups' => $mysql('', env('DB_GROUPS_DATABASE', 'cashflow_groups')),
        'system' => $mysql('SYSTEM_', env('DB_SYSTEM_DATABASE', 'cashflow_system')),
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];
