#!/usr/bin/env bash
# Cập nhật code trên server (chạy bằng user sở hữu thư mục app, vd: sudo -u smm ./scripts/deploy.sh).
# Xem docs/deploy-almalinux.md.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Bật chế độ bảo trì"
php artisan down --retry=30 || true

echo "→ Lấy code mới"
git pull --ff-only

echo "→ Cài thư viện PHP"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Build CSS/JS"
npm ci --no-audit --no-fund
npm run build

echo "→ Cập nhật database"
php artisan migrate --force

echo "→ Tối ưu cache"
php artisan optimize
php artisan filament:optimize

echo "→ Khởi động lại queue worker"
php artisan queue:restart

php artisan up
echo "✓ Deploy xong"
