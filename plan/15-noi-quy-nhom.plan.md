# Nội quy nhóm

Ngày: 11/11/2026. Trạng thái: **đã duyệt, đã làm** (11/11/2026).

## Hiện tại

- Trang nhóm `/{ma-nhom}` trên máy tính có cột phải gồm thẻ "Chiến dịch đang mở" và chân trang. Nhóm không có chiến dịch đang mở thì không có cột phải.
- Nhóm có trường **Mô tả** (sửa ở Cài đặt nhóm) nhưng trang nhóm không hiện. Chưa có chỗ ghi nội quy: mức đóng, hạn nộp, ai được hỗ trợ bao nhiêu khi hiếu hỉ…

## Đề xuất

- Thêm **Nội quy nhóm**: một đoạn chữ do trưởng nhóm / quản trị viên viết ở **Cài đặt nhóm**, ngay dưới ô Mô tả. Tối đa 3.000 ký tự, giữ xuống dòng, không định dạng chữ đậm/nghiêng.
- Trang nhóm hiện **box "Nội quy"** (biểu tượng cuốn sổ):
  - **Máy tính:** cột phải, dưới thẻ chiến dịch, trên chân trang. Nhóm không có chiến dịch đang mở vẫn có cột phải nếu có nội quy.
  - **Điện thoại:** dưới thẻ chiến dịch, trên dòng thời gian.
  - Nội quy dài hơn khoảng 6 dòng thì thu gọn, có nút **"Xem thêm"**.
- **Ai xem được:** giống dòng thời gian. Nhóm công khai ai có link cũng xem; nhóm riêng tư chỉ thành viên.
- **Chưa có nội quy:** khách và thành viên không thấy box. Trưởng nhóm / quản trị viên thấy box mờ "Chưa có nội quy" kèm nút **"Viết nội quy"** mở Cài đặt nhóm.

## Việc cần làm

1. **Database `groups`:** thêm cột `rules` (text, null).
2. **API:**
   - Cài đặt nhóm: thêm `rules` vào lấy và lưu (`nullable|string|max:3000`), cắt khoảng trắng thừa.
   - Trang nhóm (`GET /api/g/{group}` hoặc `summary`): trả `rules` khi người xem được phép xem dòng thời gian.
3. **Giao diện:**
   - `SettingsPage.vue`: ô "Nội quy nhóm" (textarea 6 dòng), gợi ý "Ví dụ: Mỗi người đóng 500.000 đ/năm trước 31/3. Hiếu hỉ nhóm hỗ trợ 500.000 đ…".
   - Component mới `GroupRules.vue` (box nội quy, thu gọn / Xem thêm), đặt trong `GroupHomePage.vue` theo vị trí ở trên.
4. **Test:** lưu và đọc `rules`; nhóm riêng tư không lộ nội quy cho khách; quá 3.000 ký tự bị từ chối.
5. **Nhóm demo** (máy dev và server): viết sẵn một nội quy mẫu cho `nhomdemo`.

## Ngoài phạm vi

- Định dạng chữ (đậm, danh sách), đính kèm file.
- Thành viên bấm "Đã đọc nội quy", lịch sử sửa nội quy.

## Đã chốt khi làm (11/11/2026)

1. Tên box: **"Nội quy"**.
2. Chỉ hiện nội quy, chưa hiện Mô tả nhóm.
3. Nút "Viết nội quy" / "Sửa" mở `/{ma-nhom}/admin/settings#rules`: trang Cài đặt tự cuộn tới ô nội quy.
4. `DemoSeeder` có hằng `DEMO_RULES` (6 điều) cho `nhomdemo`.
