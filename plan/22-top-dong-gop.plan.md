# Top đóng góp

Ngày: 11/11/2026. Trạng thái: **đã duyệt** (11/11/2026), đang code.

## Hiện tại

- Trang nhóm `/{ma-nhom}`, cột phải (máy tính) có thẻ "Chiến dịch đang mở" rồi box "Nội quy" (plan/15).
- Chiến dịch có số tiền mặc định mỗi người (`amount_per_member`, sửa riêng từng người được bằng `campaign_members.expected_amount`), hoặc để trống là **tùy tâm**.
- Nhiều người đóng hơn mức mặc định (nhóm Chi Đoàn A12 mức 200.000 đ, Nguyễn Thị Hoa đóng 500.000 đ) nhưng trang nhóm không ghi nhận gì.
- Có người đóng nhiều vì **đóng bù** năm trước (mức 200.000 đ/năm, năm trước chưa đóng nên năm nay đóng 400.000 đ). Người này không phải đóng góp thêm, không nên lên top.

## Đề xuất

### 1. Box "Top đóng góp"

- **Vị trí:** ngay dưới box Nội quy.
  - **Máy tính:** cột phải, dưới Nội quy, trên chân trang.
  - **Điện thoại:** dưới Nội quy, trên dòng thời gian.
- **Phạm vi:** chiến dịch đang mở **mới nhất**, tức chiến dịch đang hiện ở thẻ phía trên. Nhóm không có chiến dịch đang mở thì không có box.
- **Nội dung:**

```
┌────────────────────────────────────┐
│ 🏆 Top đóng góp                     │
│ Thu quỹ năm 2026 · mức 200.000 đ    │
│                                    │
│ 1  Nguyễn Thị Hoa         500.000 đ │
│ 2  Bùi Văn Nam            400.000 đ │
│ 3  Phạm Văn Hà            300.000 đ │
│ 4  …                                │
│ 5  …                                │
│ Xem tất cả (8)                      │
└────────────────────────────────────┘
```

  - Dòng phụ: tên chiến dịch, kèm "mức {X} đ" hoặc "tùy tâm · trung bình {Y} đ".
  - Mỗi dòng: thứ hạng, tên, **tổng đã đóng** trong chiến dịch. Ba hạng đầu có số thứ tự tô màu (vàng, bạc, đồng).
  - Hiện 5 người đầu; nhiều hơn thì có **"Xem tất cả ({n})"** mở rộng ngay trong box.
- **Ai xem được:** giống dòng thời gian (nhóm công khai ai có link cũng xem; nhóm riêng tư chỉ thành viên).
- **Không có ai đủ điều kiện:** không hiện box (cả khách lẫn quản trị viên).

### 2. Ai được lên top

Chỉ tính **phiếu thu chưa xóa** gắn chiến dịch, giống cách tính "đã đóng" ở `CampaignProgress`, **trừ các phiếu bị ẩn khỏi top** (mục 3). Gọi tổng đó là "số tính top".

- **Chiến dịch có mức:** lên top khi số tính top **lớn hơn** mức phải đóng của chính người đó:
  - Người trong danh sách phải đóng: mức riêng (`expected_amount`) nếu có, không thì mức chung của chiến dịch.
  - Người được miễn, hoặc người ngoài danh sách mà vẫn đóng: so với mức chung của chiến dịch.
- **Chiến dịch tùy tâm:** tính **trung bình** = tổng số tính top ÷ số người có số tính top lớn hơn 0. Lên top khi số tính top **lớn hơn** trung bình. Mọi người đóng bằng nhau thì không ai lên top, không hiện box.
- **Xếp hạng:** số tính top từ cao xuống thấp; bằng tiền thì ai đóng xong sớm hơn (phiếu cuối cũ hơn) đứng trước.
- Phiếu chỉ có tên tự do (không gắn danh bạ): mỗi phiếu là một người, giống danh sách Đã đóng hiện nay.
- Số hiện trên box là **số tính top** (đã trừ phiếu ẩn), không phải tổng đã đóng thực tế, để không lộ ra là có phiếu bị ẩn.

### 3. Ẩn phiếu thu khỏi top

Quản trị viên đánh dấu **từng phiếu thu** "Không tính vào Top đóng góp". Phiếu vẫn tính vào quỹ, tiến độ chiến dịch, danh sách Đã đóng như bình thường, chỉ không cộng vào số tính top.

Ví dụ đóng bù: Bùi Văn Nam đóng một phiếu 400.000 đ. Quản trị viên ẩn phiếu đó → số tính top của Nam là 0, Nam ra khỏi top. Nếu Nam đóng hai phiếu (200.000 đ bù năm 2025 + 200.000 đ năm 2026) thì chỉ cần ẩn phiếu đóng bù, Nam còn 200.000 đ = mức, cũng không lên top.

Chỗ ẩn:

- **Form phiếu thu** (`TransactionFormPage.vue`): ô chọn "Không tính vào Top đóng góp" cạnh ô "Hiện trên dòng thời gian". Chỉ hiện với phiếu thu có gắn chiến dịch. Gợi ý nhỏ: "Dùng khi đóng bù năm trước, đóng hộ…".
- **Ngay trong box** (chỉ trưởng nhóm / quản trị viên thấy): mỗi dòng có nút **Ẩn**.
  - Người đó có một phiếu trong chiến dịch: hỏi lại "Ẩn phiếu {số tiền} ngày {dd/mm} của {tên} khỏi top?" → ẩn.
  - Có nhiều phiếu: mở bảng nhỏ liệt kê các phiếu (ngày, số tiền, tiêu đề), chọn phiếu cần ẩn.
- **Xem lại / bỏ ẩn:** cuối box, quản trị viên thấy dòng mờ "Đã ẩn {n} phiếu khỏi top · Xem", bấm ra danh sách phiếu đã ẩn, mỗi phiếu có nút **Bỏ ẩn**.
- Danh sách phiếu thu trong quản trị (`TransactionsPage.vue`): phiếu đã ẩn có nhãn nhỏ "Không tính top".

## Việc cần làm

1. **Database `transactions`:** thêm cột `exclude_from_top` (boolean, mặc định `false`).
2. **Tính top:** service mới `CampaignTopContributors` (cạnh `CampaignProgress`), trả `{ campaign: {id, name, slug, amount_per_member, average}, people: [{rank, contact_id, name, amount}], hidden_count }`.
   - Dùng lại cách gom phiếu theo người của `CampaignProgress` (tách hàm chung nếu cần, không viết lặp).
   - Cache qua `GroupStats` như `openCampaigns`; mọi chỗ ghi phiếu đã gọi `GroupStats::forget` nên tự làm mới.
3. **API:**
   - `GET /api/g/{group}/summary` trả thêm `top_contributors` (null khi không có ai). `hidden_count` chỉ trả cho quản trị viên.
   - Quản trị: lưu `exclude_from_top` trong tạo/sửa phiếu thu (qua `TransactionWriter`, có ghi lịch sử phiếu).
   - Quản trị: `GET /api/g/{group}/admin/campaigns/{campaign}/top/transactions?contact_id=` lấy các phiếu của một người (cho bảng chọn phiếu), và `PATCH /api/g/{group}/admin/transactions/{id}/top` với `{ exclude: bool }` để ẩn / bỏ ẩn nhanh.
4. **Giao diện:**
   - Component mới `TopContributors.vue`, đặt trong `GroupHomePage.vue` ngay sau `GroupRules` (cả bố cục máy tính và điện thoại).
   - Ô chọn trong `TransactionFormPage.vue`, nhãn trong `TransactionsPage.vue`.
5. **Test:**
   - Chiến dịch có mức: chỉ người vượt mức lên top, đúng thứ tự; người có mức riêng so với mức riêng; người được miễn so với mức chung.
   - Tùy tâm: chỉ người trên trung bình; mọi người bằng nhau thì `top_contributors = null`.
   - Ẩn phiếu: người đóng bù ra khỏi top, tiến độ chiến dịch và danh sách Đã đóng không đổi; bỏ ẩn thì trở lại.
   - Nhóm riêng tư không trả top cho khách; khách không thấy `hidden_count`; thành viên thường không gọi được API ẩn.
6. **Nhóm demo:** cho vài người trong `nhomdemo` đóng vượt mức và một phiếu đóng bù đã ẩn, để box có dữ liệu.
7. Thêm dòng 22 vào bảng trong `plan.md`.

## Ngoài phạm vi

- Top theo cả năm hoặc toàn thời gian, top nhiều chiến dịch cùng lúc.
- Ẩn một phần số tiền của một phiếu (muốn vậy thì tách thành hai phiếu).
- Ghi phần đóng bù sang chiến dịch năm trước (đúng sổ sách hơn nhưng cần chiến dịch năm trước đã có; có thể làm sau).
- Cho người đóng tự chọn ẩn tên mình khỏi top.

## Đã chốt (11/11/2026)

1. Hiện **5** người, có "Xem tất cả".
2. Tên box: **"Top đóng góp"**.
3. Nhiều chiến dịch mở cùng lúc: chỉ lấy chiến dịch mới nhất.
4. Người được miễn mà vẫn đóng: so với mức chung.

## Ghi chú khi làm (11/11/2026)

- Đổi số plan từ 21 thành 22 vì phiên khác đang làm `21-thanh-vien-dang-bai`. Migration `2026_11_11_000010_add_exclude_from_top_to_transactions_table.php`.
- Service `App\Services\Campaigns\CampaignTopContributors`, cache qua `GroupStats::topContributors()` (khóa `top`).
- API: `summary.top_contributors`; khách và thành viên thường không nhận `transaction_ids`, `hidden_count`. QTV: `GET /api/g/{ma-nhom}/admin/campaigns/{id}/top-transactions?contact_id=|hidden=1`, `PUT /api/g/{ma-nhom}/admin/transactions/{id}/top` với `{ exclude }`. Ẩn / bỏ ẩn ghi vào lịch sử phiếu.
- Mỗi dòng hiện **ảnh đại diện** (chưa có ảnh thì chữ cái đầu), hạng là huy hiệu nhỏ ở góc avatar. Mỗi dòng một tông tím dùng chung cho vòng avatar, nền chữ cái, huy hiệu và số tiền; **đóng càng nhiều màu càng đậm** (so giữa người ít nhất và nhiều nhất trong top, chế độ tối thì càng sáng). Số tiền in đậm. Tên bấm được sang trang thành viên nếu người đó có tài khoản trong nhóm. Ảnh và link lấy từ `MemberIndex` lúc trả API, không nằm trong cache.
- Bằng tiền thì cùng hạng liên tiếp (1, 2, 3…), ai đóng xong sớm hơn đứng trước.
- Phiếu bỏ chiến dịch (hoặc đổi thành phiếu chi) thì cờ "Không tính vào Top" tự về `false`.
- Nhóm demo: 3 người đóng vượt mức 500.000 đ, 1 phiếu 1.000.000 đ "bù năm trước" đã ẩn. Máy dev đã sửa thẳng dữ liệu `nhomdemo`; server cần làm tương tự khi deploy (seeder bỏ qua nhóm đã có).
- Test: `tests/Feature/Campaigns/TopContributorsTest.php` (6 test).
