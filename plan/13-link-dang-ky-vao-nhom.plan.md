# Link đăng ký vào nhóm

Ngày: 10/11/2026. Trạng thái: **đã duyệt, đã làm** (10/11/2026).

## Hiện tại

- Người mới chỉ vào được nhóm bằng một cách: trưởng nhóm/QTV thêm SĐT vào **Danh bạ** rồi bấm **Cấp TK** (plan/02 mục 4). Tài khoản cấp ra có mật khẩu tạm.
- `/register` chỉ tạo tài khoản, không gắn nhóm (plan/11). plan/11 để "tham gia nhóm bằng link" ở phần ngoài phạm vi; plan này làm phần đó.
- Không có khái niệm "chờ duyệt" cho thành viên. Tab **Chờ duyệt** hiện chỉ có Chuyển khoản (SePay) và Bill thành viên.

## Quyết định đã chốt

- Mỗi nhóm có link **`/{ma-nhom}/register`**, ví dụ `wepo.hipotech.vn/chi-doan-a13/register`.
- Đăng ký qua link: tạo tài khoản, **đăng nhập luôn**, đồng thời người đó xuất hiện trong **Danh bạ** của nhóm ở mục **Chờ duyệt**. Chưa là thành viên cho tới khi QTV duyệt.
- QTV duyệt **trong trang Danh bạ**.
- Duyệt xong: vai trò **Thành viên**, tự vào danh bạ. SĐT đã có trong danh bạ thì gắn vào dòng đó, chưa có thì tạo dòng mới. **Từ chối** thì không tạo gì trong nhóm; tài khoản WePo của người đó vẫn giữ.
- Link có **công tắc bật/tắt** trong Cài đặt nhóm, **mặc định bật**.

## SĐT đã có tài khoản WePo (đã duyệt cùng plan)

- **Đã đăng nhập** mà mở link: trang hiện tên nhóm và nút **"Xin vào nhóm"**. Bấm một lần là gửi yêu cầu chờ duyệt, không cần điền lại form.
- **Chưa đăng nhập**, điền form với SĐT đã có tài khoản: báo "Số điện thoại này đã có tài khoản", kèm nút **Đăng nhập để xin vào nhóm**. Nút này mở `/login?next=/{ma-nhom}/register`, đăng nhập xong quay lại đúng trang này để bấm "Xin vào nhóm".
- **Đã là thành viên** mà mở link: chuyển thẳng vào trang nhóm.

## Việc cần làm

### 1. Cơ sở dữ liệu (database `groups`)

- Bảng mới **`group_join_requests`**: `id`, `group_id`, `user_id`, `contact_id` (null), `status` (`pending` / `approved` / `rejected`), `decided_by` (null), `decided_at` (null), timestamps. Mỗi người chỉ có một yêu cầu `pending` mỗi nhóm.
  - Dùng bảng riêng thay cho thêm trạng thái `pending` vào `contacts` / `group_members`. Lý do: thêm danh bạ đang tự đưa người mới vào các chiến dịch thu đang mở, và khoảng 15 chỗ đang lọc `status = active`. Để người chờ duyệt ở bảng riêng thì không đụng tới các chỗ đó, không lo lọt người chưa duyệt vào chiến dịch hay báo cáo.
- `groups`: thêm cột **`join_link_enabled`** (bool, mặc định `true`).

### 2. API

| Endpoint | Việc |
| --- | --- |
| `GET /api/g/{group}/join` (khách) | Trả tên nhóm, ảnh bìa, link có bật không. Đã đăng nhập thì kèm trạng thái của mình: `member` / `pending` / `none`. |
| `POST /api/g/{group}/join/register` (khách) | Giống `/api/register` (họ tên, SĐT, email không bắt buộc, mật khẩu, honeypot, giới hạn chung với `/api/register`, xem plan/18). Tạo user, đăng nhập, tạo yêu cầu `pending`. Link đang tắt → 403. |
| `POST /api/g/{group}/join` (đã đăng nhập) | Người đã có tài khoản xin vào nhóm. Đã là thành viên → báo luôn, đã có yêu cầu chờ → trả yêu cầu đó, không tạo trùng. |
| `GET /api/g/{group}/admin/join-requests` (QTV) | Danh sách đang chờ: họ tên, SĐT, ngày gửi, có trùng SĐT với ai trong danh bạ không. |
| `POST .../join-requests/{id}/approve` (QTV) | Tìm dòng danh bạ theo SĐT, không có thì tạo (dòng mới tự vào chiến dịch đang mở như thêm danh bạ thường). Tạo hoặc kích hoạt lại `group_members` vai trò `member`, gắn `contact.user_id`. Phần này tách từ `AccountGranter` ra, bỏ bước tạo mật khẩu tạm vì người dùng đã tự đặt mật khẩu. |
| `POST .../join-requests/{id}/reject` (QTV) | Đánh dấu `rejected`, không đụng danh bạ. |
| Cài đặt nhóm | Thêm `join_link_enabled` vào API sửa cài đặt hiện có. |

Khi làm có mấy chỗ khác bản nháp:

- **Không ghi nhật ký** khi duyệt/từ chối: Cấp TK hiện cũng không ghi (`admin_audit_logs` là nhật ký của /system). Bảng `group_join_requests` đã lưu ai duyệt (`decided_by`) và lúc nào (`decided_at`).
- Bật/tắt link dùng endpoint riêng `PUT /admin/settings/join-link` (giống công tắc SePay), bấm công tắc là lưu luôn, không phải bấm "Lưu cài đặt".
- `GET /admin/join-requests` trả cả danh sách chờ lẫn trạng thái link (`{ requests, link }`) để trang Danh bạ gọi một lần.
- Số người chờ duyệt đi kèm `GET /admin/sepay/count` (trường `joins`), dùng chung lần gọi lấy số đỏ đã có.

### 3. Giao diện

**`/{ma-nhom}/register`** (trang mới, dùng `AuthLayout` có logo)

- Tiêu đề **"Tham gia {Tên nhóm}"**, dòng phụ "Tạo tài khoản WePo, quản trị viên nhóm sẽ duyệt cho bạn."
- Chưa đăng nhập: form như `/register`. Xong thì vào `/{ma-nhom}`.
- Đã đăng nhập: nút **"Xin vào nhóm"** như mục Đề xuất.
- Link đang tắt hoặc nhóm không tồn tại / bị khóa: báo "Nhóm này không nhận đăng ký qua link", không hiện form.
- Route khai báo trước route `/:group` trong `app.js`. `register` không thể là mã nhóm (đã nằm trong danh sách cấm), nên không trùng.

**Trang nhóm `/{ma-nhom}` khi đang chờ duyệt**

- Thay câu "Tài khoản của bạn không thuộc nhóm này…" bằng **"Bạn đã gửi yêu cầu vào nhóm. Đang chờ quản trị viên duyệt."** Nhóm công khai thì vẫn xem timeline như khách.

**`/groups` (chọn nhóm)**

- Thêm mục "Đang chờ duyệt" liệt kê các nhóm mình đã xin vào.

**Danh bạ (QTV)**

- Bộ lọc trạng thái thành **Đang tham gia · Chờ duyệt · Đã rời** (bỏ tab "Tất cả"), tab Chờ duyệt có số đỏ. Vào Danh bạ luôn mở tab **Đang tham gia**. *(Sửa 10/11/2026: ban đầu duyệt trong một khối ở đầu trang, nhiều người chờ thì khối quá dài, nên chuyển sang duyệt trong danh sách.)*
  - Mỗi dòng có họ tên, SĐT, ngày gửi, nút **Duyệt** và **Từ chối**. SĐT trùng người đã có trong danh bạ thì ghi "Trùng SĐT với {tên} trong danh bạ, duyệt sẽ gắn vào người này".
  - Nút **"Duyệt tất cả (N)"** khi có từ 2 người: hỏi xác nhận, gửi đúng các id đang thấy (`POST /admin/join-requests/approve`, người vừa xin vào sau đó không bị duyệt theo). Ai lỗi thì báo tên và lý do, những người còn lại vẫn được duyệt.
  - Ô tìm theo tên lọc luôn danh sách chờ (cả SĐT).
- Ở các tab khác, có người chờ thì hiện **khối nổi bật** "N người chờ duyệt vào nhóm" ở đầu trang, bấm vào sang tab Chờ duyệt.
- Thêm khối **"Link đăng ký vào nhóm"**: hiện link, nút **Sao chép** và **Chia sẻ** (Zalo…). Link đang tắt thì ghi rõ và dẫn sang Cài đặt nhóm.
- Mục **Danh bạ** trên sidebar / trang Thêm có số đỏ bằng số người chờ duyệt.

**Cài đặt nhóm**

- Công tắc **"Cho đăng ký qua link"**, kèm link để sao chép.

### 4. Tài liệu

- plan/02 mục 4: thêm cách vào nhóm thứ hai là qua link, có duyệt.
- plan/11 phần ngoài phạm vi: ghi đã chuyển sang plan/13.
- plan.md: thêm dòng plan 13 và route `/{ma-nhom}/register`.

### 5. Kiểm thử

- Đăng ký qua link: tạo user, đăng nhập, có yêu cầu `pending`. **Chưa** có `group_members`, không xem được nhóm riêng tư, chưa có trong chiến dịch.
- Link tắt → 403. Nhóm bị khóa → không đăng ký được.
- SĐT đã có tài khoản → lỗi `phone`. Đăng nhập rồi `POST /join` → có yêu cầu, gửi lại không tạo trùng.
- Duyệt: SĐT có sẵn trong danh bạ → gắn vào dòng đó. Chưa có → tạo dòng mới, vào chiến dịch đang mở. Thành viên đã bị gỡ trước đây → kích hoạt lại.
- Từ chối: không tạo danh bạ, không tạo thành viên.
- Thành viên thường không xem, không duyệt được yêu cầu.
- Chạy thử khổ điện thoại: mở link → đăng ký → thấy "Đang chờ duyệt" → QTV duyệt trong Danh bạ → người đó vào được nhóm.

## Ngoài phạm vi

- Báo cho QTV qua email/Zalo khi có người xin vào. Hiện chỉ có số đỏ trong app.
- Link có hạn dùng, hoặc link riêng cho từng người.
- Duyệt tự động.
- Chọn vai trò Quản trị viên lúc duyệt (cứ duyệt là Thành viên, đổi vai trò sau ở Tài khoản & vai trò).
