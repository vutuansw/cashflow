# Gộp trang cá nhân làm một

Ngày: 11/11/2026. Trạng thái: **đã duyệt** (11/11/2026), đã code xong mốc 1 và 2.

## Hiện tại

Cùng một người có hai trang gần như giống hệt nhau:

- **Trang thành viên** `/{ma-nhom}/members/{id}` (plan/08): ảnh bìa, avatar, tên, Giới thiệu, Hoạt động trong nhóm. Ai trong nhóm cũng xem được. Nếu là trang của mình thì có nút bút, bấm vào chuyển sang `/profile`.
- **Trang cá nhân** `/profile`: y như trên, cộng thêm nút máy ảnh đổi ảnh bìa và ảnh đại diện, bánh răng (Thay đổi thông tin, Chọn nhóm, Đổi mật khẩu, Đăng xuất), nút "Thêm lời giới thiệu", hàng chọn nhóm.

Người dùng vào trang của mình rồi phải bấm bút sang trang thứ hai mới sửa được. Thừa một bước, lại dễ nhầm hai trang là một.

## Đề xuất

### 1. Chỉ còn trang thành viên

`/{ma-nhom}/members/{id}` là trang duy nhất. Khi người xem **chính là chủ trang** thì trang có thêm các nút sửa, sửa xong thấy ngay tại chỗ:

| Chỗ | Người khác xem | Chủ trang xem |
|---|---|---|
| Ảnh bìa | Bấm để phóng to | Nút máy ảnh góc trên phải. Bấm ảnh: chưa có thì chọn ảnh luôn; có rồi thì menu Xem / Chọn ảnh khác / Gỡ |
| Ảnh đại diện | Bấm để phóng to | Nút máy ảnh góc dưới phải, bấm như ảnh bìa |
| Nút cạnh tên | Không có | Bánh răng: Thay đổi thông tin, Chọn nhóm, Đổi mật khẩu, Đăng xuất (thay cho nút bút hiện nay) |
| Giới thiệu | Chỉ hiện lời giới thiệu nếu có | Chưa có thì hiện nút "Thêm lời giới thiệu" |
| Ô "Bạn đang nghĩ gì?" | Không có | Có (như hiện nay, nếu nhóm cho thành viên đăng bài) |
| Menu ⋯ | Chép link, Về trang nhóm | Chép link, Về trang nhóm |

- Sheet "Sửa thông tin" (họ tên, giới thiệu, email, số điện thoại chỉ xem) giữ nguyên như ở `/profile`.
- Đổi tên, ảnh hay lời giới thiệu xong thì phần đầu trang và thẻ Giới thiệu cập nhật ngay, không cần tải lại.
- Bỏ mục **"Xem như người khác"**: trang đã là trang người khác thấy, chỉ thêm vài nút sửa.

### 2. Bỏ trang `/profile`

Dòng hoạt động của một người chỉ xem được trong nhóm, bởi người trong nhóm đó. Nên không cần trang cá nhân đứng ngoài nhóm nữa: **xóa `ProfilePage`**.

- Địa chỉ `/profile` (và link cũ `/ca-nhan`) chỉ còn để không gãy link đã lưu: tự chuyển sang trang của mình ở nhóm vào gần nhất (nhóm nhớ trong máy, không có thì nhóm đầu danh sách). Chưa thuộc nhóm nào thì chuyển về trang chọn nhóm.
- Người chưa thuộc nhóm nào: không có trang cá nhân. Vào nhóm rồi mới có.

### 3. Người thuộc nhiều nhóm

Bỏ hàng nút chọn nhóm. Muốn xem trang mình ở nhóm khác: bánh răng → **Chọn nhóm** → vào nhóm đó → "Trang của tôi".

### 4. Link "Trang của tôi"

- Trang nhóm, khung quản trị: đã trỏ thẳng trang của mình trong nhóm, giữ nguyên.
- Trang "Thêm" của quản trị (điện thoại): mục "Cá nhân, đổi mật khẩu" đổi thành **"Trang của tôi"**, trỏ trang của mình trong nhóm đang quản trị.
- Trang chọn nhóm: avatar góc trên đang trỏ `/profile`. Bỏ link này, chỉ để avatar làm hình (trang chọn nhóm chưa gắn với nhóm nào). Nút đăng xuất cạnh đó giữ nguyên.
- Nút bút và mục "Chỉnh sửa trang cá nhân" trong menu ⋯ của trang thành viên: bỏ (đã sửa được ngay tại trang).

## Cách làm

- Tách phần sửa (menu ảnh, tải ảnh, gỡ ảnh, sheet Sửa thông tin, menu bánh răng) ra component dùng chung, gắn vào trang thành viên khi là chủ trang.
- Trang thành viên: khi là chủ trang thì lấy tên, ảnh, lời giới thiệu từ tài khoản đang đăng nhập để sửa xong là thấy ngay.
- Không đổi API, không đổi cơ sở dữ liệu.

## Mốc

1. Phần sửa gắn vào trang thành viên khi là chủ trang. Kiểm tra: đổi ảnh bìa, ảnh đại diện, gỡ ảnh, sửa tên và lời giới thiệu ngay trên trang thành viên.
2. Xóa `ProfilePage`, `/profile` tự chuyển, sửa các link. Kiểm tra: vào `/profile` khi có một nhóm, nhiều nhóm, không nhóm.
