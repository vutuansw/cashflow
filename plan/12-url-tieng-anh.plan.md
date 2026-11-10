# Đổi mọi URL sang tiếng Anh

Ngày: 10/11/2026. Trạng thái: **đã duyệt, đã làm** (10/11/2026).

## Quyết định

- Mọi đường dẫn giao diện (SPA) và tham số trên URL dùng **tiếng Anh**, chữ thường, nối bằng `-`.
- **Không đổi**: mã nhóm (`/{ma-nhom}`) và slug chiến dịch, vì đó là dữ liệu người dùng tự đặt. API `/api/...` đã là tiếng Anh, giữ nguyên.
- **URL cũ chuyển hướng (301 phía SPA) sang URL mới** để không hỏng link đã gửi qua Zalo/email, mã QR chiến dịch đã in, bookmark, PWA đã cài. Giữ lớp chuyển hướng này ít nhất 6 tháng rồi mới bỏ.

## Bảng đổi đường dẫn

### Chung

| Cũ | Mới |
| --- | --- |
| `/dang-nhap` | `/login` |
| `/dang-ky` | `/register` |
| `/quen-mat-khau` | `/forgot-password` |
| `/dat-lai-mat-khau` | `/reset-password` |
| `/doi-mat-khau` | `/change-password` |
| `/nhom` | `/groups` |
| `/nhom/moi` | `/groups/new` |
| `/ca-nhan` | `/profile` |

### Trang nhóm (ai cũng thấy)

| Cũ | Mới |
| --- | --- |
| `/:group/thong-ke` | `/:group/stats` |
| `/:group/chien-dich/:slug` | `/:group/campaigns/:slug` |
| `/:group/thanh-vien` | `/:group/members` |
| `/:group/thanh-vien/:id` | `/:group/members/:id` |

### Quản trị nhóm

| Cũ | Mới |
| --- | --- |
| `/:group/quan-tri` | `/:group/admin` |
| `.../danh-ba`, `.../danh-ba/nhap` | `.../contacts`, `.../contacts/import` |
| `.../tai-khoan` | `.../accounts` |
| `.../loai-thu-chi` | `.../categories` |
| `.../cai-dat` | `.../settings` |
| `.../them` | `.../more` |
| `.../phieu`, `.../phieu/moi`, `.../phieu/:id` | `.../transactions`, `.../transactions/new`, `.../transactions/:id` |
| `.../tao-phieu` | bỏ, chỉ còn chuyển hướng sang `.../transactions/new?type=expense` |
| `.../cho-duyet` | `.../sepay-queue` |
| `.../chien-dich`, `.../moi`, `.../:id`, `.../:id/sua` | `.../campaigns`, `.../new`, `.../:id`, `.../:id/edit` |
| `.../bao-cao` | `.../reports` |

### /system

| Cũ | Mới |
| --- | --- |
| `/system/dang-nhap` | `/system/login` |
| `/system/nhom`, `/system/nhom/moi`, `/system/nhom/:id` | `/system/groups`, `/system/groups/new`, `/system/groups/:id` |
| `/system/chi-phi-ai` | `/system/ai-costs` |
| `/system/nhat-ky` | `/system/audit-logs` |
| `/system/quan-tri-vien` | `/system/admins` |
| `/system/tai-khoan` | `/system/account` |

### Tham số trên URL

| Cũ | Mới | Giá trị |
| --- | --- | --- |
| `?loai=thu\|chi` | `?type=income\|expense` | Ở trang phiếu, báo cáo, hàng chờ SePay |
| `?ky=` | `?period=` | `month\|year\|all` (giữ) |
| `?moc=` | `?at=` | `YYYY-MM` hoặc `YYYY` (giữ) |
| `?moi=1` | `?welcome=1` | Màn hình Bắt đầu nhanh sau khi tạo nhóm |
| `?chien_dich=` | `?campaign=` | Tạo phiếu từ chiến dịch |
| `?thu_chi=`, `?loai=`, `?nguon=`, `?tim=`, `?da_xoa=` (danh sách phiếu) | `?type=`, `?category=`, `?source=`, `?q=`, `?trashed=` | |
| `?loai=bill` (hàng chờ SePay) | `?source=bill` | |
| `?loai=` (báo cáo) | `?report=` | `ledger\|by-category\|by-month\|campaign` |
| `?sepay=`, `?claim=`, `?scan=`, `?next=`, `?review=`, `?service=` | giữ nguyên | đã là tiếng Anh |

Chuyển hướng URL cũ cũng đổi luôn tham số cũ sang tham số mới.

## Việc cần làm

1. **`resources/js/app.js`**: viết lại bảng route theo bảng trên. Thêm một bảng `legacyRedirects` (đường dẫn cũ → mới, đổi cả tham số) đặt trước route `/:group`, có chú thích hạn bỏ.
2. **Mọi `RouterLink`, `router.push/replace`, `homePath()`** trong `resources/js` (khoảng 37 file) đổi sang đường dẫn mới. Đọc `route.query` theo tên tham số mới (`period.js`, trang phiếu, báo cáo, hàng chờ SePay, tổng quan).
3. **Link do server tạo ra**:
   - Email đặt lại mật khẩu (`AppServiceProvider`) → `/reset-password`.
   - Email tóm tắt SePay (`SepayDigestMail`) → `/:group/admin/sepay-queue`.
   - Lời nhắn cấp tài khoản (`AccountGranter`), tạo nhóm hộ ở `/system` (`System/GroupController`) → `/login`.
   - Link trang chiến dịch, trang thành viên (`CampaignPageController`, `GroupViewer`, `MemberIndex`) → `/campaigns/`, `/members/`.
   - `SpaController` nếu có xử lý riêng theo đường dẫn.
4. **Mã nhóm dành riêng** (`config/cashflow.php`): thêm `groups`, `profile`, `forgot-password`, `reset-password`, `change-password`. Giữ các từ tiếng Việt cũ trong danh sách để chuyển hướng không đụng nhóm thật. Viết lệnh kiểm tra trên server xem đã có nhóm nào dùng các mã mới bị cấm chưa trước khi deploy.
5. **Slug chiến dịch mặc định** khi tên không ra slug: `chien-dich` → `campaign` (chỉ ảnh hưởng chiến dịch tạo mới).
6. **Service worker / manifest**: `start_url` và các đường dẫn cache nếu có chứa URL cũ.
7. **Tài liệu**: plan.md mục 4 (bảng route), plan/02, 03, 05, 06, 08, 11 và wireframes: thay đường dẫn cũ bằng mới.

## Đã làm thêm so với dự kiến

- Slug báo cáo trong API cũng đổi: `/api/g/{nhom}/admin/reports/so-quy|theo-loai|theo-thang` → `ledger|by-category|by-month`. Tên file Excel tải về giữ tiếng Việt (`wepo-{ma-nhom}-so-quy-2026.xlsx`) vì là tên file, không phải URL.
- Chuyển hướng URL cũ nằm ở `resources/js/lib/legacyUrls.js`, gọi đầu tiên trong `router.beforeEach` (đặt route sẽ không được vì `/dang-nhap` khớp nhầm `/:group`).
- Lệnh `php artisan cashflow:reserved-codes`: liệt kê nhóm đang dùng mã dành riêng. **Chạy trên server trước khi deploy**; local không có nhóm nào trùng.
- PWA: `start_url` đổi thành `/groups?pwa=1`. PWA đã cài vẫn mở `/nhom` cũ cho tới khi trình duyệt cập nhật manifest, lớp chuyển hướng lo phần này.

## Kiểm thử

- `SpaRoutesTest`: URL mới trả SPA; mã nhóm dành riêng mới bị từ chối.
- Test cho các link server tạo: email đặt lại mật khẩu, email SePay, lời nhắn cấp tài khoản chứa đường dẫn mới.
- Kiểm tra `grep` không còn đường dẫn tiếng Việt trong `resources/js` ngoài bảng `legacyRedirects`.
- Chạy thử khổ điện thoại: các tab quản trị, tạo phiếu thu/chi, lọc kỳ, trang chiến dịch, link cũ `/dang-nhap`, `/{nhom}/quan-tri/phieu?loai=chi`, `/{nhom}/chien-dich/{slug}` chuyển đúng sang link mới.

## Lưu ý khi làm

Một phiên khác đang sửa phần `/system` (đổi mật khẩu, `app.js`, `routes/system.php`) trên cùng thư mục. Việc đổi URL đụng `app.js` và các trang `/system`, nên làm **sau khi** phiên đó commit xong để không ghi đè lẫn nhau.
