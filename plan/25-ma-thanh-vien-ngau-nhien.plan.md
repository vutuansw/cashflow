# Bỏ id MySQL khỏi link trang thành viên

Ngày: 11/11/2026. Trạng thái: **đã duyệt** (11/11/2026, chọn mã ngẫu nhiên), đã code xong mốc 1 và 2.

## Hiện tại

Trang thành viên là `/{ma-nhom}/members/{id}`, ví dụ `/chi-doan-a13/members/7`. Số `7` là `group_members.id`, khóa chính tự tăng trong MySQL. Số này còn nằm ở API `/api/g/{ma-nhom}/members/{id}` và ở trường `member_id` trong mọi JSON có tên người (bình luận, bài đăng, phiếu thu chi, top đóng góp, danh sách thành viên, hàng avatar, `/me`).

Server đã chặn xem chéo nhóm (`member()` lọc theo `group_id`), nên không xem được người nhóm khác. Nhưng số tự tăng vẫn nguy hiểm:

- **Dò được**: đổi `7` thành `6`, `8`… là lướt hết thành viên, kể cả người không hiện trong danh sách (vd. đã rời nhóm nếu người xem là quản trị).
- **Lộ quy mô hệ thống**: id `7` ở nhóm này, tuần sau thấy `5000` là biết toàn hệ thống có bao nhiêu lượt vào nhóm, tăng nhanh cỡ nào.
- **Lộ thứ tự**: id nhỏ là người vào sớm.
- Lỡ sau này có chỗ quên lọc `group_id` thì id tuần tự là cửa đầu tiên bị khai thác.

## Đề xuất

### 1. Mỗi thành viên có một mã ngẫu nhiên

Thêm cột `group_members.handle`: chuỗi ngẫu nhiên **10 ký tự chữ thường và số** (vd. `k7m2x9qa4f`), duy nhất toàn bảng, sinh lúc tạo dòng và **không bao giờ đổi**.

- 36^10 ≈ 3,6 triệu tỷ khả năng: không đoán, không dò được, không lộ số lượng hay thứ tự.
- Không dính tới tên nên đổi tên không làm gãy link đã chia sẻ.
- Link mới: `/chi-doan-a13/members/k7m2x9qa4f`.

Cách khác đã cân nhắc, không chọn:

| Cách | Vì sao không |
|---|---|
| Slug theo tên (`/members/nguyen-van-an`) | Trùng tên phải thêm số đuôi (`-2`, `-3`) là dò lại được; đổi tên thì link đổi hoặc lệch tên |
| Hashids (mã hóa id) | Vẫn là id tuần tự đội lốt, lộ khóa là giải ngược được hết |
| UUID | Dài 36 ký tự, link xấu khi gửi Zalo |
| Số thứ tự trong nhóm (1, 2, 3…) | Vẫn dò được như cũ |

### 2. Đổi khắp nơi `member_id` → `member_handle`

- API: `/api/g/{ma-nhom}/members/{handle}` và `/members/{handle}/timeline`. Bỏ `whereNumber`, chỉ nhận đúng dạng 10 ký tự `[a-z0-9]`.
- JSON: mọi chỗ đang trả `member_id` (MemberIndex, GroupViewer, MeResource, các Presenter, TimelineFeed, CampaignPageController, ContactController, MemberProfileController) đổi thành `member_handle`. Không còn trả `group_members.id` ra trang công khai nữa.
- Frontend: route `/:group/members/:handle([a-z0-9]{10})`, các link (`MemberName`, `PostComments`, `StatusCard`, `GroupHeader`, `GroupMembersPage`, `AdminShell`, `GroupHomePage`, `MorePage`, `ContactsPage`, `session.js`) dùng `member_handle`.

### 3. Link cũ dạng số

Link `/…/members/7` đã gửi trong nhóm chat sẽ **không** được chuyển sang trang người đó (nếu chuyển thì vẫn dò được bằng số). Thay vào đó tự chuyển về **danh sách thành viên** `/{ma-nhom}/members`, người xem chọn lại tên. API dạng số trả 404.

### 4. Ngoài phạm vi lần này

- API quản trị `/admin/members/{memberId}` (đổi vai trò, cấp lại mật khẩu, xóa) vẫn dùng id số: chỉ quản trị nhóm gọi được, đã lọc theo nhóm, không hiện trên thanh địa chỉ. Có thể đổi sau cho đồng bộ.
- Các trang khác có id số trên URL đều là trang quản trị đã kiểm quyền: `/{ma-nhom}/admin/campaigns/{id}`, `/system/groups/{id}`. Trang chiến dịch công khai đã dùng slug.

## Cách làm

- Migration: thêm `handle` (string 10, unique, nullable lúc đầu), điền mã cho mọi dòng đang có, rồi đổi thành not null.
- `GroupMember`: sự kiện `creating` tự sinh mã (`Str::random` lọc còn `[a-z0-9]`, thử lại nếu trùng). Mọi chỗ tạo dòng (`GroupCreator`, duyệt yêu cầu vào nhóm, thêm thành viên, `DemoSeeder`) tự có mã, không phải sửa từng chỗ.
- `MemberProfileController::member()` tìm theo `handle` + `group_id`.
- Sửa test đang dùng `$m->id` trong URL; thêm test: link số trả 404, mã nhóm A không mở được thành viên nhóm B, dòng mới luôn có mã.

## Mốc

1. Backend: migration, model, API, JSON đổi sang `member_handle`, test. Kiểm tra: `php artisan test` xanh, `/api/g/{ma}/members/7` trả 404.
   - **Xong** 11/11/2026: 201 test xanh. Migration chạy trên DB local: 28 dòng đều có mã, không trùng. Trang thành viên trả `handle`, không còn `id`.
2. Frontend: route, mọi link, chuyển hướng link số cũ. Kiểm tra trên trình duyệt: bấm tên ở bình luận, bài đăng, top đóng góp, hàng avatar, danh sách thành viên, "Trang của tôi" đều mở đúng người; mở `/chi-doan-a13/members/7` về danh sách thành viên.
   - **Xong** 12/11/2026: build sạch, không còn `member_id` trong frontend. Trên trình duyệt (chưa đăng nhập, nhóm public): 13 link tên ở trang nhóm và các link ở danh sách thành viên đều dạng mã; bấm vào mở đúng trang và timeline (API 200); `/…/members/2` chuyển về danh sách thành viên; không lỗi console. Các link khi đã đăng nhập (Trang của tôi, Thêm, Danh bạ) chưa bấm thử trên trình duyệt, chỉ có test API `/me` và `viewer` trả `member_handle`.
