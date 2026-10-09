#!/usr/bin/env bash
#
# Redeploy JalRakshak after a git push. Run on the EC2 instance with sudo.

set -euo pipefail

APP_DIR="/var/www/jalrakshak-aws"

if [[ $EUID -ne 0 ]]; then
  echo "Run this with sudo." >&2
  exit 1
fi

cd "$APP_DIR"
git pull --ff-only

cd "$APP_DIR/api"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache

cd "$APP_DIR/web"
npm ci
npm run build

chown -R www-data:www-data "$APP_DIR/api/storage" "$APP_DIR/api/bootstrap/cache" "$APP_DIR/web/dist"
systemctl reload php8.3-fpm nginx

echo "Deployed."
