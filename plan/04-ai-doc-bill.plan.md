# 04 — AI đọc ảnh bill chuyển khoản

Dùng lại cách làm đã chạy ổn ở `www/farm` (`app/Services/Ai/Vision/CashflowVisionExtractor.php`, `config/ai.php`): gọi **OpenRouter** qua Guzzle, model đọc ảnh rẻ nhất, một lần đọc lại bằng model mạnh hơn khi kết quả không dùng được.

## 1. Luồng người dùng

1. Trong trang quản trị bấm `+` → **Đọc bill chuyển khoản** → chọn ảnh từ thư viện điện thoại (`<input type="file" accept="image/*">`, không ép mở camera).
2. Trình duyệt thu nhỏ ảnh về cạnh dài 1600px rồi tải lên. Màn hình hiện ảnh và "Đang đọc bill..." (thường 3–6 giây).
3. Form phiếu được điền sẵn, các ô do AI điền có viền nhấn và nhãn "AI đọc", ô AI không chắc để trống.
4. Người dùng sửa nếu cần, thêm ảnh sự kiện, bấm **Lưu**. Chỉ lúc này mới có phiếu. Ảnh bill được gắn vào phiếu làm chứng từ (chỉ QTV xem).
5. Đọc lỗi/không phải bill → báo "Không đọc được bill, bạn nhập tay nhé", giữ ảnh làm chứng từ.

## 2. Kiến trúc code

```
app/Services/Ai/
  AiProvider.php            URL, header OpenRouter (HTTP-Referer, X-Title = Quỹ nhóm)
  BillVisionExtractor.php   Gửi ảnh, nhận JSON thô — chỉ đọc chữ, không biết gì về nhóm
  BillDraftResolver.php     PHP thuần: từ JSON thô + dữ liệu nhóm → bản nháp form
  BillScanService.php       Lưu ảnh, kiểm tra hạn mức, gọi 2 lớp trên, ghi bill_scans, tính chi phí
  FakeBillExtractor.php     Dùng trong test và khi AI_DRIVER=fake
```

Tách **đọc** (model) khỏi **suy luận** (PHP) như FarmGo: chỗ nào sai thì viết test được cho chỗ đó.

## 3. Prompt và dữ liệu AI trả về

Dựa trên prompt của `CashflowVisionExtractor` bên FarmGo, chỉnh cho quỹ nhóm:

```json
{
  "is_bank_receipt": true,
  "amount": 500000,
  "transaction_datetime": "2026-11-08 14:02",
  "screen_type": "outgoing | incoming | null",
  "counterparty_name": "NGUYEN THI LAN",
  "counterparty_account": "0123456789",
  "note": "QNA12N26 Tran Van Minh dong quy",
  "bank_name": "MB Bank",
  "reference_code": "FT26312..."
}
```

Quy tắc giữ nguyên từ FarmGo: chỉ đọc cái nhìn thấy, không đoán; `temperature = 0`; bóc JSON chịu được trường hợp model bọc trong khối mã.

## 4. Suy luận bản nháp (`BillDraftResolver`)

| Trường form | Cách suy ra |
| --- | --- |
| Thu/Chi | `outgoing` → **Chi** (QTV chuyển tiền đi); `incoming` → **Thu**; nội dung chứa `transfer_code` của chiến dịch → **Thu**. Không chắc → để người dùng chọn |
| Số tiền | `amount` |
| Ngày giờ | `transaction_datetime`; không có thì bây giờ |
| Chiến dịch | Nội dung chứa `transfer_code` (so khớp bỏ dấu cách, gạch, chữ hoa thường) |
| Loại | Theo chiến dịch; nếu không thì từ khóa trong nội dung, bỏ dấu: `hieu, vieng, tang, phung` → Đám hiếu · `cuoi, hi, hy` → Đám hỉ · `om, benh, vien` → Thăm ốm · `de, sinh, em be` → Thăm đẻ · `lien hoan, an, nhau` → Liên hoan · `quy, dong quy` → Đóng quỹ năm. Từ khóa nằm trong cấu hình, so cả với tên các loại nhóm tự thêm |
| Người đóng / nhận | Thu: tên người chuyển hoặc tên ghi trong nội dung; Chi: tên người nhận. So với `contacts.search_name` (bỏ dấu, khớp đủ họ tên trước, rồi khớp tên + đệm). Một ứng viên → chọn sẵn; nhiều → gợi ý danh sách; không có → điền `payer_name` |
| Tiêu đề | "{Loại} {tên}" |
| Mô tả | Nội dung CK nguyên văn |

Đọc lại bằng `fallback_model` đúng **một lần** khi: `amount` null hoặc ≤ 0, hoặc `is_bank_receipt` true mà thiếu cả ngày lẫn nội dung.

## 5. Cấu hình

`config/ai.php` (một nguồn sự thật cho model, như FarmGo):

```env
OPENROUTER_API_KEY=
AI_DRIVER=openrouter            # fake khi dev/test
AI_VISION_MODEL=                # trống → google/gemini-3.1-flash-lite
AI_VISION_FALLBACK_MODEL=       # trống → google/gemini-3.7-flash
AI_USD_VND=26000
```

`max_dimension` 1600px, timeout 60 giây, chỉ nhận ảnh (bill quỹ nhóm luôn là ảnh chụp màn hình, không cần nhánh PDF của FarmGo).

## 6. Hạn mức và chi phí

- Mặc định **không giới hạn**. `/system` đặt `ai_monthly_quota` cho từng nhóm; hết hạn mức thì nút đọc bill báo "Đã dùng hết lượt đọc bill tháng này", vẫn nhập tay được.
- Mỗi lượt ghi `bill_scans`: model, token, `cost_usd` (lấy từ trường `usage.cost` OpenRouter trả về). `/system` có báo cáo chi phí theo nhóm/tháng.
- Giới hạn tốc độ 10 lượt/phút/người để chống bấm liên tục.
- Đọc bill chạy **đồng bộ** trong request (người dùng đang chờ form), timeout 60 giây; không cần hàng đợi.

## 7. Kiểm thử

- Test đơn vị `BillDraftResolver` với bộ JSON mẫu (CK đi thăm ốm, CK vào có mã chiến dịch, tên không dấu, tên trùng hai người, ảnh không phải bill).
- Thư mục `tests/fixtures/bills/` chứa vài ảnh bill thật đã che số tài khoản (chủ dự án cung cấp: MB, Vietcombank, Techcombank, Momo) để chạy thử tay với model thật bằng lệnh `php artisan cashflow:ai-eval`, in độ chính xác và chi phí từng ảnh.
