# Form gửi bill: bỏ ô số tiền, quên chụp bill thì gõ số tiền

Ngày: 11/11/2026. Trạng thái: **đã làm** (11/11/2026). Làm tiếp [plan/20](20-gui-bill-de-hon.plan.md).

## Vấn đề

Phản hồi của chủ dự án về form gửi bill sau plan 20:

1. **Hai chỗ cùng làm một việc.** Đã có ô "Chọn ảnh bill" to, nút cuối form lại ghi "Chọn ảnh bill để gửi" và cũng mở chỗ chọn ảnh.
2. **Ô "Số tiền đã chuyển" thừa**, vì số tiền đã có trên ảnh bill và AI đọc được.
3. **Thiếu đường cho người quên chụp bill**: hiện bắt buộc có ảnh, không có ảnh thì không gửi được.

## Sẽ làm

### 1. Form gửi bill (`ClaimSheet.vue`)

Mặc định, form gửi **có ảnh bill**:

- Người đóng (một dòng, như plan 20) → ô **Chọn ảnh bill** → Ghi chú → nút **Gửi đi**.
- Không còn ô số tiền.
- Nút **Gửi đi** bị mờ, không bấm được tới khi chọn ảnh. Bỏ chữ "Chọn ảnh bill để gửi".

Ngay dưới ô chọn ảnh có link nhỏ **"Quên chụp bill? Nhập số tiền"**. Bấm vào thì chuyển sang kiểu **không có ảnh**:

- Ô chọn ảnh ẩn đi, thay bằng ô **Số tiền đã chuyển** (bắt buộc). Số tiền điền sẵn số họ phải đóng, đóng thiếu thì điền phần còn thiếu, như hiện nay.
- Dòng nhắc: "Không có bill, thủ quỹ sẽ đối chiếu với sao kê. Nên ghi thêm giờ chuyển vào ghi chú."
- Link **"Có ảnh bill rồi"** để quay lại kiểu có ảnh.
- Nút **Gửi đi** bấm được khi đã có số tiền.

### 2. Máy chủ: nhận bill không có số tiền, hoặc số tiền không có bill

- `POST /campaigns/{slug}/claims`:
  - `bill`: không bắt buộc.
  - `amount`: chỉ bắt buộc khi không có `bill`.
  - Không có cả hai thì báo "Chọn ảnh bill, hoặc nhập số tiền nếu quên chụp bill."
- Migration: cột `payment_claims.amount` cho phép trống (null = người gửi không khai, dùng số AI đọc).
- Gửi có ảnh: như hiện nay (chống trùng ảnh, AI đọc sau khi trả lời người gửi), `amount` để trống.
- Gửi không ảnh: không lưu bill, không gọi AI. Vẫn "một người một bill chờ" mỗi chiến dịch: gửi lại thì thay bản chờ cũ. Giới hạn 5 lần/giờ mỗi IP giữ nguyên.

### 3. Hàng chờ duyệt của QTV

- **Số tiền hiện ở dòng bill:** người gửi khai → AI đọc được → mức phải đóng của người đó.
- **Số tiền khi duyệt:** AI đọc được → người gửi khai → mức phải đóng của người đó. Không có số nào thì nút **Duyệt** đổi thành **"Nhập số tiền"**, mở form phiếu thu như hiện có.
- **Cảnh báo "Số tiền AI đọc khác số người gửi khai"**: chỉ hiện khi người gửi có khai số tiền.
- **Bill không có ảnh:** dòng ghi **"Không có ảnh bill, đối chiếu với sao kê"** (hiện đang ghi "Không có ảnh"). Khớp mềm với giao dịch SePay vẫn chạy theo số tiền khai và tên người.

## Không làm

- Không bắt người gửi xem lại số AI đọc trước khi gửi: AI chạy sau khi gửi, QTV mới là người kiểm.

## Kiểm thử

- Feature test gửi bill:
  - Có ảnh, không có số tiền: tạo được, `amount` null, AI được gọi.
  - Không ảnh, có số tiền: tạo được, không có `bill_scan_id`.
  - Không có cả hai: lỗi 422 với thông báo trên.
  - Gửi lại khi đang có bản chờ: thay bản cũ (cả hai kiểu).
- Feature test duyệt:
  - Bill không khai số tiền và AI không đọc được: duyệt lấy mức phải đóng.
  - Không có mức phải đóng (người ngoài danh sách): báo cần nhập số tiền.
  - Cảnh báo lệch số tiền chỉ hiện khi có số khai.
- Thử trên trình duyệt cỡ điện thoại:
  - Nút **Gửi đi** mờ tới khi chọn ảnh.
  - Bấm "Quên chụp bill?" thì hiện ô số tiền, gửi được.
  - Hàng chờ QTV hiện đúng hai kiểu bill.

## Ghi chú khi làm

- Migration `2026_11_11_000020_make_payment_claims_amount_nullable`: deploy tự chạy (`cashflow:migrate --force`).
- Hàng chờ QTV: dòng bill ghi thêm nguồn số tiền ("người gửi tự nhập số tiền" hoặc "số tiền theo mức phải đóng"). Không có số nào thì hiện "Chưa có số tiền" và nút **Nhập số tiền** mở form phiếu thu (như nút Sửa).
- Gửi lại mà không kèm ảnh: giữ ảnh của bản chờ cũ để thủ quỹ đối chiếu.
- Mức phải đóng = `expected_amount` của người đó trong chiến dịch, không có thì mức chung `amount_per_member`; người được miễn hoặc ngoài danh sách thì không có.

