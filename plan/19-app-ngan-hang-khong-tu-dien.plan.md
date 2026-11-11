# App ngân hàng không còn tự điền, thêm "Lưu ảnh QR"

Ngày: 11/11/2026. Trạng thái: **đã làm** (11/11/2026), chờ thử trên điện thoại thật.

## Vấn đề

Người dùng MB Bank bấm app trong lưới "Chọn app ngân hàng" (plan/05 mục 5b): MB mở ra màn hình chính, không vào form chuyển tiền, không có số tiền, nội dung.

Kiểm tra ngày 11/11/2026 bằng link `https://dl.vietqr.io/pay?app=mb&ba=0123456789@mb&am=200000&tn=TEST123`:

| Máy | VietQR chuyển tới |
| --- | --- |
| iPhone | `mbbank://` |
| Android | `intent://#Intent;scheme=mbbank;package=com.mbmobile;…;end` |

- Số tài khoản, số tiền, nội dung đều bị VietQR bỏ. Thử thêm BIDV, VietinBank, ACB, OCB và nhiều cách ghi mã ngân hàng (`mb`, `MB`, `970422`): kết quả giống nhau, chỉ mở được app.
- Danh sách của VietQR vẫn ghi 5 app này `autofill = 1`, nên cờ này không còn đúng.
- MB Bank không có deeplink công khai nào điền sẵn form chuyển tiền.

Lỗi bên mình làm nặng thêm: app có nhãn **Tự điền** thì `BankAppSheet.vue` **không chép nội dung** trước khi mở. Người dùng MB vừa không được điền sẵn, vừa không có gì để dán. Nhãn "Tự điền" cũng đang hứa sai.

Cách duy nhất điền đủ thông tin cho mọi ngân hàng hiện nay là **mã QR**. Trên điện thoại thì lưu ảnh QR rồi trong app ngân hàng chọn Quét QR → chọn ảnh (MB, Vietcombank, Techcombank, BIDV… đều có).

## Sẽ làm

### 1. Bỏ "Tự điền", app nào cũng chép sẵn

- `BankApps::list()` không trả `autofill` nữa. Link deeplink vẫn kèm `ba`, `am`, `tn`: VietQR bật lại thì tự có, không hại gì.
- `BankAppSheet.vue`: bỏ nhãn **Tự điền** và câu giải thích về nó. Bấm app nào cũng:
  - Có nội dung (biết người xem): chép nội dung, nhắc "Đã chép nội dung. Trong app: nhập số TK, số tiền {X}, dán nội dung."
  - Không có nội dung (khách): chép số tài khoản, nhắc "Đã chép số tài khoản. Nhập số tiền {X}, ô nội dung để mặc định có tên bạn."
  - Đợi khoảng 1 giây cho kịp đọc rồi mới mở app (như hiện nay với app không tự điền).
- Dòng giải thích đầu lưới: "Chọn app để mở. Thông tin không tự điền sẵn, dùng các nút Chép hoặc **Lưu ảnh QR** để quét trong app."

### 2. Nút "Lưu ảnh QR" trên điện thoại

Trong thẻ chuyển khoản ở trang đóng quỹ, ngay dưới nút **Mở app ngân hàng để chuyển**:

- Nút phụ **Lưu ảnh QR** kèm một dòng hướng dẫn: "Mở app ngân hàng → Quét QR → chọn ảnh vừa lưu. App điền đủ số tài khoản, số tiền, nội dung."
- Bấm nút:
  - Trình duyệt chia sẻ được tệp (iPhone Safari, Chrome Android): mở bảng Chia sẻ với ảnh QR, người dùng chọn **Lưu hình ảnh**.
  - Không chia sẻ được: tải ảnh về (`QR-{mã chiến dịch}.png`).
- Ảnh QR to lên, đổi thẻ QR thu nhỏ hiện nay thành chỗ đặt nút này. Câu "Mã QR cho người khác quét hộ…" giữ lại, ngắn hơn.

**Lấy ảnh QR qua máy chủ mình.** Ảnh nằm ở `qr.sepay.vn` (khác domain) nên trình duyệt không cho tải hay chia sẻ thẳng. Thêm route `GET /api/g/{ma-nhom}/campaigns/{slug}/qr.png`:

- Máy chủ lấy ảnh từ `qr.sepay.vn` đúng như `qr_url` hiện nay (nội dung theo người xem), trả về `image/png`.
- Cache theo URL ảnh 1 ngày. SePay lỗi trả 502, trang báo "Chưa lưu được ảnh, giữ ngón tay lên mã QR để lưu."
- Cùng điều kiện xem như trang đóng quỹ (nhóm private thì phải là thành viên), chiến dịch phải đang mở.
- Giới hạn lượt gọi như các API công khai khác.

### 3. Cập nhật plan/05 mục 5b

Ghi lại: VietQR không còn chuyển thông tin cho app nào (kiểm 11/11/2026), bỏ nhãn tự điền, thêm Lưu ảnh QR. Thêm dòng 19 vào bảng trong `plan.md`.

## Không làm

- Không tự sinh mã QR ở máy chủ: vẫn dùng ảnh của SePay để nội dung QR khớp với webhook như hiện nay.
- Không dò scheme riêng của từng ngân hàng: không có tài liệu công khai, dễ hỏng khi app cập nhật.
- Máy tính giữ nguyên: QR to, không có lưới app.

## Kiểm thử

- Sửa test `bank-apps` trong `PaymentClaimsTest`: không còn `autofill`, link vẫn đủ tham số.
- Test mới cho `qr.png` (giả `qr.sepay.vn` bằng `Http::fake`): trả PNG đúng nội dung theo người xem; khách xem nhóm private bị 403/404; chiến dịch đã đóng 404; SePay lỗi trả 502; lần gọi thứ hai lấy từ cache.
- Thử trên iPhone và Android thật sau deploy: bấm MB có chép nội dung, Lưu ảnh QR lưu được vào thư viện, MB quét ảnh đó điền đủ số tiền và nội dung.

## Đã chốt

- Giữ lưới app (vẫn mở nhanh được app), nút **Lưu ảnh QR** ngay dưới nút mở app.
- Route `qr.png` từng bị bỏ ở plan/05 (10/11/2026) khi tưởng deeplink tự điền được; nay làm lại.

## Ghi chú khi làm

- Ảnh QR tải sẵn ngay khi mở trang trên điện thoại: Safari chỉ cho mở bảng Chia sẻ ngay sau lúc bấm, chờ tải ảnh xong mới chia sẻ thì bị chặn.
- Ảnh lưu trong cache dạng base64 theo URL ảnh SePay (mỗi nội dung một ảnh), giới hạn 30 lượt/phút mỗi IP.

## Deploy

- 11/11/2026, commit `f7064fd`: lên wepo.hipotech.vn sau khoảng 30 giây. `GET /api/g/nhomdemo/campaigns/thu-quy-2026/qr.png` trả ảnh PNG 200.
- Còn thử trên điện thoại thật: Lưu ảnh QR lưu được vào thư viện, MB Bank quét ảnh đó điền đủ số tiền và nội dung.
