# Quỹ nhóm

**Quản lý quỹ tiền thu chi của hội nhóm theo dòng thời gian.**

Tên sản phẩm là **Quỹ nhóm**, tên kỹ thuật là `cashflow` (thư mục, namespace, database). Chạy chính thức tại `https://quynhom.hipotech.vn`.

Toàn bộ thiết kế nằm trong [`plan/`](plan/plan.md). Đổi thiết kế thì sửa plan trước rồi mới sửa code.

## Trạng thái

Xong **M0 — Khởi tạo**, **M1 — Tài khoản & nhóm** và phần lớn **M2 — Thu chi & timeline** (xem [plan/07](plan/07-lo-trinh-va-trien-khai.plan.md)): đăng ký nhóm, đăng nhập bằng SĐT, danh bạ, cấp tài khoản; phiếu thu chi kèm ảnh sự kiện và ảnh bill (chỉ QTV xem), lịch sử sửa, xóa/khôi phục 30 ngày; timeline công khai/riêng tư có gộp phiếu chiến dịch theo ngày, tim, bình luận; thống kê và trang Tổng quan; ảnh bìa/đại diện, Open Graph. Chiến dịch + SePay (M4), AI đọc bill (M3), báo cáo/PWA/`/system` (M5) chưa làm.

Dữ liệu mẫu: `php artisan db:seed --class=DemoSeeder` tạo nhóm `nhomdemo` (công khai) và `chidoan-a12` (riêng tư). Tài khoản mẫu ghi ở đầu [DemoSeeder](database/seeders/DemoSeeder.php).

## Công nghệ

PHP 8.4, Laravel 13, MySQL/MariaDB, Vue 3.5 + vue-router, Vite 8, Tailwind CSS 4, icon lucide. Cùng phiên bản với `www/chatbot` để dùng chung server. AI đọc bill qua OpenRouter giống `www/farm`.

## Cài đặt máy dev

Cần PHP 8.4, Composer, Node 22.12+, MySQL 8.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan cashflow:key
```

Điền `DB_USERNAME`, `DB_PASSWORD` trong `.env`, tạo bốn database (hai cho chạy thật, hai cho test):

```sql
CREATE DATABASE cashflow_groups      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cashflow_system      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cashflow_test_groups CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cashflow_test_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Rồi:

```bash
php artisan cashflow:migrate
composer dev
```

## Lệnh riêng

| Lệnh | Việc |
| --- | --- |
| `php artisan cashflow:migrate [--fresh] [--seed] [--force]` | Migrate cả `cashflow_groups` (`database/migrations`) và `cashflow_system` (`database/migrations/system`) |
| `php artisan cashflow:key` | Sinh `PHONE_HASH_KEY` vào `.env`, không ghi đè khóa đã có |
| `composer test` | Chạy toàn bộ test trên `cashflow_test_*` |

## Nguyên tắc bất biến

1. **Mọi dữ liệu nhóm lọc theo `group_id`.** Model có cột `group_id` dùng trait `BelongsToGroup`: tự lọc theo nhóm hiện tại do middleware `group` (`ResolveGroup`) đặt từ mã nhóm trên URL. Chưa có nhóm hiện tại thì truy vấn không trả dòng nào; console và `/system` muốn đọc chéo nhóm phải gọi `Model::acrossGroups()`.
2. **Tiền là số nguyên VND**, thu và chi đều dương, phân biệt bằng `type`.
3. **Số dư không lưu cứng**: số dư đầu kỳ + tổng thu − tổng chi.
4. **AI và SePay không tự ghi sổ**: chỉ tạo bản nháp/hàng chờ, người bấm Lưu/Duyệt chịu trách nhiệm.
5. **SĐT, địa chỉ, số tài khoản mã hóa** (cast `encrypted`). SĐT tra cứu qua `phone_hash` (HMAC bằng `PHONE_HASH_KEY`). Gán `$model->phone` là đủ, trait `HasEncryptedPhone` tự chuẩn hóa và tính hash.

> **Sao lưu `APP_KEY` và `PHONE_HASH_KEY` ra ngoài server.** Mất `APP_KEY` là mất toàn bộ SĐT/địa chỉ; mất `PHONE_HASH_KEY` là không ai đăng nhập bằng SĐT được.

## Hai database

| Connection | Database | Chứa |
| --- | --- | --- |
| `groups` (mặc định) | `cashflow_groups` | Tài khoản, nhóm, danh bạ, phiếu thu chi, chiến dịch, SePay, timeline, bảng hệ thống Laravel |
| `system` | `cashflow_system` | Quản trị viên HIPOTECH, trạng thái dịch vụ từng nhóm, nhật ký quản trị |

Chi tiết bảng ở [plan/01](plan/01-co-so-du-lieu.plan.md).
