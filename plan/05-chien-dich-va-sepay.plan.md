# 05 — Chiến dịch thu, mã QR, tích hợp SePay

## 1. Chiến dịch thu

Ví dụ "Thu quỹ năm 2026", mỗi người 500.000đ.

**Tạo chiến dịch** (quản trị nhóm): Tên* (không trùng tên chiến dịch khác trong nhóm), Loại thu* (mặc định Đóng quỹ năm), Số tiền mỗi người (để trống = tùy tâm), Mã nội dung chuyển khoản (không bắt buộc), Mô tả, **Người được miễn**.

- Mọi người đang tham gia trong danh bạ đều phải đóng; QTV chỉ chọn ai **được miễn**. Người thêm vào danh bạ sau khi tạo chiến dịch tự vào danh sách phải đóng của các chiến dịch đang mở.
- Bỏ ô Từ ngày – Đến ngày và Mục tiêu tổng khỏi form (chốt ngày 10/11/2026). Tiến độ tính theo tổng số phải đóng của mọi người.
- Ô bắt buộc có dấu `*` đỏ ở nhãn.

**Mã nội dung chuyển khoản** (`transfer_code`): không bắt buộc nhập, để trống thì tự sinh; duy nhất toàn hệ thống, chỉ chữ in hoa và số (ngân hàng hay bỏ dấu cách, gạch, ký tự đặc biệt trong nội dung). QTV sửa được khi chưa có giao dịch nào.
- Cách tự sinh (chốt 10/11/2026): ghép **trọn từng cụm** của mã nhóm (tách theo dấu `-`) cho tới khi đủ tối đa 8 ký tự, không cắt giữa chữ, rồi thêm 2 số cuối của năm. Vd `chi-doan-a12` năm 2026 → `CHIDOAN26`, `nhomdemo` → `NHOMDEMO26`. Cụm đầu dài hơn 8 ký tự thì lấy 8 ký tự đầu. Trùng mã đã có (vd chiến dịch thứ hai cùng năm) thì thêm một chữ cái: `CHIDOAN26B`, `CHIDOAN26C`…

**Trang chiến dịch** `/{ma-nhom}/campaigns/{slug}` — ai xem được timeline thì xem được:

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
│ Nội dung: VU DUC TUAN QNA12N26│
│                       [Chép]  │
│ (khách: không có dòng Nội dung)│
│ [Tôi đã chuyển khoản, gửi bill]│
├──────────────────────────────┤
│ Đã đóng (30) │ Chưa đóng (12) │
│ ✓ Trần Văn Minh   08/11       │
│ ✓ Lê Thị Hoa      07/11       │
└──────────────────────────────┘
```

- Ảnh QR lấy từ dịch vụ QR của SePay (VietQR), không cần khóa:
  `https://qr.sepay.vn/img?acc={STK}&bank={bank_code}&amount={số tiền}&des={nội dung}`
  Số tiền điền sẵn khi chiến dịch có số tiền mỗi người.
- **Nội dung chuyển khoản theo người xem** (chốt 10/11/2026 — cần tên người đóng hơn mã chiến dịch):
  - **Không biết người xem** (chưa đăng nhập, hoặc đăng nhập nhưng không phải thành viên nhóm): QR, deeplink và nút Chép **không có nội dung**. App ngân hàng tự điền mặc định "TÊN CHỦ TK chuyen tien", nhờ đó vẫn ra người đóng. Trang không hiện dòng "Nội dung" và nút Chép nội dung.
  - **Biết người xem** (thành viên nhóm đã đăng nhập): nội dung là `{TÊN} {mã chiến dịch}`, ví dụ `VU DUC TUAN DT2026`. Tên lấy theo thứ tự: họ tên của danh bạ gắn với thành viên (`group_members.contact_id`, chính là tên app dùng để so khớp) → tên tài khoản.
  - Tên bỏ dấu, chữ in hoa, chỉ giữ chữ, số và dấu cách. Một số ngân hàng cắt nội dung dài, nên giữ cả chuỗi **không quá 25 ký tự**: quá thì rút tên còn "đệm + tên" (hai chữ cuối), vẫn quá thì chỉ giữ tên; **mã chiến dịch luôn giữ nguyên** ở cuối. Ví dụ `PHAM THI THU TRANG QUYLOP2026` (29) → `THU TRANG QUYLOP2026`.
  - Trang chiến dịch giờ khác nhau theo người xem, nên phản hồi API của trang không được cache chung (`Cache-Control: private`).
- ~~Nút Tải ảnh QR~~: bỏ (chốt 10/11/2026), thay bằng nút **Tôi đã chuyển khoản, gửi bill** cho người đã chuyển trước khi nhận link, xem mục 5.
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
   - **Webhook URL**: `https://wepo.hipotech.vn/api/webhooks/sepay/{ma-nhom}` [Chép]
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
   - **Tiền vào không có mã chiến dịch** (khách quét QR không nội dung, mục 1): nếu nhóm có **đúng một** chiến dịch đang mở thì gợi ý chiến dịch đó, gắn nhãn "Đoán" trên thẻ hàng chờ để QTV để ý; có nhiều chiến dịch đang mở thì để trống cho QTV chọn. Vẫn chờ duyệt như mọi giao dịch, không tự tạo phiếu. *(Đề xuất, chờ chốt.)*
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

## 5. Thành viên tự gửi bill (chốt 10/11/2026)

Người đã chuyển khoản (trước hoặc sau khi nhận link) tự báo bằng ảnh bill, thay cho tính năng tải ảnh QR.

**Trên trang đóng quỹ** (chiến dịch đang mở; ai xem được trang là gửi được, không cần đăng nhập):
1. Nút **Tôi đã chuyển khoản, gửi bill** dưới khối QR; giữ nguyên QR và các nút Chép.
2. Bảng gửi: chọn **tên mình** trong danh sách chưa đóng/đóng thiếu (tìm không dấu), hoặc "Không thấy tên tôi" → gõ họ tên; **chọn ảnh bill** (bắt buộc, thu nhỏ trên máy trước khi tải); số tiền đã chuyển (điền sẵn số tiền mỗi người, sửa được); ghi chú (không bắt buộc).
3. Gửi xong báo "Đã gửi, thủ quỹ sẽ xác nhận". Trong danh sách, tên đó hiện nhãn **Chờ xác nhận**; **chưa tính là đã đóng** cho tới khi QTV duyệt (giống SePay).

**Máy chủ:**
- Bảng mới `payment_claims`: group_id, campaign_id, contact_id (null nếu gõ tên), payer_name, amount (người gửi khai), bill_scan_id (→ bill_scans: ảnh + kết quả AI), note, status (pending, approved, ignored), ignore_reason, transaction_id (phiếu tạo ra), reviewed_by, reviewed_at, ip, timestamps.
- Ảnh lưu như bill chứng từ (đĩa riêng, chỉ QTV xem). **AI đọc bill** (BillScanService) để QTV thấy sẵn số tiền, ngày giờ, nội dung CK, cảnh báo nếu ảnh không giống bill hoặc số tiền AI đọc khác số người gửi khai. Tính vào hạn mức AI của nhóm; hết hạn mức thì vẫn nhận bill, chỉ bỏ qua bước AI.
- Chống spam: chỉ nhận ảnh ≤ 10 MB; 5 lần gửi/giờ/IP mỗi nhóm; ô honeypot; một người (contact) chỉ có 1 bill đang chờ mỗi chiến dịch (gửi lại thì thay ảnh cũ).
- Email gộp cho QTV như giao dịch SePay ("có bill thành viên gửi chờ duyệt"), dùng chung cài đặt tắt/bật.

**Hàng chờ duyệt** (mục 4) thêm tab **Bill thành viên**, mỗi dòng: ảnh bill (bấm phóng to), người gửi, chiến dịch, số tiền khai và số AI đọc, ngày giờ trên bill, ghi chú.
- **Duyệt**: tạo phiếu thu gắn chiến dịch + người, số tiền và ngày giờ theo bill (AI đọc, không có thì số khai và giờ gửi), ảnh bill gắn vào phiếu làm chứng từ. Người đó chuyển sang Đã đóng.
- **Sửa**: mở form phiếu điền sẵn rồi lưu. **Bỏ qua** kèm lý do (ảnh sai, trùng…), khôi phục được.
- Chấm đỏ "Chờ duyệt" đếm cả bill thành viên.

**Chống trùng** (một lần chuyển chỉ ra một phiếu). Áp dụng cho mọi đường vào quỹ: bill thành viên gửi, bill QTV đọc bằng AI (plan/04), giao dịch SePay. Các trường hợp phải bắt: người đã quét QR (SePay đã báo, chờ hoặc đã duyệt) lại gửi bill; gửi lại đúng ảnh cũ sau khi bill trước đã duyệt; QTV đã nhập tay hoặc đọc bill khoản đó rồi thành viên mới gửi.
1. **Mã giao dịch ngân hàng** (dấu hiệu mạnh nhất): AI đọc mã tham chiếu trên bill (vd `FT26314829175`), SePay gửi `referenceCode`. Mã trên bill trùng mã của một giao dịch SePay, hoặc trùng mã của bill đã gắn vào phiếu trong nhóm → cùng một lần chuyển: **không cho tạo phiếu thứ hai**, nút Duyệt đổi thành **"Gắn bill vào phiếu đã có"** (ảnh thành chứng từ của phiếu đó, không cộng thêm tiền). Lưu mã đã chuẩn hóa (chữ hoa, bỏ ký tự lạ) để so.
2. **Ảnh trùng**: lưu SHA-256 của file ảnh gốc ở `bill_scans.file_hash`. Gửi lại đúng ảnh đã có trong nhóm → báo ngay "Bill này đã được gửi trước đó", không tạo bản ghi mới.
3. **Khớp mềm** khi không có mã: so với giao dịch SePay (đang chờ **và** đã duyệt) và phiếu đã có: cùng số tiền, nội dung có mã chiến dịch, giờ trên bill lệch không quá 30 phút, và không phải hai người khác nhau (biết cả hai người mà khác nhau thì không tính, vì cả nhóm hay chuyển cùng lúc cùng số tiền). Khớp thì dòng bill hiện **"Có thể trùng với…"** kèm liên kết, mặc định chọn **Ghép** (một phiếu, gắn cả giao dịch SePay lẫn ảnh bill); muốn tạo phiếu riêng phải bấm xác nhận thêm.
4. **Đã đóng đủ**: người đã đủ tiền trong chiến dịch mà gửi bill → trang đóng quỹ hỏi "Bạn đã được ghi nhận đóng đủ, vẫn gửi?"; trong hàng chờ có nhãn **Đã đóng đủ**.

Chiều ngược lại: QTV duyệt một giao dịch SePay mà có bill thành viên đang chờ khớp (lớp 1 hoặc 3) → tự gắn bill vào phiếu vừa tạo và đóng bill đó lại Khớp mềm ở chiều này phải cùng người (vì gắn tự động); bill không đọc được giờ thì tính bill gửi trong vòng 3 ngày sau giao dịch.

Ghi chú khi làm (10/11/2026):
- AI đọc bill thành viên chạy ngay sau khi trả lời người gửi (không bắt họ chờ). Ảnh thu nhỏ trên máy nên lớp 2 chỉ bắt được khi gửi lại đúng file từ cùng trình duyệt; lớp 1 và 3 mới là chính.
- Bill QTV tự đọc (plan/04) cũng qua lớp 1 và 2: trùng mã thì không lưu được phiếu, ảnh đã gắn vào phiếu hoặc đang chờ duyệt thì báo ngay.
- Xóa phiếu đã gắn bill thành viên thì bill vẫn theo phiếu (khôi phục phiếu là có lại), không quay về hàng chờ.
- Ảnh bill thành viên đang chờ hoặc đã bỏ qua không bị lệnh dọn bill mồ côi xóa.

**Bỏ:** route `/api/g/{group}/campaigns/{slug}/qr.png` và nút Tải ảnh QR. *(11/11/2026: làm lại thành nút **Lưu ảnh QR** trên điện thoại vì deeplink không còn tự điền, xem [plan/19](19-app-ngan-hang-khong-tu-dien.plan.md).)*

## 5b. Mở app ngân hàng trên điện thoại (chốt 10/11/2026)

Trên điện thoại không tự quét được mã QR đang hiện trên chính màn hình, nên nút chính là **mở thẳng app ngân hàng**, dùng deeplink VietQR:

`https://dl.vietqr.io/pay?app={appId}&ba={số TK}@{mã ngân hàng}&am={số tiền}&tn={nội dung}`

- Danh sách app lấy từ `https://api.vietqr.io/v2/android-app-deeplinks` và `.../ios-app-deeplinks` (tên, logo, `autofill`, mức phổ biến). Máy chủ lấy về, cache 1 ngày, trả cho trang theo hệ điều hành (đoán từ User-Agent). Ngày 10/11/2026: Android 35 app, iPhone 33 app.
- Chỉ 5 app **tự điền sẵn** số tài khoản, số tiền, nội dung (`autofill = 1`): MB Bank, VietinBank iPay, BIDV SmartBanking, ACB ONE, OCB OMNI. Các app khác (Vietcombank, Techcombank, VPBank, TPBank, Agribank…) chỉ mở được app.

Trang đóng quỹ trên điện thoại:
1. Nút chính **Mở app ngân hàng để chuyển** → lưới biểu tượng app xếp theo mức phổ biến; app tự điền có nhãn **Tự điền sẵn**.
2. Bấm app tự điền: mở app với đủ thông tin; nội dung theo người xem như mục 1 (`{TÊN} {mã}` hoặc để trống cho app ngân hàng tự điền tên).
3. Bấm app không tự điền: nếu có nội dung (biết người xem) thì trước khi mở app tự chép nội dung vào bộ nhớ và nhắc "Đã chép nội dung, dán vào ô nội dung chuyển khoản"; không có nội dung thì mở app luôn. Nút Chép số TK, số tiền vẫn ở trên trang.
4. Nhớ app đã chọn lần trước (lưu trên máy người dùng) và đưa lên đầu.
5. QR thu nhỏ, nằm dưới nút chính (cho trường hợp nhờ người khác quét hộ). Trên máy tính giữ QR to như hiện nay, không hiện lưới app.
6. VietQR lỗi hoặc không lấy được danh sách → ẩn lưới app, các nút Chép vẫn dùng được.

Cần kiểm trên điện thoại thật khi deploy (M6): cách ghi mã ngân hàng trong `ba`, từng app tự điền có điền đúng nội dung và số tiền không.

**Cập nhật 11/11/2026 ([plan/19](19-app-ngan-hang-khong-tu-dien.plan.md)):** VietQR không còn chuyển số tài khoản, số tiền, nội dung cho app nào (kể cả 5 app có `autofill = 1`), chỉ mở màn hình chính của app. Đã bỏ nhãn **Tự điền**; bấm app nào cũng chép sẵn nội dung (khách thì số tài khoản) rồi mới mở; thêm nút **Lưu ảnh QR** để quét ảnh trong app ngân hàng.

## 6. Kiểm thử

- Feature test webhook: sai API key, nhóm bị khóa, gửi trùng `id`, nhận ra mã chiến dịch có dấu cách/chữ thường (`qna12 n26`), gợi ý người đóng có dấu/không dấu, tiền ra.
- Thành viên gửi bill: khách gửi được ở nhóm public, nhóm private thì bị chặn; giới hạn lần gửi; chưa duyệt thì chưa tính đã đóng; duyệt ra phiếu có bill; nhóm khác không thấy bill của nhóm này.
- Chống trùng: trùng mã giao dịch với SePay (chờ hoặc đã duyệt) không tạo phiếu thứ hai; gửi lại cùng ảnh bị chặn; khớp mềm mặc định ghép; duyệt SePay tự đóng bill chờ khớp.
- Deeplink: tạo link đúng tham số theo app; danh sách app cache, VietQR lỗi thì trang vẫn chạy.
- Nội dung QR theo người xem: khách → QR, deeplink không có `des`/`tn`; thành viên gắn danh bạ → `{TÊN} {mã}` bỏ dấu, in hoa; tên dài rút gọn mà mã vẫn còn nguyên; nội dung này đưa vào webhook giả thì nhận ra đúng cả chiến dịch lẫn người đóng.
- Lệnh `php artisan cashflow:sepay-simulate {ma-nhom} {so-tien} "{noi-dung}"` gửi webhook giả vào máy local để thử luồng mà không cần SePay.
