# Kế hoạch dự án Quỹ nhóm

**Tên sản phẩm:** Quỹ nhóm
**Mô tả:** Quản lý quỹ tiền thu chi của hội nhóm theo dòng thời gian.
**Tên kỹ thuật:** `cashflow` (thư mục, namespace, database, tên tiến trình). Tên "Quỹ nhóm" chỉ nằm trong cấu hình hiển thị (`APP_NAME`), không làm đổi tên kỹ thuật.

Trạng thái: **ĐANG BÀN, CHƯA THỰC THI**. Bản nháp ngày 09/11/2026. Chủ dự án duyệt xong bộ tài liệu này mới bắt đầu code.

| Tài liệu | Nội dung |
| --- | --- |
| [01-co-so-du-lieu](01-co-so-du-lieu.plan.md) | Hai database, các bảng, mã hóa số điện thoại |
| [02-tai-khoan-va-phan-quyen](02-tai-khoan-va-phan-quyen.plan.md) | Đăng ký, đăng nhập SĐT, vai trò, danh bạ, cấp tài khoản, trang /system |
| [03-thu-chi-va-timeline](03-thu-chi-va-timeline.plan.md) | Phiếu thu chi, loại thu chi, ảnh, timeline, tim/bình luận, dashboard, báo cáo |
| [04-ai-doc-bill](04-ai-doc-bill.plan.md) | Đọc ảnh bill chuyển khoản bằng OpenRouter, như FarmGo |
| [05-chien-dich-va-sepay](05-chien-dich-va-sepay.plan.md) | Chiến dịch thu, mã QR, webhook SePay, hàng chờ duyệt |
| [06-giao-dien](06-giao-dien.plan.md) | Sơ đồ màn hình, mobile-first, PWA |
| [07-lo-trinh-va-trien-khai](07-lo-trinh-va-trien-khai.plan.md) | Các mốc code, kiểm thử, deploy lên quynhom.hipotech.vn |

---

## 1. Công nghệ

Giống `www/chatbot` (HipoChat) để dùng chung server, chung thói quen code:

| Lớp | Phiên bản |
| --- | --- |
| PHP | 8.4 (`~8.4.1`) |
| Laravel | 13.x |
| MySQL / MariaDB | collation `utf8mb4_unicode_ci` |
| Vue | 3.5 + vue-router 4 |
| Build | Vite 8 + `laravel-vite-plugin` 3 + `@vitejs/plugin-vue` 6 |
| CSS | Tailwind CSS 4 (`@tailwindcss/vite`) |
| Icon | `lucide-vue-next` |
| Test | PHPUnit 12, Pint |

Thêm so với chatbot:

| Gói | Dùng cho |
| --- | --- |
| `phpoffice/phpspreadsheet` | Xuất Excel (chatbot đã dùng) |
| `intervention/image` 3 (driver GD) | Thu nhỏ ảnh, tạo thumbnail |
| `guzzlehttp/guzzle` (có sẵn trong Laravel) | Gọi OpenRouter |
| `chart.js` 4 | Biểu đồ dashboard |

Không dùng Anthropic SDK: đọc bill đi qua OpenRouter giống `www/farm`.

## 2. Các quyết định đã chốt (09/11/2026)

| # | Chủ đề | Quyết định |
| --- | --- | --- |
| 1 | Kích hoạt nhóm | Đăng ký xong **hoạt động ngay**. Quản trị HIPOTECH duyệt sau ở `/system`, có thể khóa/sửa/xóa. Chưa thu phí. |
| 2 | Vai trò trong nhóm | **Trưởng nhóm** (chủ, toàn quyền, chuyển giao được) · **Quản trị viên** (thu chi, chiến dịch, danh bạ, cấp tài khoản, báo cáo) · **Thành viên** (xem timeline private, thả tim, bình luận). |
| 3 | Tài khoản | **Một SĐT = một tài khoản** dùng cho nhiều nhóm; vai trò tính theo từng nhóm. Đăng nhập bằng **SĐT + mật khẩu**, email tùy chọn (bắt buộc với trưởng nhóm để nhận thông báo và lấy lại mật khẩu). |
| 4 | Danh bạ thành viên | Tách khỏi tài khoản: Họ tên, SĐT (mã hóa), ngày sinh, địa chỉ (mã hóa), ghi chú. Nhập nhanh hàng loạt. QTV bấm "Cấp tài khoản" khi người đó cần đăng nhập. Xem timeline public không cần tài khoản; vào trang quản trị bắt buộc có tài khoản. |
| 5 | Chiến dịch thu | **Một QR chung** cho mỗi chiến dịch, nội dung CK điền sẵn mã chiến dịch; người đóng tự ghi thêm tên. Có bảng đã đóng/chưa đóng theo danh bạ. |
| 6 | Webhook SePay | Giao dịch vào/ra đi vào **hàng chờ duyệt**; QTV gán chiến dịch, loại, người đóng rồi duyệt mới thành phiếu. Mỗi nhóm tự có tài khoản SePay và tự dán webhook của Quỹ nhóm vào. |
| 7 | Hiển thị đã đóng/chưa đóng | Ai xem được timeline thì xem được danh sách tên. **Không bao giờ** hiển thị SĐT/địa chỉ ra ngoài trang quản trị. |
| 8 | AI đọc bill | **OpenRouter**, dùng lại cách của `www/farm` (`google/gemini-3.1-flash-lite`, dự phòng `google/gemini-3.7-flash`). AI chỉ điền form, người dùng bấm Lưu. **Không giới hạn** lượt mặc định; `/system` đặt được hạn mức từng nhóm, luôn ghi nhận lượt và chi phí. |
| 9 | Ảnh bill | Lưu làm **chứng từ, chỉ QTV xem**. Timeline chỉ hiện ảnh sự kiện. |
| 10 | Timeline | Có **thả tim + bình luận**, chỉ người đăng nhập thuộc nhóm. Khách xem link public chỉ thấy số tim và bình luận. QTV ẩn/xóa bình luận. |
| 11 | Quản trị nền tảng | `quynhom.hipotech.vn/system`, tài khoản quản trị riêng nằm trong `cashflow_admin`. |
| 12 | Tính năng thêm ở bản đầu | Xuất Excel, PWA, số dư đầu kỳ, thông báo email cho QTV. |
| 13 | Database | `cashflow_groups` (dữ liệu khách hàng, dùng chung, phân tách bằng `group_id`) và `cashflow_admin` (quản trị dịch vụ). |
| 14 | Domain | `quynhom.hipotech.vn`, thư mục server `www/cashflow`. |

## 3. Kiến trúc

Một ứng dụng Laravel, một SPA Vue, hai database trên cùng MySQL.

```mermaid
flowchart LR
    M[Điện thoại / máy tính] --> SPA[Vue SPA + PWA]
    SPA --> API[Laravel API /api]
    SEPAY[SePay webhook] --> WH[/api/webhooks/sepay/{code}/]
    WH --> API
    API --> G[(cashflow_groups: users, groups, danh bạ, phiếu, chiến dịch, timeline)]
    API --> A[(cashflow_admin: quản trị viên HIPOTECH, dịch vụ nhóm, nhật ký)]
    API --> OR[OpenRouter: đọc bill]
    API --> Q[Hàng đợi database: email, xử lý ảnh]
    SPA --> QR[qr.sepay.vn: ảnh QR]
```

Nguyên tắc bất biến:

1. **Mọi truy vấn dữ liệu nhóm đều lọc theo `group_id`.** Model nhóm dùng trait `BelongsToGroup` có global scope lấy nhóm hiện tại; middleware `ResolveGroup` đặt nhóm hiện tại từ mã trên URL. Test bắt buộc có ca "người nhóm A không đọc/sửa được dữ liệu nhóm B".
2. **Tiền lưu số nguyên VND** (`bigint unsigned`), không dùng float. Thu và chi đều là số dương, phân biệt bằng `type`.
3. **Số dư không lưu cứng**: `số dư đầu kỳ + tổng thu − tổng chi` của phiếu chưa xóa. Có cache ngắn, xóa cache khi phiếu thay đổi.
4. **AI và SePay không bao giờ tự ghi sổ.** AI chỉ trả bản nháp cho form; SePay chỉ vào hàng chờ. Người bấm Lưu/Duyệt là người chịu trách nhiệm.
5. **Dữ liệu cá nhân mã hóa ở tầng ứng dụng** (SĐT, địa chỉ). Tra cứu SĐT qua cột hash HMAC riêng.
6. Tên sản phẩm, loại thu chi mặc định, model AI: là cấu hình/dữ liệu, không viết cứng rải rác trong code.

## 4. Đường dẫn chính

| URL | Ai dùng |
| --- | --- |
| `/` | Trang giới thiệu, nút Đăng ký nhóm / Đăng nhập |
| `/dang-ky`, `/dang-nhap`, `/quen-mat-khau` | Trưởng nhóm, thành viên |
| `/nhom` | Chọn nhóm sau khi đăng nhập, tạo thêm nhóm |
| `/{ma-nhom}` | Timeline + tóm tắt quỹ (public: ai có link; private: phải đăng nhập và thuộc nhóm) |
| `/{ma-nhom}/chien-dich/{ma-chien-dich}` | Trang đóng quỹ: QR, tiến độ, đã đóng/chưa đóng |
| `/{ma-nhom}/quan-tri/...` | Trang quản trị nhóm (trưởng nhóm, QTV) |
| `/system/...` | Quản trị HIPOTECH |
| `POST /api/webhooks/sepay/{ma-nhom}` | SePay gọi vào |

Mã nhóm: chữ thường không dấu, số, dấu `-`; dài 3–32; duy nhất; chặn các từ dành riêng (`system`, `api`, `nhom`, `dang-nhap`, `dang-ky`, `quen-mat-khau`, `build`, `storage`, `media`, `manifest.webmanifest`, `sw.js`...). Đổi mã nhóm làm hỏng link đã chia sẻ và webhook SePay nên chỉ `/system` được đổi.

## 5. Ngoài phạm vi bản đầu

- Thu phí sử dụng, gói dịch vụ.
- Nhiều quỹ/nhiều tài khoản ngân hàng trong một nhóm.
- Thông báo Zalo/SMS, push notification.
- Đăng nhập OTP, đăng nhập Google.
- Ứng dụng di động gốc (đã có PWA).
- Tự động khớp giao dịch SePay không cần duyệt.

## 6. Còn cần chủ dự án cung cấp trước khi deploy

| Hạng mục | Ghi chú |
| --- | --- |
| Khóa OpenRouter | Dùng chung khóa với FarmGo hay tạo khóa riêng để tách chi phí (khuyên tạo riêng) |
| SMTP gửi email | Máy chủ gửi thư, địa chỉ người gửi (vd `no-reply@hipotech.vn`) |
| Repo GitHub | Để dựng deploy tự động giống chatbot (không có thì deploy tay bằng `deploy.sh`) |
| Logo, màu thương hiệu | Không có thì dùng logo chữ "Quỹ nhóm" và màu xanh lá mặc định |
| Tài khoản quản trị `/system` đầu tiên | Email người quản trị HIPOTECH; tạo bằng lệnh artisan |
