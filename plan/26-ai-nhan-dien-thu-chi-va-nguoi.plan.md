# AI đọc bill: nhận đúng thu/chi và đúng người đóng

Ngày: 12/11/2026. Trạng thái: **đã làm** (12/11/2026). Sửa phần suy luận của [plan/04](04-ai-doc-bill.plan.md) mục 4.

## Vấn đề

Phản hồi của chủ dự án khi QTV tải bill lên để AI điền phiếu (`BillScanController` → `BillDraftResolver`):

1. **Sai loại thu chi.** Bill thành viên chuyển vào quỹ (màn hình "Chuyển tiền thành công", trên app của họ là -200.000) lại bị điền thành **phiếu chi**.
2. **Chọn nhầm tên liên tục**, gần như bill nào cũng ra "Nguyễn Minh Hiển".

## Nguyên nhân (đã kiểm trên bill thật)

Chạy lại AI trên **18 ảnh bill thật** của nhóm Chi Đoàn A12 (`storage/app/private/groups/1/bills`), so với phiếu QTV đã duyệt:

| | Loại đúng | Người tự chọn đúng | Phải hỏi lại | Chọn sai người |
| --- | --- | --- | --- | --- |
| **Hiện nay** | 6/18 | 0/18 | 14 | 4 |

AI đọc chữ trên ảnh **đúng**. Phần sai nằm ở bước suy luận sau đó:

- **Thu/chi:** bill là ảnh chụp app của *người đóng*, nên luôn là màn hình "đã chuyển đi" (`screen_type = outgoing`). Code đang hiểu `outgoing` = tiền ra khỏi quỹ = phiếu chi. Chỉ bill nào có mã chiến dịch `CHIDOAN26` trong nội dung mới được sửa thành thu; 12 bill ghi "VU DUC TUAN chuyen tien", "Tu Tran chuyen khoan nhanh qua Zalo"… đều thành phiếu chi.
- **Người:** trên các bill này, tên đối tác là **người nhận = chủ tài khoản quỹ** (NGUYEN MINH HIEN, STK 1032296050). Code coi tên đối tác là người đóng, nên Hiển luôn lọt vào danh sách: hoặc bị chọn luôn (khớp đủ họ tên thắng "KIM HOA" chỉ khớp 2 chữ), hoặc bắt QTV chọn giữa Hiển và người thật.
- Tên viết đảo kiểu "Tu Tran" (Trần Thị Tư) không khớp được.

## Sẽ làm

Chỉ sửa `BillDraftResolver` (PHP thuần, có test). Không đổi prompt, không đổi giao diện.

### 1. Nhận ra "tiền chuyển vào quỹ"

Bill được coi là **chuyển vào tài khoản của nhóm** khi:

- số tài khoản đối tác (bỏ ký tự không phải số) **trùng** `groups.bank_account_no`, hoặc
- tên đối tác (bỏ dấu, chữ thường) **trùng** `groups.bank_account_name`.

Khi đó:

- **Loại = Thu**, bất kể màn hình là chuyển đi hay nhận về.
- **Tên đối tác bị bỏ qua** khi tìm người đóng (đó là thủ quỹ, không phải người đóng). Chỉ tìm tên trong **nội dung chuyển khoản**.
- Không có mã chiến dịch trong nội dung mà nhóm có **đúng một chiến dịch đang mở** → điền sẵn chiến dịch đó (và loại thu theo chiến dịch), đánh dấu "AI điền" để QTV đổi được.

Nhóm chưa khai tài khoản ngân hàng thì giữ cách cũ.

### 2. Tìm người trong nội dung chặt hơn

Trước khi so tên, bỏ khỏi nội dung: mã chuyển khoản của các chiến dịch, và các cụm hay gặp "chuyen tien", "chuyen khoan", "nhanh qua zalo", "dong quy", "nop quy", năm (2026…). Rồi so theo bậc, dừng ở bậc đầu tiên có người khớp:

| Bậc | Khớp | Ví dụ | Một người khớp | Nhiều người khớp |
| --- | --- | --- | --- | --- |
| 1 | Đủ họ tên | "DINH THI HANG" | Chọn luôn | Cho QTV chọn |
| 2 | Đệm + tên (2 chữ cuối) | "KIM HOA" → Nguyễn Thị Kim Hoa | Chọn luôn | Cho QTV chọn |
| 3 | Họ + tên, đảo chỗ được | "Tu Tran" → Trần Thị Tư | Chọn luôn | Cho QTV chọn |
| 4 | Chỉ tên (chỉ dùng khi tiền vào quỹ) | "Hung Thuong" | **Luôn cho QTV chọn** | Cho QTV chọn |

Tên trong danh bạ có chữ cái lẻ ở cuối để phân biệt (vd "Nguyễn Văn Thường B") thì bỏ chữ lẻ đó khi so.

Bill không phải chuyển vào quỹ (QTV chụp bill chi ra cho người khác) thì giữ cách cũ: `outgoing` = chi, tên đối tác là người nhận.

## Kết quả mô phỏng trên 18 bill thật

Áp logic trên vào đúng kết quả AI vừa đọc (chưa sửa code app):

| | Loại đúng | Người tự chọn đúng | Phải hỏi lại | Chọn sai người |
| --- | --- | --- | --- | --- |
| **Hiện nay** | 6/18 | 0/18 | 14 | 4 |
| **Sau khi sửa** | **18/18** | **17/18** | 1 | **0** |

Bill còn phải hỏi: "Hung Thuong chuyen khoan nhanh qua Zalo" (phiếu thật gán cho Nguyễn Văn Thường B). Chỉ có tên "Thường", nhóm có nhiều người tên Thường/Thương, nên để QTV chọn là đúng.

## Không làm

- Không đổi prompt AI: chữ AI đọc ra đã đúng cả 18 bill.
- Không đổi luồng thành viên tự gửi bill (`PaymentClaim`): luồng đó đã luôn là phiếu thu và lấy người do thành viên chọn.
- Không đoán người khi chỉ khớp được tên gọi.

## Kiểm thử

- Unit/feature test `BillDraftResolverTest`, dữ liệu lấy từ JSON AI đọc của bill thật (đã ẩn số tài khoản):
  - Bill chuyển vào STK quỹ, nội dung không có mã chiến dịch → Thu, chiến dịch đang mở, đúng người.
  - Tên đối tác = tên chủ TK quỹ → không bao giờ chọn chủ TK quỹ làm người đóng.
  - "KIM HOA CHIDOAN26" → Nguyễn Thị Kim Hoa; "Tu Tran …" → Trần Thị Tư.
  - Chỉ khớp tên gọi → `contact_candidates`, không chọn sẵn.
  - Nhóm chưa khai tài khoản: bill chuyển đi vẫn là Chi như cũ.
- Chạy lại script đọc 18 bill thật bằng OpenRouter (gọi AI thật, khoảng 18 lượt flash-lite) và ghi bảng kết quả vào cuối plan này.

## Kết quả sau khi làm (12/11/2026)

- Code: `app/Services/Ai/BillDraftResolver.php` (`isIntoFund`, `onlyOpenCampaign`, `noteForNames`, `contacts` so theo 4 bậc).
- Bậc 4 (chỉ tên gọi) chỉ dùng khi tiền vào quỹ. Với bill chi ra, người nhận thường là người ngoài danh bạ, nên vẫn điền tên tự do như trước.
- Test: thêm 5 test vào `tests/Feature/Ai/BillDraftResolverTest.php`. Toàn bộ 206 test qua, Pint sạch.
- Gọi AI thật (`google/gemini-3.1-flash-lite`) đọc lại 18 bill của nhóm Chi Đoàn A12, rồi cho qua code mới:

| | Loại đúng | Người tự chọn đúng | Phải hỏi lại | Chọn sai người |
| --- | --- | --- | --- | --- |
| Trước | 6/18 | 0/18 | 14 | 4 |
| **Sau** | **18/18** | **17/18** | 1 | **0** |

Bill còn phải hỏi là "Hung Thuong chuyen khoan nhanh qua Zalo": QTV chọn trong Vũ Văn Thường, Nguyễn Văn Thường B (2 dòng trùng tên trong danh bạ) và Đặng Thị Thương Thương. Chủ TK quỹ Nguyễn Minh Hiển không còn lọt vào danh sách.
