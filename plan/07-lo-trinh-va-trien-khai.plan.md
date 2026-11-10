# 07 — Lộ trình, kiểm thử, triển khai

## 1. Các mốc

Mỗi mốc kết thúc bằng: test xanh, chạy thử trên trình duyệt ở khổ điện thoại, chủ dự án xem và duyệt rồi mới qua mốc sau.

| Mốc | Nội dung | Xong khi |
| --- | --- | --- |
| **M0 Khởi tạo** | Laravel 13 + Vue + Tailwind 4 + Vite 8 giống chatbot; git; hai connection và hai bộ migration; `config/cashflow.php`, `config/ai.php`; trait `BelongsToGroup`, middleware `ResolveGroup`; mã hóa/hash SĐT; README và `.env.example` | `composer test` chạy được, `cashflow:migrate` tạo đủ bảng trên 2 DB |
| **M1 Tài khoản & nhóm** | Đăng ký nhóm, đăng nhập SĐT, quên/đổi mật khẩu, chọn nhóm, cài đặt nhóm, danh bạ (thêm, nhập hàng loạt), cấp tài khoản, vai trò, loại thu chi mặc định | Trưởng nhóm đăng ký → thêm 30 người bằng dán từ Excel → cấp tài khoản cho 1 QTV → QTV đăng nhập được |
| **M2 Thu chi & timeline** | Wireframe duyệt trước; phiếu thu chi + ảnh sự kiện + ảnh bill; lịch sử sửa; timeline public/private; tim, bình luận; dashboard; số dư đầu kỳ | Tạo phiếu có ảnh trên điện thoại, xem timeline bằng link khi chưa đăng nhập (public) và bị chặn (private) |
| **M3 AI đọc bill** | OpenRouter extractor + resolver + bill_scans; giao diện đọc bill; lệnh `cashflow:ai-eval` | Đọc đúng bộ bill mẫu chủ dự án cung cấp, form điền sẵn, bấm Lưu thành phiếu |
| **M4 Chiến dịch & SePay** | Chiến dịch, danh sách phải đóng, trang chiến dịch + QR; cài đặt SePay; webhook; hàng chờ duyệt; email thông báo | Chuyển khoản thật 2.000đ với nội dung mã chiến dịch → vào hàng chờ → duyệt → người đó chuyển sang "đã đóng" |
| **M5 Báo cáo, PWA, /system** | Báo cáo + xuất Excel; PWA; toàn bộ `/system` (duyệt, khóa, sửa, xóa, tạo nhóm, chi phí AI, nhật ký) | Xuất sổ quỹ mở được bằng Excel; cài PWA lên Android và iPhone; khóa nhóm từ /system thì nhóm và webhook bị chặn |
| **M6 Deploy & nghiệm thu** | Server, domain, SSL, worker, cron, sao lưu; chạy thử với 1–2 nhóm thật | Nhóm thật dùng 1 tuần không lỗi chặn |

## 2. Kiểm thử

- PHPUnit Feature + Unit, database test riêng `cashflow_test_groups`, `cashflow_test_system` (TestCase từ chối chạy nếu tên database không bắt đầu bằng `cashflow_test_`) (MySQL thật, không SQLite, vì dùng hai connection và kiểu enum/json).
- Ca bắt buộc:
  - Cô lập nhóm: người nhóm A không đọc/sửa phiếu, danh bạ, ảnh, chiến dịch, hàng chờ của nhóm B (thử đổi id trên URL/API).
  - Phân quyền từng vai trò theo bảng ở [02](02-tai-khoan-va-phan-quyen.plan.md).
  - Nhóm private: khách không xem được timeline, ảnh, trang chiến dịch.
  - SĐT lưu trong DB không phải bản rõ; đăng nhập theo hash; trùng SĐT.
  - Số dư đúng khi tạo/sửa/xóa/khôi phục phiếu.
  - Webhook SePay theo [05](05-chien-dich-va-sepay.plan.md).
  - Resolver AI theo [04](04-ai-doc-bill.plan.md), AI luôn dùng driver `fake` trong test.
- `pint` trước mỗi commit.

## 3. Môi trường local

```env
APP_NAME="WePo"
APP_URL=http://127.0.0.1:8000
APP_LOCALE=vi
DB_CONNECTION=groups
DB_GROUPS_DATABASE=cashflow_groups
DB_SYSTEM_DATABASE=cashflow_system
PHONE_HASH_KEY=
QUEUE_CONNECTION=database
AI_DRIVER=fake
OPENROUTER_API_KEY=
MAIL_MAILER=log
```

`composer dev` chạy server, queue, vite cùng lúc như chatbot. Seeder `DemoSeeder` tạo nhóm `nhomdemo` (public) và `chidoan-a12` (private) với danh bạ, phiếu, ảnh, chiến dịch mẫu.

## 4. Triển khai

Cùng VPS với chatbot, theo khuôn `www/chatbot/deploy`:

| Hạng mục | Giá trị |
| --- | --- |
| Thư mục | `/var/www/cashflow` |
| Domain | `wepo.hipotech.vn` (bản ghi A trỏ về VPS, SSL Let's Encrypt) |
| PHP | `php8.4-fpm` dùng chung với chatbot, cần thêm `php8.4-gd` cho xử lý ảnh |
| Database | `cashflow_groups`, `cashflow_system`, user MySQL `cashflow` chỉ có quyền trên hai DB này |
| Nginx | vhost riêng, `client_max_body_size 20m` (tải ảnh), root `public/` |
| Hàng đợi | supervisor `cashflow-worker` (`queue:work --queue=default`) |
| Cron | `schedule:run` mỗi phút: dọn bill mồ côi, xóa hẳn nhóm đã xóa quá 30 ngày, dọn phiếu xóa mềm quá 30 ngày |
| Ảnh | `storage/app/private/groups`, nằm ngoài thư mục deploy được ghi đè |
| Sao lưu | mysqldump hai DB hằng ngày + rsync thư mục ảnh; **sao lưu riêng `APP_KEY` và `PHONE_HASH_KEY`** |
| Deploy | `deploy/deploy.sh` (git pull, composer install --no-dev, npm ci && npm run build, migrate --force cả 2 DB, config/route/view cache, restart worker); GitHub Actions nếu có repo |

`deploy/README.md` hướng dẫn dựng server lần đầu, giống chatbot.

## 5. Bước tiếp theo

1. Chủ dự án đọc và duyệt bộ plan (sửa thẳng vào file hoặc ghi chú lại).
2. Cung cấp các mục ở [plan.md mục 6](plan.md#6-còn-cần-chủ-dự-án-cung-cấp-trước-khi-deploy) (không chặn M0–M2).
3. Bắt đầu M0.
