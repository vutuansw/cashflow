# Mở WePo từ icon màn hình chính

Ngày: 11/11/2026. Trạng thái: **đã làm** (11/11/2026).

## Vấn đề

- WePo đã là PWA (manifest, icon, service worker, plan/06 mục 4) nhưng gợi ý "Thêm vào màn hình chính" chỉ hiện trong khu quản trị. Thành viên và khách xem nhóm không thấy.
- Icon mở `start_url` `/groups?pwa=1`: luôn hiện trang chọn nhóm, khách chưa có tài khoản bị đưa tới trang đăng nhập.

## Đã làm

- **Gợi ý ở trang nhóm** (`GroupHomePage.vue`, chỉ điện thoại): thẻ "Thêm WePo ra màn hình chính", ngay trên thẻ chiến dịch. Android bấm **Cài đặt**; iPhone hướng dẫn Safari: Chia sẻ (iOS 26: nút ⋯ rồi Chia sẻ) → **Thêm vào MH chính**. Bấm X là ẩn hẳn (lưu trên máy). Đã mở từ icon thì không hiện.
- **Mở từ icon** (`/groups?pwa=1`, xử lý trong router `app.js`):
  - Có tài khoản một nhóm: vào thẳng nhóm đó. Nhiều nhóm: vào nhóm vừa xem gần nhất, chưa xem thì trang chọn nhóm.
  - Khách: vào nhóm công khai vừa xem gần nhất, chưa xem nhóm nào thì trang chủ.
  - "Nhóm vừa xem" lưu ở `localStorage` (`wepo:last-group`) mỗi khi mở trang nhóm xem được.
