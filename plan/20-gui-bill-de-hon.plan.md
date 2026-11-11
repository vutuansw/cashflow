# Gửi bill dễ hơn: tự nhận người đăng nhập, chỗ chọn ảnh bill dễ thấy

Ngày: 11/11/2026. Trạng thái: **đã làm** (11/11/2026).

## Vấn đề

Phản hồi từ người dùng nhóm Chi Đoàn A12 (ảnh chụp iPhone 11/11/2026):

1. **Đã đăng nhập vẫn phải chọn "Bạn là ai?"**. Thành viên đã đăng nhập, trang đóng quỹ đã biết tên họ (nội dung CK hiện `MINH HIEN CHIDOAN26`). Nhưng mở form gửi bill vẫn hiện ô tìm tên và cả danh sách người trong nhóm để tự tìm mình.
2. **Chỗ chọn ảnh bill khó thấy.** Ô "Chọn ảnh chụp màn hình giao dịch" là khung viền nét đứt, chữ nhỏ, lại nằm dưới danh sách tên dài nên dễ bị bỏ qua. Nút "Gửi bill" bị mờ mà người dùng không hiểu vì sao.
3. **Thẻ "Tôi đã chuyển khoản, gửi bill"** trên trang đóng quỹ cũng trông giống một dòng chữ, không giống nút bấm.

## Sẽ làm

### 1. Tự nhận người đang đăng nhập

**Máy chủ:** API trang đóng quỹ (`GET /api/g/{ma-nhom}/campaigns/{slug}`) trả thêm `me`:

- Thành viên nhóm có gắn danh bạ: `{ contact_id, name, status, expected, paid, pending_claim }`, lấy từ dòng của chính họ trong danh sách người đóng. Không có trong danh sách (vd không thuộc chiến dịch) thì vẫn trả `contact_id` và tên trong danh bạ.
- Khách, hoặc thành viên chưa gắn danh bạ: `me = null`, form giữ như hiện nay.

**Form gửi bill (`ClaimSheet.vue`):**

- Có `me`: chọn sẵn người gửi là họ. Phần "Bạn là ai?" chỉ còn một dòng **"Người đóng: Minh Hiền"**, kèm nút nhỏ **"Đóng hộ người khác"** để mở lại danh sách khi cần.
- Số tiền điền sẵn như khi tự chọn tên: đóng thiếu thì điền phần còn thiếu.
- Vẫn hỏi lại như hiện nay nếu họ đã đóng đủ hoặc đang có bill chờ xác nhận.

**Chặn gửi nhầm tên khác:** máy chủ không đổi. `contact_id` gửi lên vẫn do người dùng chọn, vì đóng hộ người khác là chuyện thật trong nhóm.

### 2. Chỗ chọn ảnh bill nổi bật

Trong form gửi bill:

- **Đưa ảnh bill lên đầu form.** Có `me` thì thứ tự là: Người đóng (một dòng) → Ảnh bill → Số tiền → Ghi chú. Khách vẫn chọn tên trước, nhưng danh sách tên thu gọn còn khoảng 4 dòng cuộn, để thấy được ô ảnh bill ngay bên dưới.
- **Ô chọn ảnh trông như nút:** nền xanh nhạt, viền liền màu chính, biểu tượng to hơn, chữ to **"Chọn ảnh bill"** cùng dòng nhỏ "Ảnh chụp màn hình chuyển khoản thành công trong app ngân hàng".
- **Nút Gửi khi chưa có ảnh** đổi chữ thành **"Chọn ảnh bill để gửi"** thay vì chỉ mờ đi. Bấm vào thì mở luôn chỗ chọn ảnh.
- **Có ảnh rồi:** hiện ảnh thu nhỏ kèm nút **Đổi ảnh**, như hiện nay.

### 3. Thẻ "Tôi đã chuyển khoản, gửi bill" trên trang đóng quỹ

- Đổi thành **nút nền màu chính (xanh), rộng hết thẻ**: "Tôi đã chuyển khoản, gửi bill". Dòng phụ "Gửi ảnh bill để thủ quỹ ghi nhận cho bạn" chuyển xuống dưới, chữ nhỏ.
- Vị trí giữ nguyên, ngay dưới thẻ chuyển khoản.

## Không làm

- Không bắt buộc đăng nhập mới gửi được bill: khách ở nhóm công khai vẫn gửi như hiện nay.
- Không tự gửi bill khi chọn ảnh: người dùng vẫn bấm Gửi để kịp xem lại số tiền.

## Kiểm thử

- Feature test API trang đóng quỹ:
  - Thành viên gắn danh bạ: `me.contact_id` và `me.name` đúng là của họ.
  - Khách: `me = null`. Đăng nhập nhưng không thuộc nhóm: `me = null`.
  - Thành viên không có trong danh sách chiến dịch: vẫn có `me`.
- Thử trên trình duyệt cỡ điện thoại:
  - Thành viên mở form thấy sẵn tên mình, ô ảnh bill nằm ngay dưới.
  - Bấm "Đóng hộ người khác" thì hiện danh sách tên.
  - Chưa có ảnh: nút Gửi ghi "Chọn ảnh bill để gửi", bấm thì mở chỗ chọn ảnh.

## Đã chốt

- Giữ nút **"Đóng hộ người khác"** cho thành viên đăng nhập.

## Ghi chú khi làm

- Dòng người đóng tách hai dòng ("Người đóng" nhỏ ở trên, tên đậm ở dưới, kèm "(bạn)"): một dòng thì tên bị cắt vì nút "Đóng hộ người khác" dài.
- Danh sách chọn tên cho khách và khi đóng hộ cao khoảng 4 dòng (`max-h-44`), cuộn để xem thêm; tiêu đề đổi thành "Bạn đóng cho ai?".
- Một ô chọn tệp ẩn dùng chung cho ô "Chọn ảnh bill", nút "Đổi ảnh" và nút "Chọn ảnh bill để gửi".
