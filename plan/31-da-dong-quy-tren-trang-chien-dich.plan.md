# Trang đóng quỹ báo "Bạn đã đóng"

Ngày: 12/11/2026. Trạng thái: **đã duyệt** (12/11/2026), đã code xong, đã kiểm tra trên trình duyệt; chưa commit, chưa deploy.

## Vấn đề

Thành viên đã được ghi nhận đóng đủ (ví dụ đã có trong tab "Đã đóng") mà mở lại trang `/{ma-nhom}/campaigns/{slug}` vẫn thấy QR, nút **"Tôi đã chuyển khoản, gửi bill"**, giống hệt người chưa đóng. Trang không cho họ biết là mình đã đóng rồi.

Nút gửi bill **vẫn gửi được** khi đã đóng đủ: server nhận, bill vào hàng chờ của thủ quỹ. Nếu thủ quỹ lỡ duyệt thì thành phiếu thu trùng. Theo quyết định bên dưới, server vẫn không chặn; chỉ sửa giao diện để người dùng không bấm nhầm.

API trang đóng quỹ đã trả sẵn dòng của người đang xem (`me`: `status` = `paid` / `partial` / `unpaid` / `exempt`, `paid`, `expected`, `pending_claim`), nên phần lớn là việc giao diện.

## Quyết định đã chốt (12/11/2026)

- Đã đóng đủ: hiện thông báo, **thu gọn** QR và nút gửi bill vào một link nhỏ **"Chuyển khoản hộ người khác"**.
- **Không chặn** ở server: bill trùng (nếu có) thủ quỹ tự bỏ qua khi duyệt.

## Đề xuất

### 1. Khối trạng thái của mình (ngay dưới thanh tiến độ)

Chỉ hiện khi biết người xem là ai (`me` khác null, tức thành viên đã gắn danh bạ). Khách và người chưa gắn danh bạ: giữ nguyên như hiện nay.

| Trạng thái của mình | Khối hiện | QR + nút gửi bill |
|---|---|---|
| `paid`, đóng đủ | Thẻ viền xanh, dấu tích: **"Bạn đã đóng {số tiền} ngày {dd/mm}. Cảm ơn bạn!"** (chiến dịch tùy tâm: "Bạn đã đóng {số tiền}…") | **Thu gọn** (mục 2) |
| `pending_claim`, có bill chờ duyệt | Thẻ vàng: **"Bill của bạn đang chờ thủ quỹ xác nhận."** | Giữ QR; nút đổi chữ thành **"Gửi lại bill"** (gửi lại thay cho bill đang chờ, đúng như server đã làm) |
| `partial`, đóng thiếu | Thẻ vàng: **"Bạn đã đóng {đã đóng} / {phải đóng}, còn {còn thiếu}."** | Giữ nguyên |
| `exempt`, được miễn | Thẻ xám: **"Bạn được miễn đóng chiến dịch này."** | Thu gọn như `paid` |
| `unpaid` | Không có khối | Giữ nguyên |

Khi `paid` mà vẫn có bill chờ duyệt (gửi thêm), hiện cả hai thẻ: đã đóng và bill đang chờ.

### 2. Thu gọn QR khi đã đóng

- Thay QR và nút gửi bill bằng một link chữ nhỏ: **"Chuyển khoản hộ người khác"**. Bấm thì mở lại phần QR và nút gửi bill ngay tại chỗ.
- Ở chế độ đóng hộ:
  - QR **không điền sẵn tên mình** vào nội dung chuyển khoản. Nếu vẫn điền tên mình thì SePay và thủ quỹ sẽ tưởng là mình đóng thêm. API trả thêm `bank.qr_url_others`: QR có số tiền, nội dung để trống.
  - Dòng Nội dung đổi thành: "Ghi **tên người được đóng hộ** + `CHIDOAN26`", nút Chép chỉ chép mã chiến dịch.
  - Nút gửi bill mở form **không chọn sẵn tên mình** (hiện nay form tự chọn tên người đang đăng nhập, plan/20), để chọn tên người được đóng hộ.

### 3. Không đổi

- Danh sách Đã đóng / Chưa đóng, tiến độ, nút chia sẻ.
- Server nhận bill như cũ (không chặn).
- Trang của khách (chưa đăng nhập) và người chưa gắn danh bạ.

## Cách làm

- `CampaignPageController::show`: thêm `bank.qr_url_others` (= `qrUrl(..., null)`) khi người xem đã đóng đủ hoặc được miễn. Thêm `last_paid_at` vào `me`, để hiện ngày đóng.
- `CampaignPage.vue`: khối trạng thái, thu gọn QR, chế độ đóng hộ.
- `ClaimSheet.vue`: prop `forOthers`, bật thì không chọn sẵn tên mình.
- Test (`tests/Feature/Campaigns/...`): `me.last_paid_at`; `qr_url_others` chỉ có khi đã đóng đủ hoặc được miễn, và QR đó không chứa tên người xem.

Một mốc, xong thì kiểm tra trên trình duyệt (cỡ điện thoại) rồi báo lại.
