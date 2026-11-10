# 05 — Chiến dịch thu, mã QR, tích hợp SePay

## 1. Chiến dịch thu

Ví dụ "Thu quỹ năm 2026", mỗi người 500.000đ.

**Tạo chiến dịch** (quản trị nhóm): Tên* (không trùng tên chiến dịch khác trong nhóm), Loại thu* (mặc định Đóng quỹ năm), Số tiền mỗi người (để trống = tùy tâm), Mã nội dung chuyển khoản (không bắt buộc), Mô tả, **Người được miễn**.

- Mọi người đang tham gia trong danh bạ đều phải đóng; QTV chỉ chọn ai **được miễn**. Người thêm vào danh bạ sau khi tạo chiến dịch tự vào danh sách phải đóng của các chiến dịch đang mở.
- Bỏ ô Từ ngày – Đến ngày và Mục tiêu tổng khỏi form (chốt ngày 10/11/2026). Tiến độ tính theo tổng số phải đóng của mọi người.
- Ô bắt buộc có dấu `*` đỏ ở nhãn.

**Mã nội dung chuyển khoản** (`transfer_code`): không bắt buộc nhập, để trống thì tự sinh; duy nhất toàn hệ thống, chỉ chữ in hoa và số (ngân hàng hay bỏ dấu cách, gạch, ký tự đặc biệt trong nội dung), vd `QNA12N26`. QTV sửa được khi chưa có giao dịch nào.

**Trang chiến dịch** `/{ma-nhom}/chien-dich/{slug}` — ai xem được timeline thì xem được:

```
┌──────────────────────────────┐
│ Thu quỹ năm 2026              │
│ 500.000đ / người · đến 31/12  │
│ ▓▓▓▓▓▓▓░░░ 30/42 người        │
│ 15.000.000 / 21.000.000đ      │
├──────────────────────────────┤
│        [ MÃ QR ]              │
│ MB Bank · 0123 456 789        │
│ NGUYEN VAN A                  │
│ Số tiền: 500.000đ   [Chép]    │
│ Nội dung: QNA12N26 <Tên bạn>  │
│                       [Chép]  │
│ [Tải ảnh QR]                  │
├──────────────────────────────┤
│ Đã đóng (30) │ Chưa đóng (12) │
│ ✓ Trần Văn Minh   08/11       │
│ ✓ Lê Thị Hoa      07/11       │
└──────────────────────────────┘
```

- Ảnh QR lấy từ dịch vụ QR của SePay (VietQR), không cần khóa:
  `https://qr.sepay.vn/img?acc={STK}&bank={bank_code}&amount={số tiền}&des={transfer_code}`
  QR chung một nội dung là mã chiến dịch; trang hướng dẫn người đóng **thêm tên mình** vào sau mã. Số tiền điền sẵn khi chiến dịch có số tiền mỗi người.
- Nút **Tải ảnh QR** để lưu về máy rồi mở app ngân hàng quét từ thư viện (đang xem trên chính điện thoại thì không quét được màn hình của mình).
- Nhóm chưa khai báo ngân hàng → trang chiến dịch không hiện QR, chỉ hiện tiến độ.
- Danh sách đã đóng/chưa đóng chỉ hiện **tên**; đóng thiếu hiện "đã đóng 300.000/500.000". Không bao giờ hiện SĐT.
- Chiến dịch "Đóng" thì không hiện QR nữa, vẫn xem được kết quả.
- Nút "Chia sẻ" dùng Web Share API (mở Zalo/Messenger trên điện thoại), dự phòng là chép link.

Trong quản trị, chi tiết chiến dịch thêm: SĐT của người chưa đóng (bấm gọi), nút **Chép danh sách chưa đóng** để dán nhắc trong nhóm Zalo, ghi nhận tay "đã đóng tiền mặt" (tạo phiếu thu gắn chiến dịch + người đóng), xuất Excel.

## 2. Thiết lập SePay của nhóm

Mỗi nhóm dùng **tài khoản SePay riêng** của trưởng nhóm/thủ quỹ, liên kết với tài khoản ngân hàng nhận quỹ.

Màn hình **Cài đặt → Ngân hàng và SePay** trong quản trị nhóm:

1. Nhập ngân hàng (chọn từ danh sách ngân hàng của SePay QR), số tài khoản, tên chủ tài khoản → đủ để hiện QR, chưa cần SePay.
2. Bật "Tự nhận biến động số dư qua SePay" → hệ thống sinh **API key** ngẫu nhiên 40 ký tự cho nhóm và hiển thị:
   - **Webhook URL**: `https://quynhom.hipotech.vn/api/webhooks/sepay/{ma-nhom}` [Chép]
   - **API key** [Chép] [Tạo lại]
   - Hướng dẫn từng bước có ảnh minh họa: đăng ký my.sepay.vn → liên kết ngân hàng → *Tích hợp WebHooks* → *Thêm webhooks*: chọn tài khoản ngân hàng, sự kiện "Có tiền vào" và "Có tiền ra", URL ở trên, kiểu chứng thực **API Key**, dán API key, "Bỏ qua nếu nội dung không có mã thanh toán" = **Không**.
3. Trạng thái kết nối: "Chưa nhận webhook nào" / "Đã kết nối — lần cuối 08/11 14:02". Có nút **Gửi thử** hướng dẫn dùng chức năng giả lập giao dịch của SePay.

## 3. Xử lý webhook

`POST /api/webhooks/sepay/{ma-nhom}` (không CSRF, giới hạn 120 request/phút/nhóm).

Payload SePay gửi (JSON):

```json
{
  "id": 92704,
  "gateway": "MBBank",
  "transactionDate": "2026-11-08 14:02:37",
  "accountNumber": "0123456789",
  "code": null,
  "content": "QNA12N26 Tran Van Minh",
  "transferType": "in",
  "transferAmount": 500000,
  "accumulated": 19077000,
  "subAccount": null,
  "referenceCode": "MBVCB.3278907687",
  "description": ""
}
```

Các bước:

1. Tìm nhóm theo mã; không có, đã xóa, hoặc bị khóa → 404/423, không lưu.
2. Kiểm tra header `Authorization: Apikey {key}` bằng so sánh thời gian hằng (`hash_equals`) với `sepay_api_key` của nhóm → sai thì 401.
3. `accountNumber` phải khớp số tài khoản nhóm đã khai báo → khác thì vẫn lưu nhưng gắn cờ "khác tài khoản" để QTV xem (trường hợp nhóm đổi tài khoản).
4. **Chống trùng**: `sepay_id` đã có trong nhóm → trả 200 luôn, không làm gì (SePay gửi lại khi lần trước không nhận được phản hồi).
5. Lưu vào `sepay_transactions` trạng thái `pending`, kèm `raw`.
6. Nhận diện: chuẩn hóa nội dung (bỏ dấu, chữ hoa, bỏ ký tự không phải chữ/số) rồi tìm `transfer_code` của các chiến dịch **đang mở** của nhóm → `matched_campaign_id`. Phần chữ còn lại sau mã (bỏ các từ như "chuyen tien", "CK", mã tham chiếu ngân hàng) so với danh bạ để ra `suggested_contact_id`; nếu không ra thì thử tên người chuyển trong `description`.
7. Cập nhật `sepay_last_hook_at`; đẩy job email thông báo (gộp 15 phút).
8. Trả `200 {"success": true}` — SePay chỉ coi là thành công khi nhận đúng phản hồi này, nếu không sẽ gửi lại.

Phần nặng (nhận diện, email) làm trong transaction DB ngắn hoặc job; webhook phải trả lời trong vài trăm ms.

## 4. Hàng chờ duyệt

Quản trị nhóm → **Chờ duyệt** (có số đỏ trên menu):

- Mỗi dòng: tiền vào (xanh) / ra (đỏ), số tiền, thời gian, nội dung CK, chiến dịch nhận ra, người đóng gợi ý.
- Duyệt nhanh một chạm khi đã nhận ra đủ chiến dịch và người đóng → tạo phiếu thu `source = sepay`, `occurred_at = transactionDate`.
- Bấm vào để mở form phiếu đã điền sẵn (như AI đọc bill) → sửa loại, người, tiêu đề, thêm ảnh → Lưu.
- Tiền ra → form phiếu chi điền sẵn; QTV chọn loại (Thăm ốm...) và thêm ảnh sự kiện.
- **Bỏ qua** (giao dịch không liên quan quỹ, vd tiền lãi, chuyển nhầm) kèm lý do; xem lại được ở tab "Đã bỏ qua", khôi phục được.
- Duyệt hàng loạt các dòng đã nhận ra đủ thông tin.
- Một giao dịch SePay chỉ sinh tối đa một phiếu (`transactions.sepay_transaction_id` unique). Xóa phiếu đó thì giao dịch quay về `pending`.

Người đóng tiền **chỉ được tính là đã đóng sau khi QTV duyệt**.

## 5. Kiểm thử

- Feature test webhook: sai API key, nhóm bị khóa, gửi trùng `id`, nhận ra mã chiến dịch có dấu cách/chữ thường (`qna12 n26`), gợi ý người đóng có dấu/không dấu, tiền ra.
- Lệnh `php artisan cashflow:sepay-simulate {ma-nhom} {so-tien} "{noi-dung}"` gửi webhook giả vào máy local để thử luồng mà không cần SePay.
