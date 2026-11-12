# Nút Tham gia nhóm, QTV thêm thành viên bằng SĐT

Ngày: 12/11/2026. Trạng thái: **đã duyệt** (12/11/2026). M0 và M1 đã code xong, đã kiểm tra trên trình duyệt; chưa commit, chưa deploy.

Khi làm M1: SĐT chưa có tài khoản nhưng đã có trong Danh bạ thì **không bắt gõ họ tên**, lấy tên ở dòng danh bạ đó.

Khi làm M0: **bỏ** nút "Tham gia nhóm" ở hàng nút cạnh Chia sẻ, chỉ giữ nút trong dải mời. Lý do: trên điện thoại nút đè lên hàng avatar; trên máy tính nó lặp lại nút trong dải mời ngay bên dưới (chủ dự án báo trùng nút, 12/11/2026).

## Quyết định đã chốt (12/11/2026)

- **Không** cho thành viên tự tìm nhóm theo SĐT. Muốn vào nhóm thì ban quản trị gửi **link nhóm**, người dùng mở link và bấm **Tham gia nhóm**. Bỏ hẳn ý tưởng trang `/groups/find` của bản nháp trước.
- QTV được tìm theo SĐT để thêm trực tiếp. SĐT chưa có tài khoản WePo thì **tạo luôn tài khoản với mật khẩu tạm**, giống Cấp TK.

## 0. Lỗi thật: đăng ký "qua link nhóm" mà không vào nhóm (kiểm tra 12/11/2026)

Có người phản ánh đã đăng ký qua link nhóm mà không thuộc nhóm nào. Mình đã đối chiếu log nginx và database trên server, chỉ đọc. Có **2 người** bị kẹt. Cả hai đều **chưa từng vào** trang `/{ma-nhom}/register`.

| Người | Đường đi thật | Vì sao không vào nhóm |
|---|---|---|
| #31 (10:34) | Mở **trang nhóm** `/chi-doan-a12-2005-2008` từ Zalo → **Đăng nhập** → **Tạo tài khoản** → `/register?next=/chi-doan-a12…` | Trang đăng ký chung chỉ tạo tài khoản rồi quay về trang nhóm. Với nhóm công khai, người đã đăng nhập mà chưa là thành viên **không thấy nút xin vào** (nút này hiện chỉ có ở khối "Nhóm riêng tư"). Đến giờ vẫn chưa có yêu cầu vào nhóm. |
| #33 Trần Văn Sơn (12:33) | Mở thẳng `/register` từ Zalo. Tức là link người này nhận được là **link đăng ký chung**, không phải link nhóm | Tạo tài khoản xong thì ra trang chọn nhóm "Bạn chưa thuộc nhóm nào". 12:50 người này mở đúng link nhóm, bấm Xin vào, hiện **đang chờ QTV duyệt** ở Danh bạ. |

Trang `/{ma-nhom}/register` chạy đúng. Lỗi nằm ở những lối đi vòng không dẫn về link nhóm.

## 1. Nút Tham gia nhóm (M0, sửa lỗi trên)

Chỉ hiện khi nhóm **đang bật link đăng ký**. Mọi nút đều dẫn tới `/{ma-nhom}/register`: chưa đăng nhập thì ở đó có form tạo tài khoản, đã đăng nhập thì có nút "Xin vào nhóm".

1. **Trang nhóm công khai**, với khách và người đã đăng nhập mà chưa là thành viên:
   - nút **"Tham gia nhóm"** ở góc phải đầu trang, cạnh nút Đăng nhập;
   - một dải mời phía trên dòng thời gian: "Bạn chưa ở trong nhóm này." kèm nút **Tham gia nhóm**.
   - Đang chờ duyệt thì giữ dải "Đang chờ quản trị viên duyệt" như hiện nay, không hiện nút.
2. **Trang nhóm riêng tư**: đã có nút "Xin vào nhóm", đổi chữ thành **"Tham gia nhóm"** cho thống nhất.
3. **Trang Đăng nhập** mở từ một trang nhóm (`next=/{ma-nhom}…`): link "Tạo tài khoản" dẫn tới `/{ma-nhom}/register` thay cho `/register`. Nhóm tắt link thì trang đó đã tự báo "Nhóm này không nhận đăng ký qua link".
4. **Trang chọn nhóm** khi chưa có nhóm: đổi chữ thành "Bạn chưa thuộc nhóm nào. Nhờ ban quản trị gửi **link nhóm**, mở link rồi bấm **Tham gia nhóm**. Hoặc tạo nhóm mới." Trường hợp như #33 là nhận nhầm link đăng ký chung, câu này chỉ cho họ phải làm gì.
5. **Trang đăng ký chung** `/register`: thêm một dòng nhỏ dưới tiêu đề: "Muốn vào nhóm có sẵn? Mở link nhóm do ban quản trị gửi."
6. **Dữ liệu**: không sửa tay. #33 đã có yêu cầu chờ duyệt. #31 thì QTV gửi lại đúng link nhóm, hoặc thêm bằng SĐT (phần 2).

API: không cần endpoint mới. Trang nhóm đã có `viewer.join_link_enabled`, `viewer.join_pending` và `viewer.is_member`.

## 2. QTV thêm thành viên bằng SĐT (M1)

### Giao diện (trang Danh bạ)

Thêm nút **"Thêm bằng SĐT"** cạnh "Thêm người" và "Nhập nhanh". Bấm thì mở sheet có hai bước.

**Bước 1:** ô SĐT, nút **Tìm**.

**Bước 2:** hiện theo kết quả tìm.

| Kết quả | Hiện gì | Nút |
|---|---|---|
| Đã là thành viên nhóm | "{Tên} đã ở trong nhóm." | Không có |
| Có tài khoản WePo, chưa ở nhóm | Ảnh đại diện, tên, "Đã có tài khoản WePo" | **Thêm vào nhóm** |
| Chưa có tài khoản | "Số này chưa có tài khoản WePo." kèm ô **Họ tên** (bắt buộc) | **Thêm và cấp tài khoản** |

- SĐT đã có trong Danh bạ (chưa cấp TK): báo thêm "Đã có trong danh bạ: {tên}", rồi gắn tài khoản vào đúng dòng đó, không tạo dòng mới.
- Thêm xong:
  - người có sẵn tài khoản: hiện toast "Đã thêm {tên} vào nhóm.";
  - tài khoản mới: hiện thẻ mật khẩu tạm kèm lời nhắn gửi Zalo (`TempPasswordCard`, giống Cấp TK).
- Vai trò luôn là **Thành viên**. Muốn phong QTV thì làm ở trang Tài khoản & vai trò như hiện nay.
- Người được thêm **không cần đồng ý**. Lần mở app sau, họ sẽ thấy nhóm. App chưa có thông báo.

### API

| Endpoint | Việc |
|---|---|
| `GET /api/g/{group}/admin/members/lookup?phone=...` | Trả `status`: `member` / `account` / `none`. Có tài khoản thì kèm `name`, `avatar_url`. SĐT đã có trong danh bạ thì kèm `contact` (id, tên). Giới hạn 30 lần/phút. |
| `POST /api/g/{group}/admin/members/by-phone` `{phone, full_name?}` | Tìm dòng danh bạ theo SĐT; chưa có thì tạo, lấy tên từ tài khoản (hoặc từ `full_name` khi chưa có tài khoản). Rồi gọi `AccountGranter::grant(..., Member)`, trả kết quả giống `POST /admin/members` (`member`, `temp_password`, `share_message`). Người đó đang có yêu cầu xin vào chờ duyệt thì đánh dấu `approved`. |

- Tạo danh bạ và cấp tài khoản chạy chung một transaction, để không còn dòng danh bạ thừa khi lỗi giữa chừng.
- Người từng ở nhóm rồi bị thu hồi: `AccountGranter` đã tự bật lại dòng `group_members` cũ.
- Dòng danh bạ "Đã rời" thì bật lại thành đang hoạt động, giống `JoinRequests::approve`.

## 3. Không làm lần này

- Thành viên tự tìm nhóm theo SĐT, tên hoặc mã nhóm (đã chốt là không làm).
- Gửi lời mời để người được thêm tự bấm đồng ý.
- Thông báo (Zalo, push) khi được thêm hoặc khi yêu cầu được duyệt.

## Cách làm

- **M0**
  - `GroupHomePage.vue`: nút và dải Tham gia nhóm.
  - `LoginPage.vue`: link Tạo tài khoản đi theo `next`.
  - `GroupPickerPage.vue`, `RegisterPage.vue`: đổi chữ.
- **M1**
  - `MemberController`: thêm `lookup` và `storeByPhone`. Phần dùng chung để trong service `App\Services\Accounts\PhoneMemberAdder`.
  - `JoinRequests`: thêm hàm đánh dấu `approved` mà không tạo lại danh bạ.
  - `ContactsPage.vue`: nút và sheet "Thêm bằng SĐT".
  - Test trong `tests/Feature/GroupAdmin/MembersTest.php`:
    - người có sẵn tài khoản thì mật khẩu giữ nguyên;
    - người chưa có tài khoản thì ra mật khẩu tạm;
    - SĐT đã có trong danh bạ thì không tạo dòng trùng;
    - đã là thành viên thì báo lỗi;
    - yêu cầu xin vào đang chờ thì được đánh dấu đã duyệt;
    - QTV nhóm khác không gọi được.

Hai mốc, dừng sau mỗi mốc cho bạn duyệt. M0 sửa lỗi đang gặp nên làm trước.
