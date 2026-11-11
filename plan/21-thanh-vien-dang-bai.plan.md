# Thành viên tự đăng bài (chữ + ảnh), không qua phiếu thu chi

Ngày: 11/11/2026. Trạng thái: **đã duyệt** (11/11/2026), **xong cả 4 mốc** (11/11/2026), chưa commit, chưa deploy.

## Yêu cầu

- Thành viên đăng được bài gồm **chữ và ảnh** mà không phải tạo phiếu thu chi.
- Ô viết bài (textarea + chọn ảnh) nằm ngay ở **trang cá nhân** và **timeline nhóm**.

Đã chốt khi hỏi (11/11/2026):

| # | Câu hỏi | Chốt |
| --- | --- | --- |
| 1 | Ai được đăng? | Mọi thành viên. QTV tắt được trong Cài đặt nhóm. |
| 2 | Bài hiện ở đâu? | Một bài hiện ở **cả** trang cá nhân của người đăng **và** timeline nhóm, dù đăng từ chỗ nào. |
| 3 | Có duyệt bài không? | Mặc định viết xong là đăng luôn. Cài đặt nhóm có lựa chọn **Đăng luôn** / **Chờ QTV duyệt**. |

Hiện nay (plan/08 mục 5): thành viên không tự đăng được gì, mọi bài trên timeline là phiếu thu chi do QTV tạo. Plan này thêm loại bài thứ hai: **bài đăng** (gọi trong code là *status*), không có số tiền, loại, chiến dịch.

---

## 1. Bài đăng thuộc về đâu

- Mỗi bài thuộc **một nhóm** và **một tài khoản**. Không có bài "chỉ ở trang cá nhân", vì tài khoản dùng chung nhiều nhóm và quyền xem đi theo nhóm (public/private).
- Đăng ở timeline nhóm hay ở trang cá nhân đều tạo cùng một bài trong nhóm đó:
  - Timeline nhóm `/{ma-nhom}`: hiện cùng các phiếu, xếp theo giờ đăng.
  - Trang thành viên `/{ma-nhom}/members/{id}` và cột "Hoạt động" ở `/profile`: hiện cùng các phiếu người đó đóng/nhận.
- Ở `/profile`, bài được đăng vào **nhóm đang chọn** ở hàng nút chọn nhóm (đã có). Ô viết ghi rõ "Đăng vào **Chi đoàn A12**".
- Ai xem được timeline nhóm thì xem được bài (giống phiếu). Nhóm riêng tư: chỉ thành viên.

## 2. Cài đặt nhóm: chế độ đăng bài

Thêm vào Cài đặt nhóm (quản trị), mục **"Thành viên đăng bài"**, chọn một trong ba:

| Giá trị | Nhãn | Ý nghĩa |
| --- | --- | --- |
| `open` (mặc định) | Đăng luôn | Thành viên đăng là hiện ngay. |
| `review` | Chờ QTV duyệt | Bài của thành viên vào hàng chờ, duyệt xong mới hiện. |
| `off` | Tắt | Chỉ trưởng nhóm / QTV đăng được. Thành viên không thấy ô viết bài. |

- Trưởng nhóm / QTV đăng thì **luôn hiện ngay**, ở mọi chế độ.
- Đổi từ `review` sang `open`: bài đang chờ **không** tự hiện, vẫn nằm trong hàng chờ để QTV xử lý.
- Đổi sang `off`: bài đã đăng vẫn giữ nguyên.

## 3. Database

Migration `create_status_posts_table`:

**`status_posts`**

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| id | bigint | |
| group_id | FK groups, cascade | dùng `BelongsToGroup` |
| user_id | FK users, cascade | người đăng |
| body | text null | tối đa 5000 ký tự, văn bản thuần. Bài phải có chữ **hoặc** ít nhất một ảnh |
| status | enum `published`, `pending`, `rejected` | |
| published_at | datetime null | lúc hiện lên timeline (lúc đăng, hoặc lúc được duyệt); dùng để xếp thứ tự |
| reviewed_by | FK users null | QTV duyệt/từ chối |
| reviewed_at | timestamp null | |
| reject_reason | varchar(300) null | lý do từ chối (không bắt buộc) |
| hidden_at, hidden_by | | QTV ẩn bài đã đăng (người viết vẫn thấy, nhãn "đã bị ẩn"), giống bình luận |
| timestamps, softDeletes | | |
| index | (group_id, status, published_at), (group_id, user_id, published_at) | |

**`status_post_photos`**: giống `transaction_photos` (status_post_id, path, thumb_path, width, height, sort_order).

**Tim và bình luận dùng chung bảng cũ** (để `PostComments`, ẩn/xóa bình luận, giới hạn tốc độ chạy y như phiếu):

- `reactions`, `comments`: thêm cột `status_post_id` (FK null, cascade); đổi `transaction_id` thành null được.
- Mỗi dòng có đúng một trong hai cột (kiểm trong code; MySQL không cần CHECK).
- `reactions` thêm unique (status_post_id, user_id).

Cập nhật bảng ở plan/01.

## 4. Ảnh

- Dùng lại `ImageStore::storeEventPhoto` và `shrinkImage` phía trình duyệt: xoay EXIF, bỏ EXIF/GPS, bản 1600px + thumbnail 480px, webp 80.
- Tối đa **10 ảnh/bài**, 10 MB/ảnh trước xử lý (dùng `cashflow.limits`).
- Lưu `groups/{group_id}/posts/YYYY/MM/...` trên đĩa `media`.
- Phục vụ qua route mới `GET /media/{ma-nhom}/s/{photoId}/{sm|lg}`, kiểm quyền như ảnh sự kiện: xem được nhóm, và bài đang `published` chưa ẩn; bài chờ duyệt/bị ẩn chỉ người đăng và QTV xem được. Cache giống ảnh phiếu (public/private theo nhóm).
- Xóa bài (xóa mềm) thì giữ ảnh; dọn ảnh khi xóa hẳn (cùng lệnh dọn phiếu xóa quá 30 ngày nếu có, không thì xóa file ngay khi xóa hẳn).

## 5. API

Trong `/api/g/{ma-nhom}`:

| Method | Đường dẫn | Ai | Ghi chú |
| --- | --- | --- | --- |
| POST | `/statuses` | thành viên (`group.role:member`, theo chế độ ở mục 2) | multipart: `body`, `photos[]`. Trả thẻ bài. Giới hạn **10 bài/giờ/người** |
| POST | `/statuses/{id}` | người đăng | sửa chữ, thêm/bỏ ảnh (`photos[]` mới, `remove_photo_ids[]`). Bài `published` của thành viên ở chế độ `review` sửa xong **không** phải duyệt lại (xem câu hỏi Q1) |
| DELETE | `/statuses/{id}` | người đăng, QTV | xóa mềm |
| POST | `/statuses/{id}/hide` | QTV | ẩn / bỏ ẩn |
| GET | `/statuses/{id}` | ai xem được nhóm | một bài (mở từ link chia sẻ) |
| POST | `/statuses/{id}/heart` | thành viên | như phiếu |
| GET, POST | `/statuses/{id}/comments` | như phiếu | |

Quản trị `/api/g/{ma-nhom}/admin`:

| Method | Đường dẫn | Ghi chú |
| --- | --- | --- |
| GET | `/statuses?status=pending\|rejected` | hàng chờ (cũ trước) hoặc bài đã từ chối; kèm `counts`, `post_mode` |
| GET | `/sepay/count` | trả thêm `posts` (số bài chờ duyệt) cho chấm đỏ |
| POST | `/statuses/{id}/approve` | `published_at = now()` |
| POST | `/statuses/{id}/reject` | lý do không bắt buộc |
| PUT | `/settings` | thêm `post_mode` (`open`, `review`, `off`) |

- `GET /api/g/{ma-nhom}` → `viewer` thêm `can_post` (bool) và `post_needs_review` (bool) để giao diện biết có hiện ô viết và có ghi "Bài sẽ chờ QTV duyệt".
- `InteractionController` tách phần tìm bài: `/posts/{id}` vẫn là phiếu (giữ nguyên, không đổi URL cũ), `/statuses/{id}` là bài đăng. Bình luận và tim dùng chung một service, chỉ khác cột khóa.

## 6. Timeline

**Timeline nhóm (`TimelineFeed`)**: truy vấn khóa bài hiện có thêm `UNION ALL` các bài đăng:

- Khóa `s{id}`, thời điểm `published_at`.
- Chỉ bài `published`, chưa ẩn, chưa xóa. QTV thấy cả bài đã ẩn (nhãn "Đã ẩn").
- Khi lọc theo thu/chi hoặc loại (API vẫn nhận `type`, `category_id`) thì bỏ bài đăng.
- Bài của chính mình đang **chờ duyệt** hiện trên đầu timeline của mình với nhãn "Đang chờ duyệt" (chỉ mình thấy), không nằm trong phân trang.

**Trang thành viên / `/profile` (`MemberProfileController::timeline`)**: gộp phiếu có `contact_id` của người đó **và** bài đăng có `user_id` của người đó trong nhóm, xếp theo thời gian. Không còn trả rỗng khi tài khoản chưa gắn danh bạ: vẫn có bài đăng.

Dữ liệu thẻ bài đăng trả cho SPA (`StatusPostPresenter::card`):

```
{ kind: 'status', id, body, published_at, status, hidden,
  author: { name, avatar_url, member_id, role_label },
  photos: [...], hearts_count, hearted, comments_count, comments, comments_more,
  can_edit, can_delete, can_hide }
```

## 7. Giao diện

### Ô viết bài `PostComposer.vue` (dùng chung)

```
┌──────────────────────────────┐
│ (avatar) Bạn đang nghĩ gì?    │   ← thu gọn, bấm để mở
└──────────────────────────────┘

┌──────────────────────────────┐
│ (avatar) Minh Hiền            │
│ ┌──────────────────────────┐ │
│ │ textarea tự giãn          │ │
│ └──────────────────────────┘ │
│ [ảnh][ảnh][ảnh][ + ]          │   ← ảnh thu nhỏ, mỗi ảnh có nút ✕
│ [🖼 Ảnh]           [Đăng]     │
│ Bài sẽ chờ QTV duyệt.         │   ← chỉ khi post_needs_review
└──────────────────────────────┘
```

- Bấm ô thu gọn hoặc nút 🖼 thì mở ra. Nút 🖼 mở chọn nhiều ảnh (`accept="image/*" multiple`), thu nhỏ bằng `shrinkImage` trước khi gửi.
- Nút **Đăng** sáng khi có chữ hoặc ảnh. Đang gửi: nút quay, khóa ô viết; hiện thanh tiến độ khi có ảnh.
- Đăng xong: thu gọn ô, chèn thẻ bài lên đầu timeline ngay (không tải lại). Chế độ duyệt: thẻ có nhãn "Đang chờ duyệt" và toast "Đã gửi, bài sẽ hiện khi QTV duyệt".
- Bản nháp chữ giữ trong `localStorage` theo nhóm, phòng lỡ tay tải lại trang.
- Lỗi mạng: giữ nguyên chữ và ảnh, toast "Chưa đăng được, bạn thử lại nhé".

### Đặt ô viết ở đâu

| Trang | Vị trí | Điều kiện |
| --- | --- | --- |
| Timeline nhóm `/{ma-nhom}` | Đầu cột Dòng thời gian (điện thoại: dưới thẻ Chiến dịch, trên dòng thời gian) | `viewer.can_post` |
| Trang thành viên của chính mình | Đầu cột "Hoạt động" | `is_me` và `can_post` |
| `/profile` | Đầu cột "Hoạt động", dưới hàng chọn nhóm, ghi "Đăng vào {nhóm}" | `can_post` của nhóm đang chọn |

Khách và người không thuộc nhóm không thấy ô viết.

### Thẻ bài đăng `StatusCard.vue`

- Đầu thẻ: avatar + tên người đăng (bấm ra trang thành viên), dòng "2 giờ trước · Quản trị viên" (nhãn vai trò chỉ với QTV).
- Thân: chữ (giữ xuống dòng, link tự bấm được, quá 6 dòng thì "Xem thêm"), lưới ảnh `PhotoGrid` + `PhotoViewer`.
- Chân: tim và bình luận, dùng lại phần chân của `PostCard` (tách ra component `PostActions.vue` để hai thẻ dùng chung).
- Menu ⋯: người đăng "Sửa bài", "Xóa bài"; QTV "Ẩn bài"/"Bỏ ẩn", "Xóa bài". Sửa bài mở lại `PostComposer` ngay trong thẻ.
- Khác thẻ phiếu: không có icon loại, không có số tiền; đầu thẻ là avatar người đăng.

### Duyệt bài (chế độ `review`)

- Trang quản trị mới **"Bài chờ duyệt"** `/{ma-nhom}/admin/posts`: mỗi bài một thẻ (như trên timeline) kèm nút **Duyệt** / **Từ chối**.
- Lối vào: mục trong menu quản trị (cạnh hàng chờ SePay / bill) có số đếm; Tổng quan có dòng "N bài chờ duyệt".
- Trên timeline nhóm, QTV thấy dòng "N bài đang chờ duyệt →" ở đầu khi N > 0.
- Bị từ chối: người đăng thấy bài trên trang của mình với nhãn "Không được duyệt" (và lý do nếu có), chỉ còn nút Xóa.
- Không gửi email (xem Q2).

### Cài đặt nhóm

Thêm mục "Thành viên đăng bài" với ba lựa chọn ở mục 2 (`SegmentedControl` hoặc radio), kèm dòng giải thích ngắn dưới mỗi lựa chọn.

## 8. Không làm (bản này)

- Không đăng video, không gắn link xem trước, không nhắc tên (@), không ghim bài.
- Không chia sẻ bài sang nhóm khác.
- Không thông báo realtime / email khi có bài mới (giống bình luận, plan/03 mục 5).
- Không gắn bài đăng với phiếu thu chi. Muốn có số tiền thì vẫn tạo phiếu như cũ.

## 9. Kiểm thử

Feature test:

- Đăng bài: thành viên đăng được chữ, ảnh, cả hai; bài rỗng (không chữ, không ảnh) bị từ chối; quá 10 ảnh bị từ chối; khách và người nhóm khác bị 403/404.
- Chế độ: `open` hiện ngay; `review` vào `pending`, không có trên timeline, có trong hàng chờ; QTV đăng ở `review` vẫn hiện ngay; `off` thành viên bị 403, QTV vẫn đăng được.
- Duyệt / từ chối: chỉ QTV; duyệt xong có `published_at` và hiện trên timeline.
- Timeline nhóm: có cả phiếu, thẻ gộp chiến dịch và bài đăng, đúng thứ tự, phân trang không trùng/sót; lọc thu/chi thì không có bài đăng; bài ẩn chỉ QTV thấy.
- Trang thành viên: có bài đăng của người đó trong nhóm này, không có bài của họ ở nhóm khác; tài khoản chưa gắn danh bạ vẫn thấy bài đăng.
- Sửa/xóa: chỉ người đăng sửa; người đăng và QTV xóa; người khác 403.
- Tim, bình luận trên bài đăng chạy như trên phiếu; bình luận của phiếu không lẫn sang bài đăng.
- Ảnh `/media/{ma-nhom}/s/...`: bài chờ duyệt chỉ người đăng và QTV xem; nhóm private khách bị 404; ảnh không còn EXIF.

Thử trên trình duyệt cỡ điện thoại (kèm ảnh chụp): đăng bài có 3 ảnh từ timeline nhóm và từ `/profile`, bài hiện ở cả hai nơi; sửa, xóa; chế độ duyệt từ đầu đến cuối.

## 10. Các mốc code

| Mốc | Nội dung | Dừng cho duyệt |
| --- | --- | --- |
| S1 | Migration, model `StatusPost`, `StatusPostPhoto`, API đăng/sửa/xóa/ẩn, ảnh, tim/bình luận dùng chung, `post_mode` trong cài đặt, test | ✓ |
| S2 | Gộp bài đăng vào timeline nhóm và timeline thành viên, `StatusPostPresenter`, test thứ tự/phân trang | ✓ |
| S3 | `PostComposer`, `StatusCard`, `PostActions`; đặt ô viết ở timeline nhóm, trang thành viên, `/profile` | ✓ (kèm ảnh chụp) |
| S4 | Chế độ duyệt: trang "Bài chờ duyệt", số đếm, mục cài đặt nhóm | ✓ (kèm ảnh chụp) |

Tiến độ 11/11/2026: **xong S1** (chỉ máy chủ, chưa có giao diện).

- Sửa bài dùng `POST /statuses/{id}` (không phải PUT) vì gửi ảnh bằng multipart, giống sửa phiếu.
- Tim/bình luận: route `/statuses/{id}/heart`, `/statuses/{id}/comments` gọi chung `InteractionController` với phiếu. Sửa/ẩn/xóa bình luận vẫn qua `/comments/{id}` như cũ.
- `viewer` có thêm `can_post`, `post_needs_review`. Cài đặt nhóm nhận và trả `post_mode`; không gửi thì giữ nguyên.
- Test: `tests/Feature/Transactions/StatusPostsTest.php` (10 test). Toàn bộ 184 test qua.

Tiến độ 11/11/2026: **xong S2** (chỉ máy chủ).

- Timeline nhóm: truy vấn khóa bài `UNION ALL` bài đăng (`s{id}`, xếp theo `published_at`). Lọc `type`/`category_id` thì bỏ bài đăng; lọc `year` thì lọc theo `published_at`. QTV thấy cả bài đã ẩn.
- Trang 1 có thêm `pending_mine`: bài chờ duyệt của chính người xem, ngoài phân trang. Khách không có trường này.
- Timeline thành viên: gộp phiếu của người đó và bài đăng của họ trong nhóm này, xếp theo thời gian. Chính chủ thấy cả bài chờ duyệt, bị từ chối, bị ẩn (bài chưa hiện xếp theo giờ gửi); QTV thấy thêm bài đã ẩn; người khác chỉ thấy bài đang hiện. Tài khoản chưa gắn danh bạ vẫn có bài đăng.
- `TimelineFeed::cards()` dựng thẻ cho cả phiếu và bài đăng, dùng chung cho hai timeline.
- Giao diện chưa biết `kind = status`: trước khi xong S3 không deploy, vì thẻ bài đăng sẽ hiện sai.
- Thêm 5 test (thứ tự, phân trang 50 bài qua 3 trang không trùng/sót, quyền thấy, timeline thành viên). Toàn bộ 195 test qua.

Tiến độ 11/11/2026: **xong S3** (giao diện).

- `PostActions.vue`: hàng tim/bình luận tách từ `PostCard`, dùng chung cho phiếu và bài đăng. `PostComments` gọi `/statuses/...` khi `kind = status`.
- `PostComposer.vue`: dòng "Bạn đang nghĩ gì?" + nút ảnh; mở ra có tên, "Đăng vào {nhóm}" (ở `/profile`), ô chữ tự giãn, lưới ảnh có nút bỏ, ô "+" thêm ảnh, thanh tiến độ khi tải ảnh (`apiUpload` trong `lib/api.js`, dùng XHR), nháp chữ trong `localStorage` (`status-draft:{ma-nhom}`). Nút "Để sau" thu gọn mà vẫn giữ nháp. Dùng lại cho Sửa bài ngay trong thẻ.
- `StatusCard.vue`: avatar + tên bấm được, "Vừa xong / 5 phút trước" (`relativeTime`), nhãn vai trò QTV, "đã sửa"; link trong chữ bấm được (`linkify` trong `lib/text.js`, không dùng v-html); bài dài thu gọn 6 dòng; menu ⋯ Sửa / Ẩn / Xóa. Bài chờ duyệt, bị từ chối, bị ẩn: viền nét đứt + dòng giải thích, không có tim/bình luận.
- Đặt ô viết: timeline nhóm (đầu cột dòng thời gian, `viewer.can_post`), trang thành viên của chính mình, `/profile` (nhóm đang chọn). Bài chờ duyệt của mình hiện trên đầu timeline nhóm.
- Đã thử trên trình duyệt cỡ điện thoại (dữ liệu demo, tài khoản trưởng nhóm): đăng bài 3 ảnh, sửa (bỏ 1 ảnh, đổi chữ), tim, bình luận, bài chờ duyệt hiện đầu trang, `/profile`, trang thành viên của mình có ô viết còn của người khác thì không, xóa bài. Không có lỗi console. Chưa thử bằng tài khoản thành viên thường ở chế độ chờ duyệt trên giao diện (đã có feature test).

Tiến độ 11/11/2026: **xong S4** (duyệt bài).

- API quản trị: `GET /statuses?status=pending|rejected`, `POST /statuses/{id}/approve` (duyệt cả bài đã từ chối), `POST /statuses/{id}/reject` (lý do không bắt buộc). Số đếm gộp vào `GET /sepay/count` (`posts`) thay vì route riêng; Tổng quan có `pending_posts`; trang 1 timeline nhóm có `pending_review` cho QTV.
- Duyệt / từ chối / ẩn không đổi `updated_at`, để thẻ không hiện "đã sửa".
- Giao diện: trang `/{ma-nhom}/admin/posts` "Duyệt bài đăng" (tab Chờ duyệt / Không duyệt, nút Duyệt, Từ chối mở bảng nhập lý do, Duyệt lại); mục trong menu quản trị kèm chấm đỏ (điện thoại: trong tab "Thêm", chấm đỏ cộng cả người xin vào nhóm); thẻ "N bài đăng chờ duyệt" ở Tổng quan; dòng "N bài đang chờ duyệt" trên đầu timeline nhóm cho QTV; mục "Thành viên đăng bài" ở Cài đặt nhóm (3 lựa chọn, lưu cùng nút Lưu cài đặt, link `#post-mode`).
- Thêm 2 test (duyệt, từ chối, duyệt lại, số đếm, quyền, nhóm khác). Toàn bộ 200 test qua.
- Đã thử trên trình duyệt cỡ điện thoại (dữ liệu demo): đổi chế độ trong Cài đặt, dòng nhắc trên timeline, trang duyệt, từ chối có lý do, duyệt, tab Không duyệt. Dữ liệu thử đã dọn, nhóm demo trả về "Đăng luôn".

## 11. Câu hỏi đã chốt (11/11/2026)

| # | Câu hỏi | Chốt |
| --- | --- | --- |
| Q1 | Chế độ `review`: bài đã duyệt mà người đăng sửa lại thì có phải duyệt lại không? | **Không**. Nếu sửa thành nội dung không phù hợp thì QTV ẩn/xóa. |
| Q2 | Có gửi email cho QTV khi có bài chờ duyệt không? | **Không**. Chỉ có số đếm trong quản trị và dòng nhắc trên timeline. |
| Q3 | Giới hạn độ dài chữ và số ảnh? | **5000 ký tự, 10 ảnh/bài**, 10 bài/giờ/người. |
| Q4 | Bài đăng xếp theo giờ đăng/giờ duyệt, phiếu xếp theo ngày phát sinh, nên phiếu nhập bù ngày cũ nằm dưới các bài đăng mới. | **Đồng ý**, giữ như timeline hiện nay. |
