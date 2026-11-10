# Landing page trang chủ

Ngày: 10/11/2026. Trạng thái: **đã duyệt, đã làm** (10/11/2026). Mockup đã duyệt: [wireframes/landing.html](wireframes/landing.html).

## Hiện tại

- `/` (`HomePage.vue`) chỉ có logo, một câu mô tả, 3 thẻ tính năng và các nút Đăng nhập / Tạo nhóm / Vào nhóm của tôi.
- Người mới vào `wepo.hipotech.vn` không thấy app trông như thế nào, cũng không thấy lợi ích của QR, SePay, AI đọc bill và báo cáo.

## Quyết định đã chốt

- `/` thành landing page gồm **hero + 5 section ưu việt + CTA cuối trang + footer**, bố cục giống mockup.
- Mỗi section có **ảnh chụp app thật đặt trong khung iPhone vẽ bằng CSS**, kèm họa tiết minh họa (thẻ nổi, đồng xu, bill, biểu đồ).
- Ảnh chụp lấy từ **nhóm demo `nhomdemo`**. Không dùng ảnh của nhóm thật vì có ảnh người thật.

## Nội dung từng section

| # | Section | Ảnh trong iPhone | Họa tiết |
| --- | --- | --- | --- |
| Hero | "Quỹ nhóm minh bạch, thủ quỹ nhẹ gánh." · 2 nút: **Tạo nhóm của bạn**, **Xem nhóm mẫu** · 3 điểm nhấn: QR "Quét mã chuyển tiền", A.I "Đọc bill chuyển khoản", FREE "Sử dụng miễn phí" | Dòng thời gian nhóm | Thẻ "vừa đóng +500.000 đ", thanh tiến độ 15/24, đồng xu "đ" |
| 01 | **Lưu trữ kỉ niệm** (tiêu đề "Mỗi khoản thu chi là một câu chuyện, cả nhóm cùng thấy."): mỗi khoản thu chi là một bài đăng có ảnh, tim, bình luận; nhóm công khai hoặc riêng tư | Bài chi có ảnh và bình luận | Ảnh polaroid, bong bóng tim/bình luận |
| 02 | **Nộp quỹ bằng mã QR** (chữ trên landing dùng "nộp quỹ", "đã nộp / chưa nộp", không dùng "đóng"; ảnh chụp app vẫn là chữ của app): QR có sẵn số tiền và nội dung theo từng người, mở thẳng app ngân hàng, danh sách Đã đóng / Chưa đóng, gửi bill | Trang chiến dịch có QR | Góc mã QR, chip logo ngân hàng, thẻ "Lan đã đóng" |
| 03 | **Tự động qua ngân hàng** (nền xanh đậm): ngân hàng báo biến động số dư (trên trang chỉ ghi "Ngân hàng", không nêu tên SePay), WePo khớp chiến dịch và người đóng, thủ quỹ bấm duyệt, email tóm tắt | Tổng quan quản trị (phần Số dư quỹ) | Thông báo ngân hàng → "SePay báo về WePo" → "Đã ghi vào Thu quỹ 2026" |
| 04 | **AI đọc bill chuyển khoản**: chụp bill, AI điền số tiền, ngày giờ, người chuyển, nội dung; gợi ý loại và người trong danh bạ; ô AI điền có nhãn để kiểm lại | Form tạo phiếu | Bill có tia quét chạy, ngôi sao AI, nhãn "AI" |
| 05 | **Báo cáo tự tổng hợp**: số dư, thu chi theo tháng, chi theo loại, tiến độ chiến dịch, xuất Excel | 2 máy nghiêng: Thống kê và Báo cáo | Thẻ file Excel, biểu đồ cột |
| CTA | "Lập quỹ cho nhóm của bạn ngay hôm nay" · Tạo nhóm / Đăng nhập | | Đồng xu, vòng tròn nét đứt |

### Sửa chữ so với mockup

Bỏ hoặc sửa những câu chưa đúng với app:

- "Đã điền 4 ô trong 3 giây" → **"AI đã điền sẵn phiếu"** (không cam kết số giây).
- "Đọc bill từ mọi app ngân hàng phổ biến" → **"Đọc ảnh chụp bill chuyển khoản của các app ngân hàng"**.
- Nội dung chuyển khoản minh họa theo đúng định dạng plan/05 mục 2: `{TÊN} {mã chiến dịch}`, ví dụ `NGUYEN THI LAN NHOMDEN26` (mã chiến dịch thật của nhóm demo), thay cho `WEPO TQ2026 LAN`.
- "Quỹ nhóm thế hệ mới" giữ nguyên. Ban đầu không ghi "miễn phí" vì chưa chốt giá; chủ dự án chốt thêm điểm nhấn **FREE · Sử dụng miễn phí** ở hero (10/11/2026).

## Việc cần làm

### 1. Ảnh chụp màn hình

- Chụp ở khổ 390×844, tỉ lệ 3x, lưu **`public/landing/*.webp`** (rộng 780px, ~60–100 KB mỗi ảnh): `timeline`, `timeline-post`, `campaign`, `admin`, `transaction-form`, `stats`, `reports`.
- Chụp bằng Playwright chạy ngoài repo (không thêm vào `package.json`) trên máy dev, nhóm `nhomdemo`. Phần thiếu của nhóm demo được **giả ngay lúc chụp bằng cách chặn request**, không sửa database hay storage:
  - Ảnh sự kiện: trên máy dev, ảnh của 2 nhóm demo (`nhomdemo`, `chidoan-a12`) đã được thay hẳn trong storage bằng **ảnh minh họa gradient + emoji theo nội dung từng bài** (10/11/2026), nên chụp thẳng, không cần giả.
  - Trang chiến dịch: trên máy dev, `nhomdemo` đã được khai báo tài khoản nhận quỹ giả (`Vietcombank 0000123456789`, `NHOM BAN THAN DEMO`) trong database, nên trang hiện mã VietQR thật của `qr.sepay.vn`. Chụp khi đã đăng nhập để có dòng nội dung chuyển khoản.
  - Form phiếu: mở `?scan=…` và trả một lượt đọc bill giả (500.000 đ, 09/11 20:15, Nguyễn Thị Lan) để màn hình có viền và nhãn "AI đọc". Không gọi OpenRouter.
- Khi giao diện đổi nhiều thì chụp lại theo cách trên.

### 2. Giao diện

- **`HomePage.vue`** viết lại theo mockup, chia component trong `resources/js/components/landing/`:
  - `PhoneFrame.vue`: khung iPhone (viền, Dynamic Island, thanh trạng thái 9:41, thanh home), nhận `src`, `alt`, `width`.
  - `LandingSection.vue`: số thứ tự, eyebrow, tiêu đề, đoạn mô tả, danh sách tick, slot cho phần ảnh; prop `reverse` để đảo trái/phải.
  - Thẻ nổi và họa tiết viết thẳng trong từng section, không tách thêm.
- **Nav dính trên cùng**: logo `BrandMark`, link nhảy tới từng section (ẩn trên mobile), nút Đăng nhập / Tạo nhóm.
- **Đã đăng nhập**: giữ luồng hiện tại, vẫn thấy landing. Nút trên nav, hero và CTA đổi thành **"Vào nhóm"** (`homePath()`; trên nav là nút viền `btn-outline`), ẩn Đăng nhập / Tạo nhóm như hiện nay.
- **Nút "Xem nhóm mẫu"**: mở nhóm công khai cấu hình ở `config('cashflow.landing.demo_group')` (env `LANDING_DEMO_GROUP`), đưa xuống SPA bằng thẻ `<meta name="wepo-demo-group">` trong `app.blade.php` (SPA hiện chưa có kênh nhận cấu hình nào khác, không đáng thêm API). Để trống thì ẩn nút. Server thật sẽ có nhóm demo công khai.
  - Không chạy `DemoSeeder` trên server thật: seeder tạo tài khoản mật khẩu `demo1234` và quản trị `/system` `admin@wepo.test`. Nhóm demo trên server thật tạo tay bằng tài khoản của chủ dự án, rồi đặt `LANDING_DEMO_GROUP={mã nhóm}`.
- **Màu và chữ**: dùng token Tailwind sẵn có (`primary`, `muted-foreground`, `card`…) nên tự theo chế độ tối của app. Section 03 và khối CTA giữ nền xanh đậm ở cả hai chế độ; ảnh trong iPhone là ảnh chế độ sáng. Thêm font tiêu đề **Be Vietnam Pro** (700, 800) qua `bunny()` trong `vite.config.js`, chỉ dùng cho tiêu đề landing.
- **Hiệu ứng**: thẻ nổi lên xuống nhẹ, tia quét trên bill. Tắt hết khi `prefers-reduced-motion`.
- **Mobile-first**: dưới 640px các section xếp dọc, chữ trước ảnh sau; iPhone 250px, 2 máy ở section 05 thu còn 185px; không cuộn ngang.
- Ảnh iPhone dùng `loading="lazy"` trừ ảnh hero; có `width`/`height` để không nhảy layout.
- Bỏ dải ghi chú vàng "Mockup landing…" của bản mockup.

### 3. SEO và PWA

- `SpaController`: tiêu đề `/` giữ nguyên ("Quản lý quỹ nhóm cùng nhau - WePo"). Mô tả đổi theo câu hero, thêm `og:image` là ảnh chia sẻ mới `public/landing/og.png` (1200×630: logo, câu hero, 1 iPhone).
- `public/sw.js`: không cache `/landing/` (ảnh tĩnh, trình duyệt tự cache), không cần tăng tên cache.

### 4. Kiểm tra

- Mở `/` ở 390px, 768px, 1440px: không cuộn ngang, thẻ nổi không bị Dynamic Island che.
- Chưa đăng nhập: nút Tạo nhóm, Đăng nhập, Xem nhóm mẫu đi đúng trang. Đã đăng nhập: chỉ có "Vào nhóm".
- `npm run build` không lỗi. Lighthouse mobile của `/`: Performance ≥ 85, Accessibility ≥ 95.

## Ngoài phạm vi

- Trang bảng giá, câu hỏi thường gặp, đánh giá của người dùng.
- Video demo, ảnh động GIF.
- Bản tiếng Anh.
- Ảnh chụp chế độ tối riêng cho iPhone.

## Đã chốt thêm (10/11/2026)

1. Người đã đăng nhập vào `/`: **giữ luồng hiện tại** (thấy landing, nút "Vào nhóm").
2. **Có tạo nhóm demo** trên server thật, nút "Xem nhóm mẫu" bật qua `LANDING_DEMO_GROUP`.
3. Ảnh sự kiện trong ảnh chụp: **ảnh minh họa** gradient + emoji như mockup.

## Kết quả khi làm (10/11/2026)

- File: `HomePage.vue`, `components/landing/PhoneFrame.vue`, `components/landing/LandingSection.vue`, khối `landing-*` cuối `app.css`, font trong `vite.config.js`, `config/cashflow.php` (`landing`), `SpaController`, `app.blade.php`, ảnh trong `public/landing/`.
- Ảnh iPhone 7 file webp 36–64 KB; `og.png` 1200×630 khoảng 100 KB.
- Đã đăng nhập: cả nav, hero và CTA đều chỉ còn "Vào nhóm" (nav dùng nút viền).
- Chế độ tối dùng token nên tự đổi; thẻ bill, ảnh polaroid, thông báo ngân hàng giữ màu sáng như ảnh thật.
- Kiểm tra: 390 / 768 / 1440px không cuộn ngang, không lỗi console. Lighthouse mobile (máy dev, `php -S`): Performance 86, Accessibility 100, SEO 100. Toàn bộ test PHP vẫn qua.
