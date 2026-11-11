# Vào thẳng timeline nhóm

Ngày: 11/11/2026. Trạng thái: **đã làm**.

## Hiện tại

- Đăng nhập (hoặc nút "Vào nhóm" ở trang chủ) khi chỉ có một nhóm: thành viên vào `/{ma-nhom}`, còn trưởng nhóm / QTV bị đưa vào `/{ma-nhom}/admin`.
- Trang chọn nhóm `/groups`: bấm nhóm mình làm trưởng nhóm / QTV cũng vào `/{ma-nhom}/admin`.

## Thay đổi

- Mọi vai trò đều vào **timeline** `/{ma-nhom}`. Trưởng nhóm / QTV vào quản trị bằng nút "Quản trị" trên đầu trang nhóm (đã có sẵn).
- Sửa `homePath()` trong `resources/js/lib/session.js` và `link()` trong `GroupPickerPage.vue`.
- Menu tài khoản ở trang nhóm (nút avatar "Thành viên ▾") thêm mục **Đăng xuất** dưới "Chọn nhóm khác", có vạch ngăn. Đăng xuất xong về `/login`.
- **Giữ nguyên:** tạo nhóm xong vẫn vào `/{ma-nhom}/admin?welcome=1` để trưởng nhóm cài đặt nhóm lần đầu.
