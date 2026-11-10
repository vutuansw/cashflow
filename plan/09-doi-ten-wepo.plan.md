# Đổi tên sản phẩm: Quỹ nhóm → WePo

Ngày: 10/11/2026. Trạng thái: **đã duyệt, đã làm** (10/11/2026).

## Quyết định đã chốt

- Tên sản phẩm là **WePo**. Tên kỹ thuật `cashflow` giữ nguyên (thư mục, namespace, database `cashflow_*`, config `cashflow.php`).
- Domain đổi từ `quynhom.hipotech.vn` sang **`wepo.hipotech.vn`**. App chưa deploy nên không link nào bị hỏng.
- Logo và trang chủ hiện **WePo kèm dòng phụ "Quỹ nhóm"** để người mới biết app dùng làm gì. Các câu khác chỉ ghi "WePo".
- Tên kỹ thuật có chữ `quynhom` cũng **đổi hết sang `wepo`**.
- Ngoài phạm vi: icon con heo, màu xanh lá và icon PWA giữ nguyên. Làm logo riêng cho WePo là việc khác.

## Việc cần làm

### 1. Cấu hình

| File | Đổi |
| --- | --- |
| `.env`, `.env.example` | `APP_NAME="WePo"`. Email gửi đi tự lấy tên người gửi là WePo qua `MAIL_FROM_NAME`. |
| `config/app.php`, `config/cashflow.php` | Giá trị mặc định `'Quỹ nhóm'` → `'WePo'`, đổi domain trong chú thích. Dòng phụ "Quỹ nhóm" chỉ nằm ở logo và trang chủ nên ghi thẳng trong Vue, không thêm cấu hình. |
| `composer.json` | `description`: "WePo - Quỹ nhóm, quản lý thu chi của hội nhóm theo dòng thời gian." |

### 2. Giao diện

- **`BrandMark.vue`** (logo ở AdminShell, AuthLayout, chọn nhóm): chữ **WePo** đậm, bên cạnh là dòng phụ nhỏ màu nhạt "Quỹ nhóm".
- **`HomePage.vue`**: tiêu đề lớn **WePo**, ngay dưới là dòng "Quỹ nhóm minh bạch cho hội nhóm". Câu mô tả giữ nguyên.
- **`SystemShell.vue`**: "WePo · System". **`system/LoginPage.vue`**: "Quản trị WePo".
- **Tiêu đề tab trình duyệt**: `GroupHomePage`, `CampaignPage`, `ProfilePage` đổi `· Quỹ nhóm` thành `· WePo`.
- **Các câu nhắc tên app** đổi "Quỹ nhóm" thành "WePo":
  - `LoginPage`: "mật khẩu WePo".
  - `TempPasswordCard`, `GroupCreatePage`: "tài khoản WePo".
  - `SettingsPage`, `ProfilePage`: "quản trị WePo".
  - `InstallHint`: "Cài WePo lên điện thoại".
  - Tin nhắn mời trong `GroupCreatePage`.
- **Domain trong gợi ý**: `RegisterPage`, `GroupPickerPage`, `OverviewPage` ghi `wepo.hipotech.vn/…`.

### 3. PWA và trang tĩnh

- `public/manifest.webmanifest`: `name` "WePo · Quỹ nhóm", `short_name` "WePo", vì tên dưới icon trên điện thoại phải ngắn.
- `resources/views/app.blade.php`: `apple-mobile-web-app-title` lấy từ `config('cashflow.brand.name')` thay cho chữ cố định.
- `public/offline.html`: tiêu đề và câu "WePo cần mạng…".
- `public/sw.js`: đổi chú thích và **tăng tên cache**. Máy đã cài PWA sẽ tải lại vỏ app mới, không còn hiện tên cũ.

### 4. Máy chủ (email, Excel, AI)

- **Email**:
  - `GroupStatusMail`: tiêu đề `[WePo] …`.
  - `mail/group-status.blade.php`: "Quản trị WePo".
  - Email đặt lại mật khẩu trong `AppServiceProvider`: "Đặt lại mật khẩu WePo", "tài khoản WePo", ký tên "WePo".
- **Tin nhắn cấp tài khoản** (`AccountGranter`): "WePo - {tên nhóm}", "mật khẩu WePo".
- **Excel** (`ExcelExporter`, `ContactController`):
  - Tên file `wepo-{ma-nhom}-…xlsx`, file mẫu danh bạ `wepo-mau-danh-ba.xlsx`.
  - Người tạo file là "WePo", chân trang "Xuất từ WePo lúc …".
- **AI**: `AiProvider` gửi `X-Title` theo `APP_NAME`, tự thành WePo. Chỉ sửa chú thích.

### 5. Tên kỹ thuật

- **localStorage**:
  - `BankAppSheet` đổi `quynhom:bank-app` → `wepo:bank-app`.
  - Rà các khóa `quynhom:` khác nếu có (InstallHint…).
  - Đổi khóa nghĩa là máy đang dùng quên app ngân hàng đã chọn lần trước. Chấp nhận được vì chưa có người dùng thật.
- **Tài khoản demo**: `DemoSeeder` đổi `admin@quynhom.test` → `admin@wepo.test`. Chạy lại seeder tạo tài khoản mới; dòng cũ trong database local mình xóa luôn.

### 6. Test

Sửa các test đang kiểm tra tên cũ:
- `SpaRoutesTest`: `name` = WePo.
- `TimelineTest`: `og:title` và `<title>`.
- `ReportsTest`: tên file `wepo-…xlsx`.

### 7. Tài liệu

- **`README.md`**: tên, domain, tên kỹ thuật vẫn là cashflow.
- **`plan/plan.md`**: tên sản phẩm, domain, dòng logo chữ.
- **`plan/02`–`08`**: thay "Quỹ nhóm" khi là tên app và đổi domain. Từ "quỹ nhóm" theo nghĩa thường (quỹ của nhóm) thì giữ.
- **`plan/wireframes/m2.html`**: giữ nguyên, là bản vẽ lịch sử.
- **Bộ nhớ dự án**: ghi chú tên mới WePo.

## Lưu ý khi làm

Một số file cần sửa đang có thay đổi chưa commit của phiên làm việc khác: `GroupHomePage`, `CampaignPage`, `ProfilePage`, `SettingsPage`, `OverviewPage`, `BankAppSheet`, `TimelineTest`, `plan/08`.

Đề xuất:
- Làm trên **nhánh mới `doi-ten-wepo`**, tách từ `bill-thanh-vien`.
- Chỉ commit các file không vướng thay đổi của phiên kia.
- Với 8 file trên, mình vẫn sửa dòng tên trong working tree nhưng **không commit**. Phiên kia commit file của họ sẽ mang theo luôn dòng đổi tên.

Nếu bạn muốn commit đủ một lần, hãy chờ phiên kia commit xong rồi mình làm.

## Kiểm tra

- `grep -rni "quỹ nhóm\|quynhom"` chỉ còn các chỗ cố ý giữ: dòng phụ, nghĩa thường, wireframe cũ.
- `php artisan test` xanh hết. `npm run build` không lỗi.
- Trình duyệt:
  - Trang chủ, đăng nhập và khu quản trị có logo WePo kèm dòng phụ.
  - Tab trình duyệt ghi "… · WePo".
  - `/system` ghi "WePo · System".
  - Gợi ý domain là `wepo.hipotech.vn`.
- Tải file Excel ra tên `wepo-…xlsx`.
