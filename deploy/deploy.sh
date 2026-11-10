#!/usr/bin/env bash
# Cập nhật WePo trên server: bash /var/www/wepo/deploy/deploy.sh (chạy bằng root).
# Git và artisan chạy bằng www-data, build giao diện bằng Node (nvm) của root như chatbot.
# Có migration mới thì chạy luôn cho cả hai database (cashflow:migrate --force).
set -euo pipefail

# Bọc trong { } để bash đọc hết script trước khi chạy: git pull bên dưới thay chính file này.
{

APP_DIR="${APP_DIR:-/var/www/wepo}"
APP_USER="${APP_USER:-www-data}"
PHP="${PHP:-/usr/bin/php8.4}"
BRANCH="${BRANCH:-main}"

export NVM_DIR="${NVM_DIR:-/root/.nvm}"
if [ -s "$NVM_DIR/nvm.sh" ]; then
    set +u
    # shellcheck disable=SC1091
    . "$NVM_DIR/nvm.sh"
    set -u
fi

cd "$APP_DIR"
as_app() { sudo -u "$APP_USER" "$@"; }

OLD_COMMIT="$(as_app git rev-parse HEAD)"
as_app git pull --ff-only origin "$BRANCH"
NEW_COMMIT="$(as_app git rev-parse HEAD)"
changed() { as_app git diff --name-only --diff-filter=AM "$OLD_COMMIT" "$NEW_COMMIT" -- "$@"; }

# Chỉ cài lại thư viện khi file khóa đổi (hoặc lần đầu chưa có), cho deploy nhanh.
if [ ! -d vendor ] || [ -n "$(changed composer.lock)" ]; then
    as_app "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction --no-progress
fi
if [ ! -d node_modules ] || [ -n "$(changed package-lock.json)" ]; then
    npm ci --no-audit --no-fund
    chown -R "$APP_USER:$APP_USER" node_modules
fi
npm run build
chown -R "$APP_USER:$APP_USER" public/build

if [ "$OLD_COMMIT" = "$NEW_COMMIT" ] || [ -n "$(changed database/migrations)" ]; then
    as_app "$PHP" artisan cashflow:migrate --force
fi

as_app "$PHP" artisan optimize
# Worker thoát sau job đang chạy; Supervisor tự bật lại với code mới.
as_app "$PHP" artisan queue:restart

echo "Đã deploy $(as_app git rev-parse --short HEAD)."
exit 0
}
