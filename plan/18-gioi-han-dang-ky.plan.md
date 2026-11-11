# Giới hạn đăng ký: không chặn người nhập sai

Ngày: 11/11/2026. Trạng thái: **đã làm, chưa deploy**.

## Lỗi

- Khách đăng ký qua link `/chi-doan-a12-2005-2008/register` bị toast đỏ **"Too Many Attempts."**.
- Log nginx trên server: cùng một IP gửi 3 lần bị lỗi nhập liệu (422) trong khoảng 1 phút, từ lần thứ 4 bị 429.
- **Nguyên nhân:** giới hạn `register` (3 lần/giờ/IP, dùng chung cho `/register` và link xin vào nhóm) đếm **mọi lần bấm**, kể cả lần bị lỗi nhập liệu. Nhập sai 3 lần là bị khóa 1 tiếng. Thêm nữa: cả nhóm đăng ký cùng một Wi-Fi (buổi họp lớp) hoặc dùng 4G chung IP của nhà mạng thì 3 người đầu là hết lượt.
- Thông báo 429 là câu tiếng Anh mặc định của Laravel.

## Sửa

- **Giới hạn mới** (`AppServiceProvider`, tên `register`):
  - Tối đa **10 tài khoản tạo thành công**/giờ/IP. Chỉ đếm lần trả 201; nhập sai (422) không mất lượt.
  - Tối đa **30 lần gửi**/giờ/IP (đếm mọi lần) để chống spam.
- **Thông báo 429 tiếng Việt** cho mọi API: "Bạn thử quá nhiều lần. Đợi N phút rồi thử lại nhé." (dưới 1 phút thì ghi giây), xử lý trong `bootstrap/app.php`.
- Test trong `RegisterTest`: nhập sai 5 lần vẫn đăng ký được; 10 tài khoản/giờ; 30 lần gửi thì bị chặn kèm câu tiếng Việt.

## Lưu ý khi deploy

- Bộ đếm cũ của IP đang bị chặn hết hạn sau 1 tiếng kể từ lần bị chặn (khoảng 11:18 ngày 11/11/2026). Muốn mở ngay thì xóa cache rate limiter trên server.
