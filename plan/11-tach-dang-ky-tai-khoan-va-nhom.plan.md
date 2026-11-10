# Tách đăng ký tài khoản và tạo nhóm

Ngày: 10/11/2026. Trạng thái: **đã duyệt, đã làm** (10/11/2026).

## Hiện tại

- `/register` (`RegisterPage.vue` → `POST /api/register`) bắt điền **cả** thông tin người dùng lẫn thông tin nhóm, tạo tài khoản + nhóm trong một bước, xong vào `/{ma-nhom}/admin?welcome=1`.
- Người đã đăng nhập tạo thêm nhóm ở `/groups` qua một Sheet bật lên (`POST /api/groups`). Sheet này sơ sài hơn form đăng ký: không kiểm tra mã nhóm khi gõ, chế độ xem là ô select.
- Trang chủ có hai nút "Đăng ký nhóm" và "Tạo tài khoản" nhưng **cả hai cùng trỏ về `/register`**.

## Quyết định đã chốt

- **Đăng ký tài khoản** và **tạo nhóm** là hai màn hình riêng, hai API riêng.
- Bấm **"Tạo nhóm"** thì luôn vào màn hình tạo nhóm `/groups/new`. Chưa đăng nhập thì màn hình vẫn mở, hiện cảnh báo **"Bạn cần tạo tài khoản trước"** kèm nút Tạo tài khoản / Đăng nhập. Đăng ký hoặc đăng nhập xong thì quay lại đúng `/groups/new`.
- Bấm **"Tạo tài khoản"** thì vào màn hình đăng ký tài khoản `/register`.
- Form tạo nhóm là **trang riêng `/groups/new`**, bỏ Sheet trong `/groups`.
- **Email không bắt buộc** khi đăng ký tài khoản. Ai không có email thì quên mật khẩu phải nhờ trưởng nhóm/QTV (luồng này đã có ở plan/02 mục 1). Cột `users.email` vốn đã `nullable`, không cần migration.
- SĐT đã có tài khoản (kể cả tài khoản do trưởng nhóm cấp) → báo "Số điện thoại này đã có tài khoản", kèm link Đăng nhập và Quên mật khẩu. Không làm luồng nhận lại tài khoản bằng OTP.

## Việc cần làm

### 1. API

| Endpoint | Thay đổi |
| --- | --- |
| `POST /api/register` (guest) | Chỉ nhận `name*`, `phone*`, `email` (không bắt buộc, vẫn `unique`), `password*`, `website` (honeypot). Bỏ hết trường nhóm. Tạo user, đăng nhập luôn, trả `{ me }`. Giữ giới hạn 3 lần/giờ/IP. `RegisterController` không còn gọi `GroupCreator`. |
| `POST /api/groups` (auth) | Giữ nguyên: tên, mã, chế độ xem, số dư đầu kỳ, giới hạn số nhóm mỗi tài khoản. Đây là lối tạo nhóm duy nhất phía người dùng. |
| `GET /api/group-code` | Giữ nguyên, trang tạo nhóm dùng để kiểm tra mã và gợi ý mã. |
| `/system` tạo nhóm hộ | Không đổi (vẫn tạo trưởng nhóm + nhóm một lần, vì là QTV HIPOTECH làm hộ). |

### 2. Giao diện

**`/register` — Tạo tài khoản** (`RegisterPage.vue` viết lại)

- Tiêu đề "Tạo tài khoản", mô tả ngắn "Dùng một tài khoản cho mọi nhóm bạn tham gia."
- Trường: Họ tên*, Số điện thoại*, Email (ghi chú "Không bắt buộc. Có email thì tự lấy lại được mật khẩu."), Mật khẩu*.
- Lỗi SĐT đã có tài khoản: hiện dưới ô SĐT, kèm link Đăng nhập (mang theo `next`) và Quên mật khẩu.
- Nhận `?next=/...` giống trang đăng nhập. Đăng ký xong: có `next` thì về `next`, không thì về `/groups`.
- Link cuối form: "Đã có tài khoản? Đăng nhập" (giữ `next`).

**`/groups/new` — Tạo nhóm** (trang mới, `GroupCreatePage.vue`)

- Không đặt `meta.auth` để khách mở được.
- Nội dung form chuyển từ phần "Nhóm của bạn" của form đăng ký cũ: Tên nhóm*, Mã nhóm* (tự gợi ý từ tên, kiểm tra trùng khi gõ, hiện tiền tố `wepo.hipotech.vn/`), thẻ chọn Công khai/Riêng tư, Số tiền quỹ đang có.
- **Chưa đăng nhập**: trên đầu form có khung cảnh báo "Bạn cần tạo tài khoản trước" + hai nút **Tạo tài khoản** (`/register?next=/groups/new`) và **Đăng nhập** (`/login?next=/groups/new`). Các ô nhóm vẫn hiện nhưng khóa lại (khách xem trước được cần chuẩn bị gì), nút "Tạo nhóm" bị ẩn.
- **Đã đăng nhập**: form dùng bình thường, gọi `POST /api/groups`, xong `loadMe(true)` rồi vào `/{ma-nhom}/admin?welcome=1` (màn hình Bắt đầu nhanh như cũ).
- Nút quay lại: đã đăng nhập về `/groups`, chưa đăng nhập về `/`.

**`/groups` — Chọn nhóm**

- Bỏ Sheet tạo nhóm, nút "Tạo nhóm mới" thành link sang `/groups/new`.
- Chưa thuộc nhóm nào (người vừa đăng ký xong): câu hiện tại giữ ý, viết rõ hơn: "Bạn chưa thuộc nhóm nào. Tạo nhóm mới, hoặc gửi số điện thoại cho trưởng nhóm để được thêm vào nhóm."

**Trang chủ, trang đăng nhập**

- `HomePage.vue` (chưa đăng nhập): **Đăng nhập** (nút chính), **Tạo nhóm** → `/groups/new`, dòng "Chưa có tài khoản? **Tạo tài khoản**" → `/register`.
- `LoginPage.vue`: link cuối "Đăng ký nhóm mới" đổi thành "Chưa có tài khoản? Tạo tài khoản" → `/register`, mang theo `next` nếu có.
- Route `/groups/new` khai báo **trước** `/groups` và các route `/:group/...` trong `app.js`.

### 3. Tài liệu

- plan/02 mục 2 viết lại thành hai phần: "Đăng ký tài khoản" và "Tạo nhóm", theo quyết định ở trên. Chống spam: 3 lần đăng ký tài khoản/giờ/IP + honeypot; tạo nhóm giữ giới hạn hiện có (10 lần/giờ và tối đa N nhóm/tài khoản).
- plan.md: thêm route `/groups/new`, sửa mô tả `/register`.

### 4. Kiểm thử

Sửa `tests/Feature/Auth/RegisterTest.php` và thêm test cho `POST /api/groups`:

- Đăng ký chỉ với tên, SĐT, mật khẩu (không email) → tạo user, đã đăng nhập, **không** tạo nhóm nào.
- Đăng ký có email trùng → lỗi `email`.
- SĐT đã có tài khoản → lỗi `phone`, không tạo user mới.
- Honeypot vẫn chặn.
- Gửi kèm trường nhóm cũ (`group_name`...) bị bỏ qua, không tạo nhóm.
- `POST /api/groups` khi chưa đăng nhập → 401; đã đăng nhập → tạo nhóm, người tạo là `owner`; mã trùng/mã cấm bị từ chối (chuyển các ca mã nhóm từ RegisterTest sang đây); quá giới hạn số nhóm → lỗi.
- Chạy thử trên trình duyệt khổ điện thoại: khách bấm Tạo nhóm → thấy cảnh báo → Tạo tài khoản → quay lại `/groups/new` → tạo nhóm → vào Bắt đầu nhanh.

## Ngoài phạm vi

- Xác minh SĐT bằng OTP, xác minh email.
- Tham gia nhóm bằng link mời / mã nhóm (hiện vẫn do trưởng nhóm thêm SĐT vào danh bạ rồi cấp tài khoản). → Đã làm ở [plan/13](13-link-dang-ky-vao-nhom.plan.md).
