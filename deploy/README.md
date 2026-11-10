# Deploy WePo

Server đã dựng ngày 10/11/2026, xem [plan/07 mục 4](../plan/07-lo-trinh-va-trien-khai.plan.md#4-triển-khai). File này ghi cách cập nhật code lên server.

## Cập nhật bằng tay

SSH vào server bằng root rồi chạy:

```bash
bash /var/www/wepo/deploy/deploy.sh
```

Script lấy code mới từ `main`, cài lại thư viện khi file khóa đổi, build giao diện, chạy `cashflow:migrate --force` khi có migration mới, `optimize` và khởi động lại worker.

## Cho GitHub tự deploy

Làm một lần, giống `www/chatbot`. Xong thì mỗi lần push lên `main`, workflow [Deploy](../.github/workflows/deploy.yml) tự SSH vào server và chạy `deploy.sh`.

1. **Khoá SSH cho GitHub Actions.** Dùng lại cặp khoá của chatbot (`~/.ssh/hipochat_actions`), vì cùng VPS. Muốn tách riêng thì tạo cặp mới:

   ```bash
   ssh-keygen -t ed25519 -N "" -C "github-actions-wepo" -f ~/.ssh/wepo_actions
   ```

   Khoá công khai (`.pub`) phải có trong `~/.ssh/authorized_keys` của user SSH trên server. Khoá của chatbot đã có sẵn.

2. **Thử trước từ máy mình.** User là `root`, hoặc user chạy được `sudo` không hỏi mật khẩu:

   ```bash
   ssh -i ~/.ssh/hipochat_actions root@IP_SERVER "bash /var/www/wepo/deploy/deploy.sh"
   ```

3. **Thêm secret trên GitHub**, repo `vutuansw/cashflow`, **Settings → Secrets and variables → Actions → New repository secret**:

   | Secret | Giá trị |
   | --- | --- |
   | `DEPLOY_HOST` | IP hoặc tên miền của VPS (giống chatbot) |
   | `DEPLOY_USER` | User SSH vừa thử, ví dụ `root` |
   | `DEPLOY_SSH_KEY` | Toàn bộ nội dung khoá bí mật, ví dụ file `~/.ssh/hipochat_actions` |

   Đặt ở **Secrets**, không đặt ở Variables: Variables ai xem được repo cũng đọc được.

4. Vào tab **Actions → Deploy → Run workflow** để chạy thử. Xem log, dòng cuối là `Đã deploy <commit>.`

## Lưu ý

- Workflow chỉ deploy, không chạy test. Chạy `php artisan test` ở máy mình trước khi push.
- Migration chạy tự động. Migration nào xóa hoặc đổi dữ liệu thì sao lưu database trước khi push.
- Biến mới trong `.env.example` (ví dụ `LANDING_DEMO_GROUP`) phải tự thêm vào `.env` trên server, deploy không đụng tới `.env`. Sửa `.env` xong chạy lại `deploy.sh` để `optimize` đọc giá trị mới.
