# Mở từ icon cũng nhận bản mới sau deploy

Ngày: 12/11/2026. Trạng thái: **đã duyệt** (12/11/2026), đã code xong mốc 1 và 2, chưa commit, chưa deploy.

## Vấn đề

Sau mỗi lần deploy, mở WePo bằng Safari thì thấy bản mới, còn mở từ icon trên màn hình chính iPhone thì vẫn là bản cũ. Bấm qua lại nhiều link cũng không đổi. Có ba nguyên nhân cộng lại:

1. **iOS không tải lại app mở từ icon.** WePo là một trang Vue duy nhất. Thoát ra rồi bấm icon lần nữa, iOS chỉ đánh thức lại đúng trang đang nằm trong bộ nhớ, vẫn chạy code JS từ trước khi deploy. App từ icon còn có bộ nhớ và service worker riêng, tách khỏi Safari.
2. **Bấm link không tải lại trang.** vue-router đổi trang ngay trên máy, không hỏi lại server `index.html`, nên trang không biết đã có bản mới.
3. **Cơ chế tự tải lại hiện có không bao giờ chạy.** `router.onError` trong `app.js` chỉ tải lại khi file JS cũ bị 404. Nhưng `public/sw.js` lấy mọi file `/build/` từ cache trước, nên trang nào đã mở rồi thì file cũ vẫn có trong cache, không 404, không tải lại.

Cách tạm thời hiện nay: vuốt tắt hẳn WePo trong trình chuyển app rồi mở lại.

## Đề xuất

### 1. Mỗi bản build có một mã phiên bản

Dùng `Vite::manifestHash()` của Laravel: md5 của `public/build/manifest.json`. Mỗi lần `npm run build` có file đổi thì mã đổi. Không phải tự sinh mã, không phải sửa `deploy.sh`.

- **Mã của trang đang chạy**: `app.blade.php` in vào `<meta name="wepo-build" content="...">`. Đây đúng là mã của bộ file JS mà trang này đã tải.
- **Mã mới nhất trên server**: `/api/app` (đang có, không cần đăng nhập) trả thêm trường `build`. Gắn `Cache-Control: no-store` để trình duyệt không giữ lại. Service worker vốn không đụng tới `/api/`.
- Chạy dev (`npm run dev`) không có manifest nên mã rỗng: tắt hẳn việc kiểm tra.

### 2. Khi nào hỏi server

- **Khi app quay lại màn hình** (`visibilitychange` sang `visible`): đúng lúc bấm icon mở lại app đang nằm nền. Tối đa 1 lần mỗi 30 giây.
- **Khi chuyển trang** (`router.beforeEach`) nếu đã quá 5 phút chưa hỏi: cho trường hợp để mở Safari cả buổi không tắt.
- Hỏi lỗi (mất mạng, server đang deploy) thì bỏ qua, lần sau hỏi lại. Không báo gì cho người dùng.

### 3. Thấy bản mới thì làm gì

Đánh dấu "đã có bản mới". **Lần bấm chuyển trang kế tiếp** sẽ tải lại hẳn trang đích (`window.location.assign(to.fullPath)`) thay cho chuyển trang trong Vue. Trang mới tải về có meta mã mới và trỏ tới file JS tên mới, nên cache cũ không còn ảnh hưởng.

Vì sao **không** tải lại ngay lúc app quay lại màn hình: người dùng hay đang gửi bill, chuyển sang app ngân hàng chụp màn hình rồi quay lại WePo. Tải lại ngay lúc đó sẽ mất ảnh và chữ đang nhập dở. Đợi lúc họ tự bấm sang trang khác thì không mất gì.

Chặn lặp: dùng chung kiểu chặn của `router.onError` (`sessionStorage`, không tải lại 2 lần trong 10 giây).

Cách khác đã cân nhắc, không chọn:

| Cách | Vì sao không |
|---|---|
| Tải lại ngay khi app quay lại màn hình | Mất form đang nhập dở (gửi bill, viết bài) |
| Hiện thanh "Có bản mới, bấm để cập nhật" | Thêm một thứ phải bấm; người dùng nhóm quỹ không quan tâm phiên bản. Có thể thêm sau nếu cần |
| Bỏ cache `/build/` trong service worker | Mở app chậm hơn mà vẫn không sửa được nguyên nhân 1 và 2 |
| Thêm `define: __BUILD_ID__` trong Vite | Phải tự sinh mã lúc build; `manifestHash()` đã có sẵn và luôn khớp với file thật trên server |

### 4. Dọn cache cũ trong service worker

Hiện cache `wepo-v2-static` giữ mọi file JS của mọi bản build từ trước tới giờ, chỉ xóa khi đổi `VERSION`. Sửa nhẹ: khi trang tải lại vì có bản mới, xóa các file `/build/` trong cache (sau khi tải lại thì trang chỉ cần file mới). Đổi `VERSION` thành `wepo-v3` để lần này xóa sạch cache cũ một lượt.

### 5. Lưu ý lần deploy đầu tiên

Các app đang mở từ icon vẫn chạy code cũ, chưa có phần kiểm tra này. **Lần deploy bản sửa này**, mỗi máy vẫn phải vuốt tắt WePo và mở lại một lần (hoặc đợi iOS tự tắt app nằm nền lâu). Từ lần deploy sau trở đi thì tự cập nhật.

## Cách làm

- `routes/api.php`: `/api/app` trả thêm `'build' => Vite::manifestHash()`, kèm header `no-store`.
- `resources/views/app.blade.php`: thêm `<meta name="wepo-build" content="{{ Vite::manifestHash() }}">`.
- `resources/js/app.js` (hoặc file nhỏ `resources/js/lib/update-check.js`): đọc meta, hỏi `/api/app` theo mục 2, cờ "có bản mới", trong `router.beforeEach` thì tải lại trang đích nếu cờ bật.
- `public/sw.js`: đổi `VERSION`, nhận lệnh xóa file `/build/` cũ (mục 4).
- Test: `/api/app` có trường `build` và header `no-store`.

## Mốc

1. Backend: `/api/app` trả `build`, meta trong trang, test. Kiểm tra: `php artisan test` xanh, `curl /api/app` có `build` khớp md5 của `public/build/manifest.json`.
   - **Xong** 12/11/2026: 208 test xanh (thêm 2 test trong `SpaRoutesTest`: mã khớp md5 manifest, đổi manifest thì mã đổi, có file `hot` thì `null`, header `no-store`; trang có meta khớp API). Trên server local: `/api/app` trả `build` = `02085249…` đúng bằng `md5 public/build/manifest.json`, header `Cache-Control: no-store, private`, trang `/login` có `<meta name="wepo-build">` cùng mã.
2. Frontend và service worker. Kiểm tra trên trình duyệt local (bản build, không phải dev): mở trang, sửa một chữ trong code rồi `npm run build` lần nữa, chuyển tab đi rồi quay lại, bấm một link → trang tải lại hẳn và hiện chữ mới; đang nhập dở form gửi bill rồi chuyển tab qua lại thì form không mất; không lỗi console, không tải lại lặp.
   - **Xong** 12/11/2026: `resources/js/lib/updateCheck.js` (hỏi server, cờ có bản mới, `reloadTo` dùng chung chặn lặp 10 giây với `router.onError`), gắn vào `router.beforeEach` trong `app.js`; `sw.js` lên `wepo-v3` và nhận lệnh `purge-build`. Chỉ tải lại khi đổi đường dẫn, đổi bộ lọc cùng trang thì không.
   - Thử trên trình duyệt (bản build, giả lập deploy bằng cách sửa `manifest.json` cho mã đổi): chưa có bản mới thì bấm link vẫn chuyển trang trong Vue; app quay lại màn hình chưa tới 30 giây thì không hỏi server, quá 30 giây thì hỏi đúng 1 lần và **không** tải lại; chữ đang gõ trong form đăng nhập vẫn còn; bấm sang trang khác thì tải lại hẳn, meta mang mã mới; sau đó bấm qua lại không tải lại nữa. Không lỗi console nào từ phần này.
   - Chưa thử được service worker trên trình duyệt: khung trình duyệt của Claude không cho đăng ký service worker (lỗi ngay lúc tải `sw.js`, dù `/sw.js` trả 200). Đã chạy `sw.js` trong Node với `caches` giả: bản v3 xóa cache v2; lệnh `purge-build` chỉ xóa file `/build/`, giữ `offline.html` và icon. Sẽ thấy thật ở mốc 3 trên iPhone.
3. Deploy và thử trên iPhone thật (bạn làm): vuốt tắt WePo một lần như mục 5. Lần deploy sau, mở từ icon, bấm sang trang khác → thấy bản mới mà không phải tắt app.
