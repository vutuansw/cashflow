# 03 — Thu chi, timeline, dashboard, báo cáo

## 1. Loại thu chi

- Mặc định khi tạo nhóm: Thu **Đóng quỹ năm**; Chi **Liên hoan, Đám hiếu, Đám hỉ, Thăm ốm, Thăm đẻ**. Danh sách nằm ở `config/cashflow.php`.
- QTV thêm/sửa/sắp xếp/ẩn loại; chọn icon (bộ lucide có sẵn) và màu.
- Không xóa được loại đã có phiếu, chỉ ẩn.

## 2. Phiếu thu chi

Màn hình **Tạo phiếu** (mở bằng nút `+` nổi trên mobile), hai cách vào:

1. **Chụp/chọn bill chuyển khoản** → AI đọc → form được điền sẵn (xem [04](04-ai-doc-bill.plan.md)).
2. **Nhập tay**.

Form:

| Trường | Ghi chú |
| --- | --- |
| Thu / Chi | công tắc lớn trên cùng |
| Số tiền* | bàn phím số, tự định dạng `1.500.000`, nút nhanh `+000` |
| Loại* | chip chọn nhanh theo loại thu/chi |
| Ngày giờ* | mặc định bây giờ |
| Tiêu đề* | gợi ý tự động: "{Loại} {tên người}" vd "Thăm ốm bạn Lan" |
| Người đóng / người nhận | chọn từ danh bạ (tìm không dấu) hoặc gõ tên tự do |
| Chiến dịch | chỉ với phiếu thu; chọn chiến dịch đang mở, mặc định chiến dịch mới nhất khi nhập tay (phiếu từ bill/SePay giữ chiến dịch nhận ra được) |
| Mô tả | |
| Ảnh sự kiện | nhiều ảnh, chọn từ thư viện điện thoại; hiện trên timeline |
| Ảnh bill | ảnh chứng từ, chỉ QTV xem |
| Hiện trên timeline | mặc định bật |

- Sửa/xóa phiếu: chỉ QTV; mọi thay đổi ghi `transaction_histories`. Xóa là xóa mềm, khôi phục được trong 30 ngày.
- Danh sách phiếu trong quản trị: lọc theo khoảng ngày, thu/chi, loại, chiến dịch, nguồn (tay/AI/SePay); tổng thu, tổng chi của bộ lọc.

## 3. Ảnh

- Lưu trên đĩa **riêng tư** `storage/app/private/groups/{group_id}/...`, không đặt trong `public/`. Phục vụ qua route `/media/{group_code}/{id}/{size}` kiểm tra quyền: nhóm public → ai cũng xem ảnh sự kiện; nhóm private → phải là thành viên; ảnh bill → chỉ QTV.
- Khi tải lên: chấp nhận jpeg/png/webp/heic (Safari iOS thường tự đổi HEIC sang JPEG khi chọn ảnh qua `<input type=file accept="image/*">`); xoay theo EXIF rồi **xóa EXIF** (ảnh điện thoại có vị trí GPS); thu cạnh dài về 1600px (bản lớn) và 480px (thumbnail), lưu webp chất lượng 80.
- Giới hạn 10 MB/ảnh trước xử lý, 10 ảnh/phiếu. Thu nhỏ phía trình duyệt trước khi gửi (canvas) để tiết kiệm 4G.
- Header `Cache-Control: private` cho nhóm private, `public, max-age=31536000, immutable` cho nhóm public (đường dẫn có hash).

## 4. Timeline nhóm `/{ma-nhom}`

Bố cục như trang nhóm Facebook, mobile-first:

```
┌──────────────────────────────┐
│ Ảnh bìa                       │
│ (Ảnh đại diện) Chi đoàn A12   │
│ Công khai · 42 thành viên     │
├──────────────────────────────┤
│ Số dư quỹ   12.450.000 đ      │
│ Thu 2026  +18.000.000         │
│ Chi 2026   -5.550.000         │
│ [Biểu đồ thu chi 12 tháng]    │
├──────────────────────────────┤
│ Chiến dịch đang mở            │
│ Thu quỹ năm 2026  ▓▓▓▓░ 30/42 │
│ [Đóng quỹ ngay]               │
├──────────────────────────────┤
│ Lọc: Tất cả · Thu · Chi · Loại│
├──────────────────────────────┤
│ ── Tháng 11/2026 ──           │
│ [icon] Thăm ốm bạn Lan  -500k │
│ 08/11 · Thăm ốm               │
│ Mô tả...                      │
│ [ảnh][ảnh][ảnh]               │
│ ♥ 12   💬 3                   │
└──────────────────────────────┘
```

- Mỗi phiếu là một thẻ: icon và màu của loại, tiêu đề, số tiền (xanh thu, đỏ chi), ngày, loại, mô tả, lưới ảnh (bấm xem phóng to, vuốt qua lại), tim và bình luận.
- Phiếu thu lẻ của chiến dịch được **gộp theo ngày** thành một thẻ "Thu quỹ năm 2026 — 8 người đóng hôm nay, +4.000.000" để timeline không bị ngập; bấm mở ra danh sách tên.
- Cuộn vô hạn theo trang 20 phiếu, chia mốc theo tháng; lọc theo thu/chi, loại, năm.
- Thẻ tóm tắt và biểu đồ đặt trên đầu timeline; tab "Thống kê" trên mobile.
- Nhóm private chưa đăng nhập → màn hình "Nhóm riêng tư, đăng nhập để xem" (chỉ hiện tên và ảnh nhóm).
- Mọi trang nhóm có `<meta name="robots" content="noindex">` ("public" nghĩa là ai có link, không phải để Google tìm). Có thẻ Open Graph (tên nhóm, ảnh bìa) để dán link Zalo/Facebook ra bản xem trước đẹp.

## 5. Tim và bình luận

- Chỉ tài khoản thuộc nhóm. Tim bật/tắt một chạm. Bình luận văn bản thuần, tối đa 1000 ký tự, giới hạn 10 bình luận/phút/người.
- QTV ẩn bình luận (người viết vẫn thấy với nhãn "đã bị ẩn") hoặc xóa. Người viết sửa/xóa bình luận của mình.
- Bình luận hiện ngay dưới phiếu trên timeline, không mở modal: sẵn 5 bình luận mới nhất (cũ trên, mới dưới), quá 5 thì có "Xem thêm bình luận" để tải tiếp 10 bình luận cũ hơn mỗi lần. Bấm "Bình luận" mở ô viết ngay dưới phiếu; gửi hoặc sửa xong thì ô viết tự đóng.
- Không có thông báo realtime ở bản đầu; bình luận mới của người khác hiện khi tải lại timeline.

## 6. Dashboard

**Trên timeline (ai xem được timeline đều thấy):** số dư, tổng thu/chi năm đang chọn, biểu đồ cột thu/chi theo tháng, biểu đồ tròn cơ cấu chi theo loại, tiến độ chiến dịch đang mở.

**Trong quản trị — Tổng quan:** như trên, thêm:
- Số giao dịch SePay đang chờ duyệt (nút đi tới hàng chờ).
- Danh sách người chưa đóng của chiến dịch đang mở.
- 5 phiếu gần nhất, ai tạo.
- So sánh với cùng kỳ năm trước.

Truy vấn tổng hợp dùng `SUM ... GROUP BY YEAR/MONTH, category_id` trên index (group_id, occurred_at); cache 5 phút theo nhóm, xóa khi có phiếu thay đổi.

## 7. Báo cáo và xuất Excel

Màn hình **Báo cáo** trong quản trị: chọn khoảng thời gian (tháng này, năm nay, tùy chọn).

| Báo cáo | Nội dung | Xuất Excel |
| --- | --- | --- |
| Sổ quỹ | Số dư đầu kỳ, từng phiếu theo ngày (thu, chi, tồn lũy kế), số dư cuối kỳ | ✓ |
| Tổng hợp theo loại | Tổng thu/chi từng loại, tỷ lệ | ✓ |
| Theo tháng | Thu, chi, chênh lệch từng tháng | ✓ |
| Chiến dịch | Từng người: phải đóng, đã đóng, còn thiếu, ngày đóng | ✓ |

File Excel tạo bằng PhpSpreadsheet, đồng bộ (dữ liệu nhóm nhỏ), tên file `wepo-{ma-nhom}-so-quy-2026.xlsx`, định dạng tiền `#,##0`, dòng tổng in đậm, cố định hàng tiêu đề.

## 8. Thông báo email

Gửi qua hàng đợi tới `notify_emails` của nhóm (trống thì gửi trưởng nhóm):
- Có giao dịch SePay mới vào hàng chờ (gộp: tối đa 1 email/15 phút/nhóm, liệt kê các giao dịch mới).
- Nhóm bị khóa/mở khóa bởi `/system`.

QTV tắt được từng loại thông báo trong cài đặt nhóm.
