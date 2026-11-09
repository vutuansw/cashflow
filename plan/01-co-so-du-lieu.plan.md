# 01 — Cơ sở dữ liệu

Hai connection trong `config/database.php`:

| Connection | Database | Chứa |
| --- | --- | --- |
| `groups` (mặc định) | `cashflow_groups` | Dữ liệu khách hàng: tài khoản, nhóm, danh bạ, phiếu thu chi, chiến dịch, SePay, timeline. Mọi nhóm dùng chung, phân tách bằng `group_id`. Bảng hệ thống của Laravel (sessions, jobs, cache, failed_jobs) cũng ở đây. |
| `system` | `cashflow_system` | Quản trị viên HIPOTECH, trạng thái dịch vụ của từng nhóm, nhật ký quản trị, thiết lập nền tảng. |

Migration tách hai thư mục: `database/migrations` (groups) và `database/migrations/system` (chạy `php artisan migrate --database=system --path=database/migrations/system`). Lệnh gộp `php artisan cashflow:migrate` chạy cả hai.

Không có khóa ngoại giữa hai database. `cashflow_system.group_services.group_id` trỏ sang `cashflow_groups.groups.id` bằng quy ước, được giữ nhất quán trong service `GroupServiceManager`.

Mọi bảng có `created_at`, `updated_at`. Tiền là `bigint unsigned` đơn vị đồng.

---

## 1. Database `cashflow_groups`

### users — tài khoản đăng nhập (dùng chung nhiều nhóm)

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| id | bigint | |
| name | varchar(120) | Họ tên hiển thị |
| phone | text | **Mã hóa** (cast `encrypted`) |
| phone_hash | char(64) unique | HMAC-SHA256 của SĐT đã chuẩn hóa, dùng để đăng nhập/tra trùng |
| email | varchar(190) null unique | |
| password | varchar | bcrypt |
| must_change_password | bool | true khi QTV cấp tài khoản với mật khẩu tạm |
| avatar_path | varchar null | |
| last_login_at | timestamp null | |
| status | enum(active, locked) | |
| remember_token | | |

### groups — mỗi nhóm đăng ký là một dòng

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| id | bigint | Đây là `group_id` |
| code | varchar(32) unique | Mã quản lý, vd `chidoan-a12` |
| name | varchar(150) | |
| description | text null | |
| avatar_path, cover_path | varchar null | Ảnh đại diện, ảnh bìa timeline |
| visibility | enum(public, private) | |
| owner_id | bigint → users | Trưởng nhóm |
| opening_balance | bigint | Số dư đầu kỳ, mặc định 0 |
| opening_balance_date | date null | |
| bank_code | varchar(30) null | Mã ngân hàng theo danh sách của SePay QR (vd `MBBank`) |
| bank_account_no | text null | **Mã hóa** |
| bank_account_name | varchar(120) null | Tên chủ tài khoản (hiển thị trên trang đóng quỹ) |
| sepay_api_key | text null | **Mã hóa**; do Quỹ nhóm sinh, QTV dán vào SePay |
| sepay_last_hook_at | timestamp null | Lần cuối nhận webhook, để báo "đã kết nối" |
| notify_emails | json null | Danh sách email nhận thông báo; trống thì gửi trưởng nhóm |
| deleted_at | soft delete | |

### group_members — tài khoản thuộc nhóm với vai trò nào

| Cột | Ghi chú |
| --- | --- |
| group_id, user_id | unique cặp |
| role | enum(owner, admin, member) |
| contact_id | null → contacts; liên kết tài khoản với dòng danh bạ |
| status | enum(active, removed) |

### contacts — danh bạ thành viên

| Cột | Ghi chú |
| --- | --- |
| group_id | |
| full_name | varchar(120) |
| search_name | varchar(120): tên bỏ dấu, chữ thường — để gợi ý người đóng từ nội dung CK |
| phone | text null, **mã hóa** |
| phone_hash | char(64) null; index (group_id, phone_hash) để chặn trùng trong nhóm |
| birthday | date null |
| address | text null, **mã hóa** |
| note | varchar(255) null |
| user_id | null → users khi đã cấp tài khoản |
| status | enum(active, left) — "đã rời nhóm" vẫn giữ lịch sử đóng quỹ |
| sort_order | int |

### categories — loại thu chi

| Cột | Ghi chú |
| --- | --- |
| group_id | |
| type | enum(income, expense) |
| name | varchar(80), unique (group_id, type, name) |
| icon, color | để hiển thị timeline/biểu đồ |
| is_system | bool: loại mặc định được tạo khi lập nhóm (sửa tên được, không xóa được khi đã có phiếu) |
| archived_at | ẩn khỏi form nhưng giữ phiếu cũ |
| sort_order | |

Khi tạo nhóm, seed từ `config/cashflow.php`:
- Thu: **Đóng quỹ năm**
- Chi: **Liên hoan**, **Đám hiếu**, **Đám hỉ**, **Thăm ốm**, **Thăm đẻ**

### transactions — phiếu thu chi (mỗi phiếu là một bài trên timeline)

| Cột | Ghi chú |
| --- | --- |
| group_id | |
| type | enum(income, expense) |
| category_id | |
| campaign_id | null → campaigns (phiếu thu thuộc chiến dịch) |
| contact_id | null → contacts: người đóng (thu) hoặc người được thăm/nhận (chi) |
| amount | bigint unsigned > 0 |
| occurred_at | datetime: ngày giờ phát sinh (lấy từ bill nếu AI đọc được) |
| title | varchar(150): tiêu đề bài timeline, vd "Thăm ốm bạn Lan" |
| description | text null |
| payer_name | varchar(120) null: tên người nộp/nhận khi không có trong danh bạ |
| source | enum(manual, ai_bill, sepay) |
| sepay_transaction_id | null → sepay_transactions, unique |
| show_on_timeline | bool, mặc định true |
| created_by, updated_by | → users |
| deleted_at | soft delete, có người xóa `deleted_by` |

Index: (group_id, occurred_at), (group_id, type, category_id), (campaign_id).

### transaction_photos — ảnh sự kiện (hiện trên timeline)

transaction_id, path, thumb_path, width, height, sort_order. Tối đa 10 ảnh/phiếu.

### bill_scans — ảnh bill chứng từ + nhật ký AI

| Cột | Ghi chú |
| --- | --- |
| group_id, user_id | người tải lên |
| transaction_id | null; gắn vào phiếu khi người dùng bấm Lưu |
| path | ảnh đã thu nhỏ, lưu ổ riêng tư |
| status | enum(processing, done, failed) |
| model, fallback_used | model đã gọi |
| result | json: dữ liệu AI đọc thô |
| draft | json: bản nháp form đã suy luận |
| prompt_tokens, completion_tokens, cost_usd | decimal(10,6) |
| error | text null |

Bill chưa gắn phiếu sau 7 ngày bị xóa file bằng lệnh định kỳ (giữ dòng log để tính chi phí).

### transaction_histories — nhật ký sửa phiếu

transaction_id, user_id, action (created, updated, deleted, restored), changes json (trước/sau). Tiền bạc cần truy vết ai sửa gì; QTV xem được trong chi tiết phiếu.

### campaigns — chiến dịch thu

| Cột | Ghi chú |
| --- | --- |
| group_id | |
| name | vd "Thu quỹ năm 2026" |
| slug | dùng trên URL, unique trong nhóm |
| transfer_code | mã nội dung CK, **unique toàn hệ thống**, chỉ chữ in hoa + số, 6–12 ký tự, vd `QNA12N26`. Tự sinh, sửa được khi chưa có giao dịch |
| category_id | loại thu, mặc định "Đóng quỹ năm" |
| amount_per_member | bigint null: số tiền mỗi người (null = tùy tâm) |
| target_amount | bigint null |
| starts_on, ends_on | date null |
| status | enum(open, closed) |
| description | text null |
| cover_path | null |

### campaign_members — ai phải đóng trong chiến dịch

campaign_id, contact_id (unique cặp), expected_amount (mặc định = amount_per_member), exempt (bool, miễn đóng), note.
Số đã đóng = tổng `transactions.amount` cùng campaign_id + contact_id. Trạng thái: chưa đóng / đóng thiếu / đã đủ / miễn.

### sepay_transactions — hàng chờ duyệt

| Cột | Ghi chú |
| --- | --- |
| group_id | |
| sepay_id | id giao dịch bên SePay; unique (group_id, sepay_id) để webhook gửi lại không bị trùng |
| gateway | tên ngân hàng |
| transaction_date | datetime |
| account_number | |
| transfer_type | enum(in, out) |
| amount | bigint |
| content | nội dung CK |
| reference_code | |
| raw | json nguyên payload |
| matched_campaign_id | null: chiến dịch nhận ra từ `transfer_code` trong nội dung |
| suggested_contact_id | null: người đóng đoán từ tên trong nội dung |
| status | enum(pending, approved, ignored) |
| (phiếu tạo ra) | không lưu cột ở đây; tra qua `transactions.sepay_transaction_id` (unique) để liên kết chỉ đi một chiều |
| reviewed_by, reviewed_at | |

### reactions, comments — tương tác timeline

- `reactions`: transaction_id, user_id, unique cặp (chỉ có "tim").
- `comments`: transaction_id, user_id, body (≤ 1000 ký tự), hidden_at, hidden_by, deleted_at.

### Bảng của Laravel

`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`.

---

## 2. Database `cashflow_system`

### admins

id, name, email unique, password, status, last_login_at. Tạo bằng `php artisan cashflow:admin-create`. Guard riêng `system`.

### group_services — trạng thái dịch vụ của từng nhóm (nguồn sự thật)

| Cột | Ghi chú |
| --- | --- |
| group_id | unique |
| review_status | enum(pending_review, approved, rejected) — nhóm mới là `pending_review` nhưng vẫn hoạt động |
| service_status | enum(active, locked) — `locked` thì mọi trang và webhook của nhóm trả về "Nhóm đang tạm khóa" |
| lock_reason | varchar null |
| ai_monthly_quota | int null — null = không giới hạn (mặc định) |
| max_photos_mb | int null — hạn mức dung lượng ảnh, null = mặc định nền tảng |
| reviewed_by, reviewed_at | → admins |
| note | ghi chú nội bộ |

Middleware `ResolveGroup` đọc `service_status` (cache 60 giây, xóa cache khi `/system` đổi trạng thái).

### admin_audit_logs

admin_id, action (approve, lock, unlock, update_group, delete_group, create_group, change_code...), group_id null, payload json, ip, created_at.

### platform_settings

key/value json: hạn mức AI mặc định, số nhóm tối đa một tài khoản được tạo (mặc định 5), thông báo hệ thống.

---

## 3. Mã hóa dữ liệu cá nhân

| Dữ liệu | Cách lưu |
| --- | --- |
| SĐT (users, contacts) | Cột `phone`: cast `encrypted` (AES-256-CBC + MAC của Laravel). Cột `phone_hash`: `hash_hmac('sha256', chuẩn_hóa(sđt), PHONE_HASH_KEY)` |
| Địa chỉ, STK ngân hàng, API key SePay | cast `encrypted` |
| Mật khẩu | bcrypt |

- Chuẩn hóa SĐT trước khi hash: bỏ khoảng trắng/dấu chấm, `+84`/`84` → `0`, kiểm tra 10 số đầu `0`.
- `PHONE_HASH_KEY` là khóa riêng trong `.env`, khác `APP_KEY`. **Mất `APP_KEY` là mất toàn bộ SĐT/địa chỉ**, mất `PHONE_HASH_KEY` là không đăng nhập bằng SĐT được: hai khóa phải được sao lưu ngoài server. Ghi rõ trong README và `deploy/README.md`.
- Đổi `APP_KEY` dùng `APP_PREVIOUS_KEYS` của Laravel để giải mã dữ liệu cũ, rồi chạy lệnh `cashflow:reencrypt`.
- SĐT không bao giờ trả về API công khai; trong trang quản trị hiển thị đầy đủ cho QTV, các nơi khác che `09xx xxx 123`.
- Không log SĐT; request log che các trường `phone`, `address`, `password`.
