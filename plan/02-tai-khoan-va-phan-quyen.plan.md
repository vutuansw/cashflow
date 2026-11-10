# 02 — Tài khoản, phân quyền, danh bạ, quản trị nền tảng

## 1. Xác thực

- SPA cùng domain, xác thực bằng **session cookie** của Laravel (guard `web`, provider `users` trên connection `groups`), CSRF qua cookie `XSRF-TOKEN`. Không cần token API.
- Guard `system` riêng cho `/system`, provider `admins` trên connection `system`, cookie session riêng (`cashflow_system_session`), không đăng nhập chéo.
- Đăng nhập: SĐT + mật khẩu. Tìm user theo `phone_hash`. Giới hạn 5 lần sai/phút theo SĐT + IP.
- "Ghi nhớ đăng nhập" mặc định bật trên điện thoại (dùng như app).
- Quên mật khẩu:
  - Tài khoản có email → gửi link đặt lại qua email.
  - Không có email → màn hình hướng dẫn "Liên hệ trưởng nhóm / quản trị viên để đặt lại". QTV có nút **Đặt lại mật khẩu** → sinh mật khẩu tạm, `must_change_password = true`.
- `must_change_password = true` thì sau đăng nhập bắt buộc đổi mật khẩu trước khi làm gì khác.

## 2. Đăng ký nhóm (trưởng nhóm)

Form một trang, mobile-first:

| Phần | Trường |
| --- | --- |
| Bạn là ai | Họ tên*, SĐT*, Email*, Mật khẩu* |
| Nhóm | Tên nhóm*, Mã nhóm* (tự gợi ý từ tên, kiểm tra trùng ngay khi gõ), Chế độ xem* (Công khai / Riêng tư), Số dư quỹ hiện có (số dư đầu kỳ, mặc định 0) |

- SĐT đã có tài khoản → báo "SĐT này đã có tài khoản, đăng nhập để tạo thêm nhóm".
- Sau khi tạo: nhóm hoạt động ngay; tạo `group_services` với `review_status = pending_review`; seed loại thu chi mặc định; trưởng nhóm được thêm vào danh bạ và `group_members` với vai trò `owner`; chuyển tới màn hình **Bắt đầu nhanh** (3 bước: thêm danh bạ → thêm thông tin ngân hàng → tạo phiếu/chiến dịch đầu tiên).
- Người đã đăng nhập tạo thêm nhóm từ `/nhom` (tối đa theo `platform_settings`, mặc định 5).
- Chống spam: giới hạn 3 lần đăng ký/giờ/IP, trường honeypot.

## 3. Vai trò trong nhóm

| Quyền | Trưởng nhóm | Quản trị viên | Thành viên | Khách (link public) |
| --- | :-: | :-: | :-: | :-: |
| Xem timeline, tóm tắt quỹ, trang chiến dịch (nhóm public) | ✓ | ✓ | ✓ | ✓ |
| Xem timeline (nhóm private) | ✓ | ✓ | ✓ | ✗ |
| Thả tim, bình luận | ✓ | ✓ | ✓ | ✗ |
| Ẩn/xóa bình luận | ✓ | ✓ | bình luận của mình | ✗ |
| Vào trang quản trị nhóm | ✓ | ✓ | ✗ | ✗ |
| Tạo/sửa/xóa phiếu thu chi, đọc bill AI | ✓ | ✓ | ✗ | ✗ |
| Xem ảnh bill chứng từ, lịch sử sửa phiếu | ✓ | ✓ | ✗ | ✗ |
| Chiến dịch, duyệt hàng chờ SePay | ✓ | ✓ | ✗ | ✗ |
| Danh bạ, cấp tài khoản, đặt lại mật khẩu | ✓ | ✓ | ✗ | ✗ |
| Báo cáo, xuất Excel | ✓ | ✓ | ✗ | ✗ |
| Loại thu chi, thông tin nhóm, chế độ xem, ngân hàng, SePay | ✓ | ✓ | ✗ | ✗ |
| Phong/hạ quản trị viên, chuyển trưởng nhóm, xóa nhóm | ✓ | ✗ | ✗ | ✗ |

Thực hiện bằng Policy của Laravel + middleware `group.role:admin`. Phía Vue chỉ ẩn nút; server luôn kiểm tra lại.

## 4. Danh bạ thành viên

- Danh sách: tìm theo tên, lọc đang tham gia / đã rời, cột "Tài khoản" (chưa có / đã cấp / vai trò).
- Thêm một người: Họ tên*, SĐT, Ngày sinh, Địa chỉ, Ghi chú.
- **Nhập nhanh hàng loạt**: ô dán văn bản, mỗi dòng một người, các cột cách bằng tab hoặc dấu phẩy theo thứ tự `Họ tên, SĐT, Ngày sinh, Địa chỉ` (dán thẳng từ Excel/Google Sheets được). Màn hình xem trước báo dòng lỗi/trùng SĐT trước khi lưu. Excel mất số 0 đầu SĐT thì tự thêm lại; ô ngày dạng số sê-ri của Excel cũng đọc được. Tải lên file .xlsx theo mẫu làm cùng phần Excel ở M5.
- **Cấp tài khoản**: yêu cầu dòng danh bạ có SĐT.
  - SĐT đã có tài khoản WePo (ở nhóm khác) → chỉ gắn tài khoản đó vào nhóm, không đổi mật khẩu của họ.
  - Chưa có → tạo tài khoản, mật khẩu tạm 8 ký tự, hiện **một lần** kèm nút "Sao chép lời nhắn" (gồm link đăng nhập, SĐT, mật khẩu tạm) để QTV gửi qua Zalo.
  - Chọn vai trò: Thành viên / Quản trị viên. **Chỉ trưởng nhóm** cấp được vai trò Quản trị viên (chốt khi làm M1, cho khớp quy tắc "phong/hạ QTV chỉ trưởng nhóm" ở mục 3). Quản trị viên chỉ cấp, đặt lại mật khẩu, thu hồi tài khoản Thành viên.
- Thu hồi tài khoản khỏi nhóm: xóa `group_members` (tài khoản vẫn dùng được ở nhóm khác).
- Đánh dấu "Đã rời nhóm": giữ lịch sử, không còn trong danh sách phải đóng của chiến dịch mới.

## 5. Chọn nhóm

`/nhom`: danh sách nhóm của tài khoản (ảnh, tên, vai trò, số dư nếu là QTV). Chỉ một nhóm thì vào thẳng. Đăng nhập từ link nhóm thì quay lại đúng nhóm đó.

## 6. Trang quản trị nền tảng `/system`

Dành cho nhân sự HIPOTECH, dữ liệu dịch vụ ở `cashflow_system`.

| Màn hình | Chức năng |
| --- | --- |
| Đăng nhập | Email + mật khẩu, giới hạn lần thử |
| Tổng quan | Số nhóm (mới trong 7 ngày, chờ duyệt, bị khóa), số người dùng, số phiếu, chi phí AI tháng này |
| Danh sách nhóm | Lọc theo trạng thái duyệt/dịch vụ, tìm theo mã/tên/SĐT trưởng nhóm (tìm SĐT qua hash) |
| Chi tiết nhóm | Thông tin, trưởng nhóm, số thành viên, số phiếu, dung lượng ảnh, lượt AI theo tháng. Nút: **Duyệt**, **Từ chối**, **Khóa/Mở khóa** (kèm lý do), **Sửa** (tên, mã nhóm, chế độ, hạn mức AI), **Xóa** (xóa mềm, xác nhận bằng cách gõ lại mã nhóm; xóa hẳn dữ liệu sau 30 ngày bằng lệnh định kỳ) |
| Tạo nhóm mới | Tạo hộ khách: thông tin trưởng nhóm + nhóm, giống form đăng ký; trả về mật khẩu tạm |
| Chi phí AI | Lượt đọc bill và chi phí theo nhóm/tháng |
| Nhật ký | `admin_audit_logs` |
| Quản trị viên | Thêm/khóa tài khoản `/system` |

Quản trị HIPOTECH **không** xem được SĐT/địa chỉ trong danh bạ của nhóm (chỉ SĐT trưởng nhóm để liên hệ).
