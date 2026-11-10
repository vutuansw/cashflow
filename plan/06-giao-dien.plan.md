# 06 — Giao diện

Một SPA Vue 3 (`resources/js`), Tailwind 4, icon lucide, cấu trúc giống chatbot: `pages/`, `layouts/`, `components/`, `lib/` (`api.js`, `money.js`, `time.js`, `session.js`...).

## 1. Nguyên tắc

- **Mobile-first**: thiết kế cho màn 360–430px trước, máy tính là bản mở rộng. Không cuộn ngang ở 360px.
- Vùng chạm tối thiểu 44px; nút chính ở tầm ngón cái (đáy màn hình).
- Ô số tiền dùng `inputmode="numeric"`, tự thêm dấu chấm hàng nghìn; hiển thị tiền dạng `1.500.000 đ`, rút gọn `1,5tr` ở chỗ chật.
- Ngày giờ theo kiểu Việt Nam `08/11/2026`, mốc tương đối trên timeline ("hôm qua", "3 ngày trước").
- Có chế độ tối theo hệ điều hành.
- Màu: xanh lá (thu, thương hiệu), đỏ (chi), trung tính cho nền. Màu loại thu chi do nhóm chọn.
- Toàn bộ chữ tiếng Việt, xưng "bạn".

## 2. Bố cục

**Khu xem nhóm** (`/{ma-nhom}`, `/chien-dich/...`): thanh trên cùng (ảnh + tên nhóm, nút Đăng nhập hoặc avatar). QTV có thêm nút "Quản trị".

**Khu quản trị nhóm** (`/{ma-nhom}/quan-tri`):
- Mobile: thanh tab dưới đáy 5 mục — **Tổng quan · Phiếu · [+] · Chờ duyệt · Thêm**. Nút `+` giữa mở bảng chọn: *Đọc bill* / *Phiếu thu* / *Phiếu chi*. "Thêm" mở danh sách: Chiến dịch, Danh bạ, Báo cáo, Loại thu chi, Cài đặt nhóm, Xem timeline.
- Máy tính: sidebar trái với cùng các mục.

**Điều hướng màn con** (chốt 10/11/2026): màn đi sâu (chi tiết/sửa phiếu, chi tiết/sửa chiến dịch, nhập danh bạ, các mục vào từ "Thêm", chi tiết nhóm ở `/system`) có nút ‹ trên điện thoại và breadcrumb các cấp cha trên máy tính. Nút ‹ lùi lịch sử nếu đi từ trong app (giữ bộ lọc, vị trí cuộn), mở thẳng từ link thì về trang cha; cần cho PWA vì không có nút back của trình duyệt. Màn cấp 1 (tab đáy, sidebar) không có nút ‹.

**Khu `/system`**: bố cục sidebar đơn giản, ưu tiên máy tính nhưng vẫn dùng được trên điện thoại.

## 3. Danh sách màn hình

| Khu | Màn hình |
| --- | --- |
| Chung | Trang giới thiệu, Đăng ký nhóm, Đăng nhập, Quên mật khẩu, Đặt lại mật khẩu, Đổi mật khẩu bắt buộc, Chọn nhóm, Hồ sơ cá nhân, Trang 404 / nhóm bị khóa / nhóm riêng tư |
| Xem nhóm | Timeline + thống kê, Chi tiết phiếu (ảnh, tim, bình luận), Trang chiến dịch |
| Quản trị nhóm | Tổng quan, Bắt đầu nhanh, Danh sách phiếu, Tạo/sửa phiếu, Đọc bill, Chờ duyệt SePay, Chiến dịch (danh sách, tạo/sửa, chi tiết), Danh bạ (danh sách, thêm/sửa, nhập hàng loạt), Tài khoản & vai trò, Loại thu chi, Báo cáo, Cài đặt nhóm (thông tin, ảnh, chế độ xem, số dư đầu kỳ, ngân hàng & SePay, email thông báo, chuyển trưởng nhóm, xóa nhóm) |
| /system | Đăng nhập, Tổng quan, Danh sách nhóm, Chi tiết/sửa nhóm, Tạo nhóm, Chi phí AI, Nhật ký, Quản trị viên |

## 4. PWA

- `public/manifest.webmanifest`: tên "Quỹ nhóm", `short_name` "Quỹ nhóm", icon 192/512 + maskable, `display: standalone`, màu chủ đạo.
- Service worker nhỏ tự viết (`public/sw.js`): chỉ cache file tĩnh đã build (js/css/font/icon) theo kiểu cache-first; **không cache API và ảnh nhóm private**. Có trang "Mất kết nối" khi offline.
- Hiện gợi ý "Thêm vào màn hình chính" một lần trong khu quản trị (Android dùng sự kiện `beforeinstallprompt`, iOS hiện hướng dẫn Chia sẻ → Thêm vào MH chính).

## 5. Wireframe

Trước khi code giao diện ở mốc M2, dựng nhanh wireframe tĩnh 4 màn chính (Timeline, Tạo phiếu bằng bill, Chờ duyệt, Trang chiến dịch) ở khổ điện thoại để chủ dự án duyệt bố cục.
