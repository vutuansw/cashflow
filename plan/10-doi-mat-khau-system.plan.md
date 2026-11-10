# Quản trị viên /system tự đổi mật khẩu

Ngày: 10/11/2026. Trạng thái: **đã duyệt, đã làm** (10/11/2026).

## Hiện trạng

- Khu `/system` đăng nhập bằng guard `system` (bảng `admins` ở `cashflow_system`), cookie session riêng `cashflow_system_session`, xem plan/02 mục 6. Route ở `routes/system.php`, chặn bằng `EnsureSystemAdmin`.
- Quản trị viên đầu tiên tạo bằng `cashflow:admin-create`, người sau thêm ở trang Quản trị viên. Cả hai cách đều sinh mật khẩu ngẫu nhiên, hiện một lần. **Chưa có chỗ nào để quản trị viên tự đổi mật khẩu**, muốn đổi phải vào server.
- Phía người dùng nhóm đã có `PUT /api/me/password` (`PasswordController::update`) và trang `ChangePasswordPage.vue`: bắt mật khẩu cũ, tối thiểu 8 ký tự, ô nhập lại phải khớp, mật khẩu mới phải khác mật khẩu cũ. Không dùng lại được route này vì nó chạy trên guard `web`, nhưng bám theo luật và câu chữ của nó.

## Quyết định đã chốt

- **Chỗ đặt form**: trang riêng `/system/tai-khoan`, tiêu đề "Tài khoản của tôi".
  - Máy tính: bấm vào **email ở cuối sidebar** (hiện đang là chữ, đổi thành link có icon người) để vào.
  - Điện thoại: thêm nút icon người cạnh nút Đăng xuất trên thanh đầu trang.
  - Không thêm vào menu chính để menu không dài thêm.
- **Bắt nhập mật khẩu hiện tại**. Sai thì báo lỗi ở ô đó: "Mật khẩu hiện tại không đúng."
- **Luật mật khẩu mới**: tối thiểu **8 ký tự** (giống người dùng nhóm), ô "Nhập lại mật khẩu mới" phải khớp, mật khẩu mới phải khác mật khẩu đang dùng.
- **Sau khi đổi**: phiên đang dùng để đổi được giữ; **các phiên /system khác của tài khoản này bị đăng xuất** ở lần gọi kế tiếp.
- **Không gửi email** báo đổi mật khẩu.
- Ghi **nhật ký** `change_password` (không ghi mật khẩu), hiện ở trang Nhật ký là "Đổi mật khẩu".

## Việc cần làm

### M1. Máy chủ

- `routes/system.php`, trong nhóm `EnsureSystemAdmin`: `PUT /api/system/me/password` → `AuthController::updatePassword`, `throttle:5,1` như các route mật khẩu khác.
- `AuthController::updatePassword`:
  - Validate `current_password` (bắt buộc), `password` (bắt buộc, `confirmed`, `Password::min(8)`).
  - `Hash::check` mật khẩu hiện tại, sai thì lỗi ở `current_password`.
  - Mật khẩu mới trùng mật khẩu cũ thì lỗi ở `password`: "Mật khẩu mới phải khác mật khẩu đang dùng."
  - Lưu mật khẩu mới, đổi `remember_token`, ghi nhật ký, cập nhật dấu mật khẩu trong phiên hiện tại (xem dưới), `regenerate` session id. Trả `{data: {id, name, email}}` như `/me`.
- **Đăng xuất phiên khác**: sessions lưu trong bảng `sessions` nhưng cột `user_id` chỉ ghi người dùng guard `web`, nên không xóa theo cột được. Cách làm:
  - Khi đăng nhập /system, lưu vào session khóa `system_password_hash` = một hash ngắn của mật khẩu đã băm hiện tại (giống `AuthenticateSession` của Laravel).
  - Middleware mới `EnsureSystemSessionFresh` đặt trong nhóm middleware `system` (sau `StartSession`): đã đăng nhập mà khóa trong session khác với mật khẩu hiện tại thì `logout` guard system, `invalidate` session. Request đó đi tiếp như khách: `/me` trả `null`, các route cần đăng nhập trả 401, SPA tự về trang đăng nhập.
  - Phiên cũ đăng nhập trước khi có tính năng này (chưa có khóa) thì ghi khóa luôn, không đá ra.
- `AuditLogsPage.vue`: thêm nhãn `change_password: 'Đổi mật khẩu'`.

### M2. Giao diện

- Route `/system/tai-khoan` trong `app.js` (`meta: { system: true }`), trang mới `resources/js/pages/system/AccountPage.vue`:
  - Khối thông tin: tên, email (chỉ xem).
  - Khối "Đổi mật khẩu" trong `card`: 3 ô `FormField` type password (Mật khẩu hiện tại, Mật khẩu mới với gợi ý "Ít nhất 8 ký tự.", Nhập lại mật khẩu mới), `autocomplete` đúng loại, nút "Lưu mật khẩu". Dùng `useForm` để hiện lỗi dưới từng ô.
  - Đổi xong: xóa trắng form, toast "Đã đổi mật khẩu. Các phiên đăng nhập khác đã bị đăng xuất."
- `SystemShell.vue`: email cuối sidebar thành `RouterLink` tới `/system/tai-khoan` (có icon `UserRound`, tô đậm khi đang ở trang này); trên điện thoại thêm nút icon `UserRound` cạnh nút Đăng xuất.

### M3. Test và kiểm tra

Feature test trong `tests/Feature/System/AdminPasswordTest.php`:
- Đổi đúng thì được: trả 200, đăng nhập lại bằng mật khẩu mới được, mật khẩu cũ không được, có nhật ký `change_password`.
- Sai mật khẩu hiện tại thì bị từ chối: lỗi ở `current_password`, mật khẩu không đổi.
- Luật mật khẩu: dưới 8 ký tự, nhập lại không khớp, trùng mật khẩu cũ đều bị từ chối.
- Người không phải admin system không gọi được: khách và người dùng nhóm đăng nhập guard `web` đều nhận 401; quản trị viên bị khóa nhận 403.
- Đăng xuất phiên khác: hai phiên cùng tài khoản, đổi mật khẩu ở phiên 1 thì phiên 2 gọi `/overview` nhận 401, phiên 1 vẫn dùng được.

Sau đó chạy `php artisan test`, `vendor/bin/pint`, `npm run build`, rồi thử trên trình duyệt tại http://127.0.0.1:8001: đổi mật khẩu, đăng xuất, đăng nhập lại bằng mật khẩu mới (dùng tài khoản quản trị thử tạo riêng, không dùng mật khẩu thật).

## Ngoài phạm vi

- Quên mật khẩu /system qua email. Quản trị viên quên mật khẩu thì nhờ người khác hoặc dùng lệnh trên server (việc khác nếu cần).
- Quản trị viên đặt lại mật khẩu hộ người khác.
- Sửa tên, email của quản trị viên.
