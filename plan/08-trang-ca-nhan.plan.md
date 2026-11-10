# 08 — Trang cá nhân kiểu Facebook

Trạng thái: **ĐANG BÀN, CHƯA THỰC THI**. Bản nháp ngày 10/11/2026.

Yêu cầu: trang thành viên giống Facebook, mỗi người tự tải **ảnh bìa** và **ảnh đại diện**, tự sửa thông tin của mình, và có **timeline riêng** của người đó.

---

## 1. Hai trang

| Đường dẫn | Ai vào | Dùng để |
| --- | --- | --- |
| `/ca-nhan` | Chính chủ (bắt buộc đăng nhập) | Trang cá nhân của tôi: đổi ảnh bìa/ảnh đại diện, sửa thông tin, xem timeline của tôi, đổi mật khẩu, đăng xuất. Thay cho form "Cá nhân" hiện tại. |
| `/{ma-nhom}/thanh-vien/{memberId}` | Ai xem được timeline nhóm thì xem được (giống quyết định số 7 ở plan.md) | Trang của một thành viên trong nhóm: ảnh, tên, vai trò, timeline của người đó **trong nhóm này**. Xem trang của chính mình thì có thêm nút "Sửa trang cá nhân", bấm vào sẽ mở `/ca-nhan`. |

- Đường dẫn dùng `group_members.id`, không dùng `users.id`. Như vậy mỗi nhóm chỉ thấy người của nhóm mình và không dò được tài khoản ở nhóm khác.
- Thành viên đã bị gỡ khỏi nhóm (`status = removed`) thì trang trả 404, trừ với QTV. QTV vẫn xem được, có nhãn "Đã rời nhóm".
- Người có trong danh bạ nhưng **chưa được cấp tài khoản** thì chưa có trang (xem câu hỏi Q2).
- Không bao giờ hiện SĐT, ngày sinh, địa chỉ trên trang này. Những thông tin đó nằm trong danh bạ, chỉ hiện ở trang quản trị.

## 2. Bố cục (mobile-first)

```
┌──────────────────────────────┐
│ Ảnh bìa 3:1        [📷 Đổi bìa]│
│ (Ảnh ĐD)📷                    │
│ Vũ Đức Tuấn                   │
│ Quản trị viên · Chi đoàn A12  │
│ "Giới thiệu ngắn…"            │
│ [Sửa trang cá nhân]           │   ← chỉ chính chủ
├──────────────────────────────┤
│ Đã đóng 2026   1.500.000 đ    │
│ Chiến dịch đã đóng   3/4      │
│ Đang mở: Thu quỹ 2026 ✓ Đã đóng│
├──────────────────────────────┤
│ Nhóm: [Chi đoàn A12 ▾]        │   ← chỉ ở /ca-nhan khi thuộc ≥2 nhóm
├──────────────────────────────┤
│ ── Tháng 11/2026 ──           │
│ [PostCard] Đóng quỹ năm +500k │
│ [PostCard] Thăm ốm bạn Lan…   │
└──────────────────────────────┘
```

- Máy tính: khung giữa rộng như trang nhóm. Ảnh bìa tràn ngang, ảnh đại diện tròn đè lên mép dưới ảnh bìa, giống trang nhóm hiện tại.
- Chưa có ảnh: ảnh bìa là một dải màu nhạt, ảnh đại diện là chữ cái đầu của tên (giống ô bình luận hiện nay).
- Bấm ảnh đại diện hoặc ảnh bìa thì xem phóng to (dùng `PhotoViewer`). Chính chủ thì có thêm menu "Đổi ảnh / Gỡ ảnh".
- `/ca-nhan` giữ các nút **Đổi mật khẩu**, **Chọn nhóm**, **Đăng xuất** ở cuối trang (hoặc trong menu ⋯).

## 3. Sửa thông tin

Bấm "Sửa trang cá nhân" thì mở Sheet (bottom sheet trên điện thoại):

| Trường | Ghi chú |
| --- | --- |
| Họ tên* | Như hiện tại, tối đa 120 ký tự |
| Giới thiệu | **Mới** — cột `users.bio` varchar(160) null, văn bản thuần (xem Q3) |
| Email | Như hiện tại, để lấy lại mật khẩu |
| Số điện thoại | Chỉ đọc, kèm dòng "Muốn đổi số, nhờ trưởng nhóm liên hệ quản trị WePo" |

Chỉ chính chủ sửa được thông tin và ảnh của mình. QTV không sửa hộ, vì một tài khoản có thể thuộc nhiều nhóm.

## 4. Ảnh đại diện và ảnh bìa

- Cột: dùng `users.avatar_path` (đã có) và thêm `users.cover_path`.
- Xử lý giống ảnh nhóm (`ImageStore`): xoay theo EXIF, bỏ EXIF/GPS, lưu webp chất lượng 80.
  - Ảnh đại diện: **cắt vuông 400px ở giữa ảnh**.
  - Ảnh bìa: **cắt 1600×533 (tỉ lệ 3:1) ở giữa ảnh**.
  - Bản đầu không có khung kéo/zoom để chọn vùng (xem Q4).
- Lưu ở đĩa `media`, thư mục `users/{user_id}/profile/YYYY/MM/{avatar|cover}-{ngẫu nhiên}.webp`. Tài khoản dùng chung nhiều nhóm nên không để trong `groups/{id}`.
- Phục vụ qua `GET /media/u/{userId}/{avatar|cover}/{tên-file}`. Ảnh công khai, cache 1 năm, `immutable`; tên file ngẫu nhiên nằm trên URL nên đổi ảnh là URL đổi theo. Mã nhóm dài ít nhất 3 ký tự nên không trùng với `u`.
- Đổi ảnh thì xóa file cũ. Gỡ ảnh thì xóa file và để cột về null.
- Giới hạn dung lượng và định dạng dùng chung cấu hình ảnh (`cashflow.images.mimes`, `limits.photo_upload_max_kb`).

## 5. Timeline của một người

Trong WePo, thành viên không tự đăng bài. Bài trên timeline là **phiếu thu chi** do QTV tạo. Vì vậy timeline của một người là:

- Các phiếu có `contact_id` = dòng danh bạ gắn với tài khoản đó trong nhóm (`group_members.contact_id`). Gồm cả phiếu thu (người đó đóng quỹ) lẫn phiếu chi (người đó được thăm hoặc nhận tiền).
- Chỉ lấy phiếu `show_on_timeline = true` và chưa bị xóa, giống timeline nhóm.
- **Không gộp** theo chiến dịch/ngày: mỗi phiếu là một thẻ `PostCard`, vẫn có tim và bình luận như timeline nhóm.
- Mới nhất trước, 20 thẻ một trang, cuộn vô hạn, chia mốc theo tháng. Dùng lại `TransactionPresenter`.
- Tài khoản chưa gắn với dòng danh bạ nào thì hiện "Chưa có hoạt động trong nhóm".

**Thẻ tóm tắt** trên đầu timeline: tổng đã đóng năm nay, số chiến dịch đã đóng trên tổng số phải đóng, các chiến dịch đang mở kèm trạng thái đã đóng/chưa đóng/được miễn. Những số này người xem timeline vốn đã thấy trong bảng đã đóng/chưa đóng, nên không lộ thêm thông tin gì.

## 6. Avatar hiện ở đâu, bấm tên ra trang cá nhân

Tạo component `UserAvatar` (ảnh tròn; chưa có ảnh thì hiện chữ cái đầu) và dùng ở:

| Chỗ | Thay đổi |
| --- | --- |
| Thanh trên trang nhóm và menu quản trị | Thay icon `UserRound` bằng avatar, bấm vào ra `/ca-nhan` |
| Bình luận dưới phiếu | Avatar thay ô chữ cái; bấm avatar hoặc tên ra trang thành viên |
| Trang Tài khoản thành viên (quản trị) | Avatar cạnh tên |
| Bảng đã đóng/chưa đóng của chiến dịch | Avatar với người đã có tài khoản; bấm tên ra trang thành viên |
| Thẻ phiếu trên timeline | Tên người đóng/người nhận bấm được nếu người đó đã có tài khoản |

API trả thêm cho mỗi người: `avatar_url` và `member_id` (để dựng link). `CommentPresenter.user` đổi từ chuỗi tên sang object `{ name, avatar_url, member_id }`.

## 7. API

| Method | Đường dẫn | Ghi chú |
| --- | --- | --- |
| GET | `/api/me` | Thêm `avatar_url`, `cover_url`, `bio` |
| PUT | `/api/me` | Thêm `bio` |
| POST | `/api/me/images` | `kind` = avatar\|cover, `image` (file). Trả về MeResource |
| DELETE | `/api/me/images/{kind}` | Gỡ ảnh |
| GET | `/api/groups/{code}/members/{memberId}` | Hồ sơ + thẻ tóm tắt. Kiểm quyền bằng `GroupViewer::canView()` |
| GET | `/api/groups/{code}/members/{memberId}/timeline?page=` | Timeline của người đó |

Upload ảnh giới hạn 10 lần/phút/người.

## 8. Database

Migration mới `add_profile_columns_to_users_table`:

| Cột | Kiểu |
| --- | --- |
| cover_path | varchar null |
| bio | varchar(160) null |

Cập nhật bảng `users` ở plan/01.

## 9. Kiểm thử

- Tải ảnh đại diện/ảnh bìa: đúng kích thước, đúng định dạng webp, xóa file cũ, gỡ ảnh, file sai định dạng bị từ chối.
- `/media/u/...`: sai tên file thì 404, đúng thì có header cache.
- Trang thành viên: nhóm private mà khách chưa đăng nhập thì không xem được; người ở nhóm khác không xem được; thành viên đã rời nhóm thì 404 trừ với QTV; không có SĐT/địa chỉ trong JSON trả về.
- Timeline của một người chỉ có phiếu của người đó, không có phiếu đang ẩn khỏi timeline hoặc đã xóa.

## 10. Các mốc code

| Mốc | Nội dung | Dừng cho duyệt |
| --- | --- | --- |
| P1 | Migration, upload/gỡ ảnh, route `/media/u`, `MeResource`, test | ✓ |
| P2 | Component `UserAvatar`, làm lại `/ca-nhan` (ảnh bìa, ảnh đại diện, Sheet sửa thông tin) | ✓ (kèm ảnh chụp) |
| P3 | Trang `/{ma-nhom}/thanh-vien/{id}`: API hồ sơ, timeline, thẻ tóm tắt | ✓ (kèm ảnh chụp) |
| P4 | Gắn avatar và link trang thành viên ở bình luận, thanh trên, danh sách tài khoản, bảng đã đóng | ✓ |

Tiến độ 10/11/2026: xong P1. Làm trước P3 và phần link của P4 theo yêu cầu "mỗi tên thành viên bấm vào đều ra trang cá nhân": tên ở thẻ phiếu, thẻ gộp đóng quỹ, bình luận (kèm avatar), bảng đã đóng/chưa đóng, trang Tài khoản và Danh bạ của quản trị. Tên người chưa có tài khoản vẫn là chữ thường. Sau đó xong P2: `/ca-nhan` có ảnh bìa, ảnh đại diện (menu Xem/Đổi/Gỡ), Sheet sửa thông tin, hàng nút chọn nhóm để xem hoạt động của mình ngay trên trang.

## 10b. Lối vào "trang của tôi" từ timeline (bổ sung 10/11/2026)

Vấn đề: người đang ở timeline nhóm đăng nhập xong chỉ có icon dẫn tới `/ca-nhan` (form sửa thông tin); QTV thì chỉ thấy nút "Quản trị". Không có đường đi thẳng tới trang `/{ma-nhom}/thanh-vien/{id}` của chính mình, trừ khi tự tìm tên mình trên một phiếu.

Thay đổi:

| Chỗ | Trước | Sau |
| --- | --- | --- |
| `viewer` trong `GET /api/g/{code}` | Không có id thành viên | Thêm `member_id` (`group_members.id`, null nếu không thuộc nhóm) |
| `GET /api/me` → `groups[]` | | Thêm `member_id` cho từng nhóm |
| Đầu trang nhóm, khách | Nút "Đăng nhập" | Giữ nguyên. Đăng nhập xong quay về timeline |
| Đầu trang nhóm, thành viên | Icon `UserRound` → `/ca-nhan` | **Ảnh đại diện của tôi** → `/{ma-nhom}/thanh-vien/{member_id}` |
| Đầu trang nhóm, QTV | Chỉ nút "Quản trị" | Nút "Quản trị" **và** ảnh đại diện → trang của tôi |
| Đầu trang nhóm, đăng nhập nhưng không thuộc nhóm | Icon → `/ca-nhan` | Ảnh đại diện → `/ca-nhan` |
| `/ca-nhan` | Không có link | Menu ⋯ → "Xem như người khác" → `/{ma-nhom}/thanh-vien/{member_id}` của nhóm đang chọn (bỏ thẻ "Trang của tôi" ngày 10/11/2026) |
| Trang quản trị | Icon + tên ở cuối thanh bên → `/ca-nhan` | Ảnh đại diện + tên **ở góc phải thanh trên** (điện thoại chỉ có ảnh) → trang của tôi trong nhóm. Bỏ khỏi thanh bên. Tab "Thêm" vẫn có "Cá nhân, đổi mật khẩu" |
| Trang chọn nhóm | Icon `UserRound` | Ảnh đại diện (vẫn → `/ca-nhan`) |

Luồng của một người: timeline → "Đăng nhập" → về lại timeline → bấm ảnh đại diện góc phải → trang của tôi → "Sửa trang cá nhân" → `/ca-nhan`.

Kiểm thử: `viewer.member_id` đúng với thành viên, null với khách và người nhóm khác; `/api/me` có `groups.*.member_id`.

Trên điện thoại, nút "Quản trị" chỉ còn icon ⚙ để tên nhóm không bị cắt quá ngắn.

Tiến độ 10/11/2026: đã làm xong mục này (avatar ở thanh trên của P4 cũng xong theo).

## 10c. Giao diện phần đầu trang cá nhân (chốt 10/11/2026)

- Ảnh bìa 3:1 mờ dần xuống nền trắng; chưa có ảnh thì nền xanh có sóng và cành lá. Avatar tròn viền trắng, tên và dòng "vai trò | nhóm" đè lên phần mờ, căn giữa theo chiều dọc với avatar. Không nhắc số đã đóng ở phần đầu (đã có trong thẻ Giới thiệu).
- Trang thành viên: nút tròn biểu tượng bút (chỉ khi xem trang của mình) → `/ca-nhan`. Menu ⋯: chép link, chỉnh sửa trang cá nhân, về trang nhóm.
- `/ca-nhan`: nút tròn biểu tượng bánh răng mở menu xổ xuống "Thay đổi thông tin", "Chọn nhóm", "Đổi mật khẩu", "Đăng xuất". Nút đổi ảnh bìa là nút tròn nhỏ biểu tượng máy ảnh ở góc trên ảnh bìa. Menu ⋯: xem như người khác, đổi ảnh đại diện, đổi ảnh bìa. Bỏ thẻ "Trang của tôi" và thẻ tài khoản ở cuối trang.
- Bên dưới: cột trái "Giới thiệu", cột phải "Hoạt động" (điện thoại xếp dọc).
## 10d. Trang nhóm kiểu nhóm Facebook (chốt 10/11/2026)

- Nhóm **không có ảnh đại diện** (bỏ hẳn: Cài đặt nhóm chỉ còn ảnh bìa, API `/settings/images` chỉ nhận ảnh bìa, `GET /api/g/{code}` không còn `avatar_url`, ảnh xem trước khi dán link chỉ lấy ảnh bìa). Cột `groups.avatar_path` còn trong database nhưng không dùng. Phần đầu chỉ có ảnh bìa 3:1 rộng (bo góc dưới); chưa có ảnh bìa thì nền xanh sóng + cành lá.
- Tên nhóm lớn, "Nhóm Công khai/Riêng tư · N thành viên", hàng avatar thành viên (8 trên điện thoại, 12 trên máy tính, còn lại "+N"). `GET /api/g/{code}` trả thêm `members_preview` (tối đa 20: tên, `member_id`, `avatar_url`; người có ảnh trước, rồi người có tài khoản, rồi người chỉ có trong danh bạ), chỉ khi xem được nhóm. Bấm avatar người có tài khoản ra trang thành viên.
- Nút: khách "Đăng nhập"; "Chia sẻ"; (không có nút "Đóng quỹ" ở phần đầu, nút này nằm trong thẻ từng chiến dịch đang mở); QTV "Quản trị"; người đăng nhập có nút avatar ▾ (Trang của tôi, Chọn nhóm khác). "Trang của tôi" là lối duy nhất tới trang cá nhân: thành viên ra trang của mình trong nhóm (sửa bằng nút bút ở đó), người không thuộc nhóm ra `/ca-nhan`.
- Thanh tab: Dòng thời gian · Thống kê · Chiến dịch (chiến dịch đang mở mới nhất).
- Máy tính hai cột: dòng thời gian bên trái (bỏ khung lọc Tất cả/Thu/Chi/Loại ngày 10/11/2026; API timeline vẫn nhận type, category_id); Chiến dịch đang mở bên phải (dính khi cuộn). Điện thoại: Chiến dịch rồi mới tới dòng thời gian. Bỏ thẻ Giới thiệu (10/11/2026): công khai/riêng tư và số thành viên đã có ở phần đầu. Bỏ thẻ Số dư + biểu đồ khỏi trang nhóm (10/11/2026), xem ở tab Thống kê.

## 10e. Danh sách thành viên của nhóm (bổ sung 10/11/2026)

- Bấm "N thành viên" (hoặc "+N" cạnh hàng avatar) ở đầu trang nhóm ra `/{ma-nhom}/thanh-vien`.
- API `GET /api/g/{group}/members` (cùng quyền xem timeline): người đang tham gia trong danh bạ, cùng số với "N thành viên". Mỗi người chỉ có tên, ảnh đại diện, vai trò quản trị (Trưởng nhóm / Quản trị viên); không SĐT, ngày sinh, địa chỉ.
- Trang chia "Ban quản trị" và "Thành viên", tìm theo tên không dấu. Người có tài khoản bấm vào ra trang thành viên; người chỉ có trong danh bạ thì không bấm được.

## 11. Câu hỏi cần chốt

| # | Câu hỏi | Đề xuất |
| --- | --- | --- |
| Q1 | Timeline của một người gồm những gì? | Phiếu có người đó là người đóng/người nhận (mục 5). Không tính bình luận và tim của họ. |
| Q2 | Người trong danh bạ chưa có tài khoản có trang riêng không? | Bản đầu **không**. Sau này có thể làm trang rút gọn (chỉ tên và các phiếu, không có ảnh). |
| Q3 | Thêm ô "Giới thiệu" (bio) không? | **Có**, tối đa 160 ký tự. |
| Q4 | Ảnh tự cắt ở giữa hay cho người dùng kéo/zoom chọn vùng? | Bản đầu **tự cắt ở giữa**. Nếu cần chọn vùng thì làm sau, phải thêm thư viện cắt ảnh cho Vue. |
| Q5 | Ai xem được ảnh đại diện và ảnh bìa? | **Công khai qua link có tên file ngẫu nhiên**, giống ảnh nhóm. Khách xem nhóm public cũng thấy avatar trong bình luận. |
