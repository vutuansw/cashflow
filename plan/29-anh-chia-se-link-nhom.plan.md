# Ảnh xem trước khi chia sẻ link nhóm

Ngày: 12/11/2026. Trạng thái: **đã duyệt** (12/11/2026), đã code xong mốc 1 và 2, chưa commit, chưa deploy.

## Vấn đề

Dán link nhóm vào Zalo, Messenger, Facebook, Telegram, iMessage thì app chat đọc thẻ Open Graph (`og:image`) để hiện ảnh xem trước. `SpaController` đã in `og:image` bằng ảnh bìa nhóm (plan/03 mục 4), nhưng thực tế hay không ra ảnh hoặc ra ảnh xấu:

1. **Nhóm chưa có ảnh bìa thì không có ảnh nào.** Kiểm tra ngày 12/11/2026: `https://wepo.hipotech.vn/nhomdemo` không có thẻ `og:image` vì nhóm demo chưa đặt ảnh bìa.
2. **Ảnh bìa là WebP.** Zalo và một số app chat không đọc được WebP, nên bỏ qua ảnh. Ảnh share nên là JPEG.
3. **Sai tỉ lệ.** Ảnh bìa 1600×533 (3:1). Ô xem trước của Facebook, Zalo, Messenger là khoảng 1.91:1 (1200×630), mỗi app tự cắt một kiểu, hay mất chữ ở hai bên.
4. **Thiếu thẻ phụ** `og:image:width`, `og:image:height`, `og:image:type`, `og:image:alt`. Thiếu kích thước thì Facebook/Messenger thường không hiện ảnh ở **lần share đầu tiên**, vì chưa tải ảnh kịp.
5. **Link thành viên** (`/{ma-nhom}/members/{ma}`) đang hiện tên và ảnh của nhóm, không phải của người đó.

## Đã chốt (12/11/2026)

| # | Câu hỏi | Chọn |
|---|---|---|
| 1 | Ảnh bìa 3:1 đưa vào khung 1.91:1 thế nào | **Giữ trọn ảnh bìa**: đặt nguyên ảnh vào giữa khung 1200×630, phần trống trên dưới lấp bằng chính ảnh đó phóng to làm mờ |
| 2 | Nhóm chưa có ảnh bìa | Dùng **ảnh chung của WePo** (`public/landing/og.png`, đúng 1200×630) |
| 3 | Nhóm riêng tư | **Vẫn hiện ảnh bìa** như nhóm công khai (ảnh bìa vốn ai có link cũng xem được, tên nhóm cũng đã nằm trong tiêu đề) |
| 4 | Link con trong nhóm | **Link thành viên hiện ảnh của thành viên**. Mọi link khác trong nhóm (timeline, chiến dịch, thống kê, danh sách thành viên, đăng ký vào nhóm…) hiện **ảnh nhóm** |

## Đề xuất

### 1. Ảnh share riêng, JPEG 1200×630

Không dùng thẳng file ảnh bìa. Tạo thêm một ảnh share:

- Khung **1200×630**, JPEG chất lượng 85 (khoảng 80–150 KB, dưới mức 300 KB mà Zalo/WhatsApp khuyên).
- Nền: ảnh gốc phóng cho phủ kín khung, làm mờ mạnh và tối nhẹ để không giành mắt với ảnh chính.
- Giữa: ảnh gốc thu cho vừa khung, **không cắt** (ảnh bìa 3:1 sẽ rộng 1200, cao 400, nền mờ hiện ở trên và dưới mỗi bên 115px).

Dùng `intervention/image` (driver GD, đã có, máy đã hỗ trợ JPEG/WebP).

**Tạo lúc nào:** lần đầu có người (hoặc app chat) xin ảnh share thì tạo rồi lưu cạnh ảnh gốc, các lần sau trả file đã lưu. Ưu điểm: ảnh bìa đã có từ trước tự có ảnh share, không phải chạy lệnh chuyển đổi. Tên file share bám tên file gốc (`cover-abc123.webp` → `cover-abc123_og.jpg`), mà tên gốc ngẫu nhiên và đổi mỗi lần đổi ảnh, nên đổi ảnh bìa là đổi link ảnh share, app chat không giữ ảnh cũ.

**Xóa:** khi đổi hoặc gỡ ảnh bìa nhóm, ảnh đại diện, ảnh bìa cá nhân thì xóa luôn file `_og.jpg` đi kèm.

### 2. Đường dẫn ảnh share

| Ảnh | Đường dẫn |
|---|---|
| Ảnh share của nhóm | `/media/{ma-nhom}/brand/og/{ten-file}.jpg` |
| Ảnh share của thành viên | `/media/u/{user}/og/{avatar\|cover}/{ten-file}.jpg` |

Công khai và cache lâu như ảnh bìa hiện tại (tên file ngẫu nhiên, không ghi đè). `Content-Type: image/jpeg`. Không cần đăng nhập, vì app chat tải ảnh như khách.

### 3. Trang nào ra ảnh gì

| Link | Tiêu đề | Ảnh |
|---|---|---|
| `/` | như hiện tại | `og.png` của WePo |
| `/{ma-nhom}` và mọi link con của nhóm, trừ trang một thành viên | `Tên nhóm - WePo` (như hiện tại) | Ảnh share của nhóm. Chưa có ảnh bìa thì `og.png` |
| `/{ma-nhom}/members/{ma}`, nhóm **công khai**, thành viên đang hoạt động | `Tên thành viên · Tên nhóm - WePo`. Mô tả: lời giới thiệu (bio) nếu có, không thì "Thành viên nhóm …" | Ảnh của thành viên (xem dưới). Không có thì ảnh nhóm |
| `/{ma-nhom}/members/{ma}`, nhóm **riêng tư**, hoặc mã sai, hoặc người đã rời nhóm | như trang nhóm | Ảnh nhóm |

**Ảnh của thành viên**: ưu tiên **ảnh đại diện** (ảnh vuông, đặt giữa khung trên nền mờ của chính nó), không có thì **ảnh bìa cá nhân**, không có nữa thì ảnh nhóm.

Vì sao nhóm riêng tư không hiện tên và ảnh thành viên: khách không đăng nhập không xem được trang thành viên của nhóm riêng tư (`group.view`). Ai lấy được link cũng chỉ thấy nhóm, không biết ai ở trong nhóm. Ảnh bìa nhóm thì vẫn hiện như mục chốt số 3.

Các trang không thuộc nhóm (`/login`, `/groups/new`, `/system`…) giữ như hiện tại.

### 4. Thẻ meta in ra

Mọi trang có ảnh share in đủ:

```html
<meta property="og:image" content="https://wepo.hipotech.vn/media/.../og/....jpg">
<meta property="og:image:secure_url" content="(như trên)">
<meta property="og:image:type" content="image/jpeg">   <!-- og.png thì image/png -->
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Tên nhóm">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="(như trên)">
```

Link ảnh luôn là `https://...` tuyệt đối (lấy theo `APP_URL` trên server). Trên server cần đảm bảo `APP_URL=https://wepo.hipotech.vn`; sẽ kiểm tra ở mốc 3.

### 5. App chat giữ ảnh cũ

Zalo, Facebook, Messenger nhớ bản xem trước của một link trong một thời gian. Link nhóm đã từng share **trước** bản sửa này có thể vẫn ra không ảnh cho tới khi app chat tự đọc lại.

- Facebook/Messenger: dán link vào [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) và bấm "Scrape Again" là cập nhật ngay.
- Zalo: chưa có công cụ làm mới công khai; thường tự cập nhật sau vài giờ đến 1 ngày.
- Link share lần đầu sau khi deploy thì ra ảnh ngay.

## Cách khác đã cân nhắc, không chọn

| Cách | Vì sao không |
|---|---|
| Dùng thẳng file ảnh bìa WebP, chỉ thêm thẻ width/height | Zalo vẫn không đọc WebP; tỉ lệ 3:1 vẫn bị cắt |
| Cắt giữa ảnh bìa cho đầy khung | Mất khoảng 1/3 chiều ngang, chữ ở mép ảnh bìa bị cắt (đã hỏi, không chọn) |
| Tự vẽ ảnh có tên nhóm khi chưa có bìa | Phải cài font tiếng Việt lên server để vẽ chữ; để sau nếu cần (đã hỏi, chọn ảnh chung WePo) |
| Tạo ảnh share ngay lúc tải ảnh lên | Ảnh bìa có từ trước không có ảnh share, phải chạy lệnh chuyển đổi riêng; tạo lúc cần thì tự phủ cả ảnh cũ |
| Ảnh chiến dịch cho link chiến dịch | Đã hỏi: mọi link trong nhóm trừ thành viên dùng ảnh nhóm |

## Cách làm

- `app/Services/Media/ShareImage.php` (mới): nhận đường dẫn ảnh gốc, trả đường dẫn `_og.jpg`; chưa có thì vẽ (nền mờ + ảnh giữa) rồi lưu. Hàm `ogPathFor($path)` dùng chung để xóa.
- `app/Services/Media/ImageStore.php`: `delete()` xóa luôn `_og.jpg` đi kèm mỗi ảnh.
- `routes/web.php` + `MediaController`: thêm `groupShare` và `profileShare` (mục 2). Chỉ trả ảnh của đúng file đang dùng, sai tên thì 404 như `groupCover`.
- `app/Support/MediaUrl.php`: thêm `groupShare()`, `profileShare()`.
- `app/Http/Controllers/SpaController.php`: chọn ảnh/tiêu đề theo bảng mục 3; ảnh mặc định `og.png`; thêm `image_type`, `image_alt` vào `$meta`.
- `resources/views/app.blade.php`: in các thẻ ở mục 4.
- Test (`SpaRoutesTest`, test media):
  - nhóm có bìa → `og:image` trỏ `/brand/og/…jpg`, có width/height/type; nhóm chưa bìa → `og.png`; link con `/stats`, `/campaigns/…` → ảnh nhóm;
  - nhóm riêng tư vẫn có ảnh bìa; link thành viên của nhóm riêng tư → ảnh và tiêu đề nhóm, không lộ tên người;
  - link thành viên nhóm công khai → tên người, ảnh đại diện; không có đại diện → bìa cá nhân → ảnh nhóm; mã sai/người đã rời → như trang nhóm;
  - route ảnh share trả `image/jpeg` đúng 1200×630, sai tên file → 404; đổi ảnh bìa thì file `_og.jpg` cũ bị xóa.

## Mốc

1. **Ảnh share và route ảnh.** `ShareImage`, hai route, xóa kèm. Kiểm tra: `php artisan test` xanh; trên server local mở `/media/{ma-nhom}/brand/og/…jpg` của một nhóm có bìa, xem ảnh 1200×630 giữ trọn ảnh bìa trên nền mờ; thử thêm với một ảnh đại diện vuông.
   - **Xong** 12/11/2026: `app/Services/Media/ShareImage.php` (`pathFor()`, `ensure()`), `ImageStore::delete()` xóa kèm `_og.jpg`, route `groupShare` và `profileShare` trong `MediaController`, `MediaUrl::groupShare()` và `profileShare()`. Thêm `tests/Feature/ShareImageTest.php` (4 test: JPEG 1200×630, ảnh bìa không bị cắt vì mép trái vẫn còn, nhóm private khách vẫn xem được, sai tên hoặc chưa có ảnh thì 404, đổi/gỡ ảnh bìa và gỡ ảnh đại diện thì xóa ảnh share cũ). Toàn bộ 216 test xanh, Pint sạch.
   - Thử trên server local (cổng 8123, vì cổng 8000 đang chạy app farm): ảnh bìa nhóm thật 1600×533 ra ảnh share 1200×630, 132 KB; ảnh đại diện 400×400 ra 1200×630, 87 KB. Lần đầu tạo mất khoảng 0,25 giây, các lần sau khoảng 0,01 giây vì trả file đã lưu.
   - Đổi cách làm nền so với dự tính: thu nhỏ một lần rồi phóng thẳng lên 1200×630 thì nền lộ ô vuông. Sửa thành thu còn 80×42 rồi phóng lên qua hai bước (300×158 rồi 1200×630), mỗi bước làm mờ một ít. Nền mịn, thời gian vẫn khoảng 0,25 giây.
2. **Thẻ meta.** `SpaController` + `app.blade.php` theo mục 3, 4. Kiểm tra: test xanh; `curl -A facebookexternalhit` các link nhóm, link con, link thành viên (nhóm công khai và riêng tư) ra đúng thẻ.
   - **Xong** 12/11/2026: `SpaController` tách `groupMeta()`, `memberMeta()`, `brandImage()` theo bảng mục 3; `app.blade.php` in đủ thẻ mục 4 (thêm cả `twitter:title`, `twitter:description`). Thêm `tests/Feature/ShareMetaTest.php` (6 test: nhóm và 6 kiểu link con ra ảnh bìa nhóm; nhóm private vẫn ra ảnh bìa; chưa có bìa thì `og.png`; trang ngoài nhóm không có ảnh; link thành viên lần lượt ra ảnh đại diện, ảnh bìa cá nhân rồi ảnh nhóm; nhóm private, mã sai, mã của nhóm khác, người đã rời nhóm thì chỉ ra nhóm và không lộ tên). Toàn bộ 222 test xanh, Pint sạch.
   - `curl -A facebookexternalhit` trên server local: `/chi-doan-a12-2005-2008` và `/…/stats` ra ảnh bìa nhóm JPEG; `/…/members/{ma}` ra tên "Nguyễn Minh Hiển · Chi Đoàn A12 (2005-2008) - WePo" và ảnh đại diện, link ảnh tải về 200 `image/jpeg`; `/nhomdemo` (chưa có bìa) ra `og.png`. Mọi trang có đủ width 1200, height 630, type.
   - Link ảnh lấy theo địa chỉ trang đang mở (`url()`), giống `og:url`. Ngày 12/11/2026 `og:url` trên server đã là `https://wepo.hipotech.vn/…` nên link ảnh cũng sẽ là `https://`.
3. **Deploy và thử thật** (bạn làm phần dán link): kiểm `APP_URL` trên server là `https://`; đặt ảnh bìa cho `nhomdemo`; dán link nhóm và link thành viên vào Zalo, Messenger, Facebook, Telegram, iMessage xem ảnh xem trước; dùng Facebook Sharing Debugger kiểm thẻ và làm mới link cũ.
   - 12/11/2026: Zalo tải ảnh share lúc 14:28, ra ảnh đúng. Facebook thì bản xem trước chỉ có tên miền, Debugger báo "Mã phản hồi 403 … robots.txt", dù log nginx ghi mọi lượt Facebook vào trang đều 200. Bấm "Thu thập lại" trên link gốc thì Facebook không gọi server mà trả luôn 403 đã lưu. Debugger với `/login` (cũng noindex) ra 200, nên không phải do noindex hay robots.txt. Debugger với `/chi-doan-a12-2005-2008?v=4`: Facebook đọc link đó, theo `og:url` đọc lại link gốc, tải ảnh share, từ đó link gốc ra 200 với đủ tên nhóm và ảnh bìa. Không sửa code.
   - Nếu link nhóm khác trên Facebook/Messenger ra bản xem trước trống: mở Debugger với link đó, bấm "Thu thập lại"; vẫn 403 thì gỡ lỗi link có thêm `?v=1` để Facebook đọc lại link gốc qua `og:url`.
