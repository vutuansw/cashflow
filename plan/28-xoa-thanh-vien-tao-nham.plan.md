# Xóa được thành viên tạo nhầm

Ngày: 12/11/2026. Trạng thái: **đã duyệt** (12/11/2026), giữ xóa hai bước (thu hồi → xóa). Đã code xong, test qua, chưa commit, chưa deploy.

## Vấn đề

Thêm nhầm một người vào Danh bạ rồi bấm "Xóa khỏi danh bạ" thì bị báo *"Người này đã có tài khoản hoặc lịch sử thu chi. Đánh dấu Đã rời nhóm thay vì xóa."*, dù người đó chưa đóng đồng nào.

Nguyên nhân, đọc trong code:

1. **Chiến dịch đang mở tự thêm người mới vào danh sách phải đóng.** `Contact::created` (plan/05 mục 1) tạo dòng `campaign_members` cho mọi chiến dịch đang mở. Còn `ContactController::destroy` coi *có dòng `campaign_members`* là "đã có lịch sử", nên chặn. Kết quả: nhóm nào đang có chiến dịch mở thì **không xóa được bất kỳ ai vừa thêm**. Đây là trường hợp gặp nhiều nhất.
2. **Đã cấp tài khoản rồi thu hồi vẫn không xóa được.** Thu hồi (`MemberController::destroy`) chỉ đổi `group_members.status` thành `removed`, còn `contacts.user_id` vẫn giữ. Trang Danh bạ lúc này hiện lại nút Xóa (vì không còn tài khoản đang hoạt động) nhưng server vẫn chặn vì `user_id` khác rỗng. Người dùng bấm thu hồi theo hướng dẫn mà vẫn bế tắc.

## Đề xuất

### 1. Thế nào là "đã có lịch sử" (vẫn chặn xóa)

Chỉ chặn khi xóa sẽ làm mất hoặc làm sai dữ liệu tiền:

- Có giao dịch thu/chi gắn với người này (kể cả giao dịch đã xóa mềm), **hoặc**
- Có báo đóng tiền (`payment_claims`) của người này, bất kể trạng thái, **hoặc**
- Đang có tài khoản **hoạt động** trong nhóm (`group_members.status = active`).

**Không** còn coi là lịch sử:

- Dòng `campaign_members` (có trong danh sách phải đóng nhưng chưa đóng). Xóa người thì dòng này tự xóa theo (khóa ngoại đã `cascadeOnDelete`). Kể cả dòng admin đã chỉnh số tiền riêng hay đánh dấu miễn: chưa có tiền thật nên mất cũng không sao.
- `contacts.user_id` còn trỏ tới tài khoản đã thu hồi.

### 2. Lời báo khi bị chặn: nói rõ lý do và việc cần làm

| Lý do | Lời báo |
|---|---|
| Có giao dịch hoặc báo đóng tiền | "{Tên} đã có lịch sử thu chi nên không xóa được. Đánh dấu "Đã rời nhóm" để ẩn khỏi các chiến dịch mới." |
| Có tài khoản đang hoạt động | "{Tên} đang có tài khoản trong nhóm. Thu hồi tài khoản ở trang Tài khoản & vai trò trước, rồi xóa." |

### 3. Xóa xong thì

- Xóa cache số liệu nhóm (`GroupStats::forget`), vì danh sách phải đóng của chiến dịch đã đổi (giống lúc thêm người).
- Các bản ghi trỏ tới người này (yêu cầu tham gia nhóm, tài khoản đã thu hồi) tự để trống `contact_id` theo khóa ngoại đã có, không phải sửa gì.
- Tài khoản đăng nhập (bảng `users`) **không xóa**: có thể người đó còn ở nhóm khác.

### 4. Giao diện Danh bạ

- Giữ nguyên chỗ đặt nút "Xóa khỏi danh bạ" (chỉ hiện khi không có tài khoản đang hoạt động).
- Câu hỏi xác nhận nói thêm nếu có chiến dịch: "Xóa {tên} khỏi danh bạ? Người này cũng bị bỏ khỏi danh sách phải đóng của các chiến dịch." (Không cần hỏi server, chỉ thêm câu này cố định.)
- Khi bị chặn: hiện lời báo ở mục 2 như hiện nay (`toast` lỗi).

## Câu hỏi cần chốt

1. **Người đã có tài khoản nhưng chưa từng đăng nhập** (cấp nhầm tài khoản): có muốn nút Xóa ở Danh bạ làm luôn một bước (tự thu hồi tài khoản rồi xóa) không? Đề xuất: **chưa làm**, giữ hai bước (thu hồi → xóa) cho rõ ràng; lời báo ở mục 2 chỉ đường.

## Cách làm

- `app/Http/Controllers/GroupAdmin/ContactController.php` → `destroy`: đổi điều kiện chặn theo mục 1, hai lời báo theo mục 2, `GroupStats::forget` sau khi xóa.
- `resources/js/pages/admin/ContactsPage.vue` → `remove`: sửa câu xác nhận theo mục 4.
- `tests/Feature/GroupAdmin/ContactsTest.php`: thêm test
  - xóa được người vừa thêm khi đang có chiến dịch mở (dòng `campaign_members` bị xóa theo);
  - xóa được người đã thu hồi tài khoản;
  - vẫn chặn khi có giao dịch, có báo đóng tiền, hoặc tài khoản đang hoạt động.

Một mốc, làm xong chạy test rồi báo lại.
