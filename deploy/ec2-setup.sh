#!/usr/bin/env bash
#
# One time setup for JalRakshak on a fresh Amazon EC2 instance.
# Target: Ubuntu 24.04 LTS on t3.micro or t2.micro (free tier), which ships
# PHP 8.3 and MySQL 8 in its default repositories.
#
# Usage, on the instance:
#   curl -fsSL https://raw.githubusercontent.com/shubhamverma-devx/jalrakshak-aws/main/deploy/ec2-setup.sh -o setup.sh
#   chmod +x setup.sh
#   sudo ./setup.sh
#
# It is safe to re-run.

set -euo pipefail

REPO="https://github.com/shubhamverma-devx/jalrakshak-aws.git"
APP_DIR="/var/www/jalrakshak-aws"
DB_NAME="jalrakshak_aws"
DB_USER="jalrakshak"

if [[ $EUID -ne 0 ]]; then
  echo "Run this with sudo." >&2
  exit 1
fi

say() { printf '\n==> %s\n' "$1"; }

say "Adding swap"
# t3.micro has under 1 GB of RAM. The Vite build and composer can both spike
# past that, so give the box 2 GB of swap before anything heavy runs.
if ! swapon --show | grep -q /swapfile; then
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
free -h | head -3

say "Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y --no-install-recommends \
  nginx mysql-server git unzip curl ca-certificates \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl

say "Installing Composer"
if ! command -v composer >/dev/null; then
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

say "Installing Node.js 20 (to build the React app)"
if ! command -v node >/dev/null; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

say "Creating the database"
DB_PASS_FILE="/root/.jalrakshak-db-pass"
if [[ ! -f "$DB_PASS_FILE" ]]; then
  head -c 24 /dev/urandom | base64 | tr -d '/+=' > "$DB_PASS_FILE"
  chmod 600 "$DB_PASS_FILE"
fi
DB_PASS="$(cat "$DB_PASS_FILE")"

mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

say "Fetching the code"
if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" pull --ff-only
else
  mkdir -p "$(dirname "$APP_DIR")"
  git clone --depth 1 "$REPO" "$APP_DIR"
fi

say "Installing PHP dependencies"
cd "$APP_DIR/api"
sudo -u www-data -H composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null \
  || composer install --no-dev --optimize-autoloader --no-interaction

say "Writing the .env"
# IMDSv2: fetch a token first. A plain GET is refused when the instance is
# launched with HttpTokens=required, which is the hardened default here.
IMDS_TOKEN="$(curl -fsS --max-time 5 -X PUT 'http://169.254.169.254/latest/api/token' \
  -H 'X-aws-ec2-metadata-token-ttl-seconds: 300' || true)"

PUBLIC_HOST="$(curl -fsS --max-time 5 \
  -H "X-aws-ec2-metadata-token: ${IMDS_TOKEN}" \
  http://169.254.169.254/latest/meta-data/public-hostname || echo localhost)"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

set_env() {
  local key="$1" value="$2"
  if grep -q "^${key}=" .env; then
    # Use | as the delimiter so URLs with / do not break sed.
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

set_env APP_ENV production
set_env APP_DEBUG false
set_env "APP_URL" "http://${PUBLIC_HOST}"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"
set_env MAPS_DISK s3
set_env SNS_ENABLED true
set_env AWS_DEFAULT_REGION ap-south-1

# The officer console is reachable from the internet, so it must not ship with
# the placeholder credentials from .env.example. Generate them once and keep
# them, so re-running this script does not change the password mid demo.
OFFICER_PASS_FILE="/root/.jalrakshak-officer-pass"
if [[ ! -f "$OFFICER_PASS_FILE" ]]; then
  head -c 12 /dev/urandom | base64 | tr -d '/+=' > "$OFFICER_PASS_FILE"
  chmod 600 "$OFFICER_PASS_FILE"
fi
OFFICER_PASS="$(cat "$OFFICER_PASS_FILE")"

OFFICER_TOKEN_FILE="/root/.jalrakshak-officer-token"
if [[ ! -f "$OFFICER_TOKEN_FILE" ]]; then
  head -c 32 /dev/urandom | base64 | tr -d '/+=' > "$OFFICER_TOKEN_FILE"
  chmod 600 "$OFFICER_TOKEN_FILE"
fi

set_env OFFICER_PASSWORD "$OFFICER_PASS"
set_env OFFICER_TOKEN "$(cat "$OFFICER_TOKEN_FILE")"

grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

echo
echo "NOTE: set AWS_BUCKET, and either attach an IAM instance role or set"
echo "      AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY, in $APP_DIR/api/.env"
echo

say "Running migrations and seeding the Assam demo data"
php artisan migrate --force --seed

# Replay ka risk pehle se compute karke rakho, warna dashboard pehli baar khaali dikhta
# hai jab tak scheduler nahi chalta.
php artisan risk:compute --mode=replay --day=5 || true
php artisan risk:compute --mode=live || true

# Har gaon ka Amazon SNS topic bana do, taaki console mein dikhein.
php artisan jalrakshak:sns-setup || true

php artisan config:cache
php artisan route:cache

say "Building the React app"
cd "$APP_DIR/web"
npm ci
npm run build

say "Setting permissions"
chown -R www-data:www-data "$APP_DIR/api/storage" "$APP_DIR/api/bootstrap/cache"
chmod -R 775 "$APP_DIR/api/storage" "$APP_DIR/api/bootstrap/cache"
chown -R www-data:www-data "$APP_DIR/web/dist"

say "Scheduling risk:compute"
# Laravel ka scheduler har minute chalta hai; routes/console.php tay karta hai ki
# risk:compute kab chale. Cron root ke paas hai, command www-data ke roop mein.
cat > /etc/cron.d/jalrakshak <<'CRON'
* * * * * www-data cd /var/www/jalrakshak-aws/api && /usr/bin/php artisan schedule:run >> /var/log/jalrakshak-schedule.log 2>&1
CRON
chmod 644 /etc/cron.d/jalrakshak
touch /var/log/jalrakshak-schedule.log
chown www-data:www-data /var/log/jalrakshak-schedule.log
systemctl restart cron

say "Configuring Nginx"
cp "$APP_DIR/deploy/nginx-jalrakshak.conf" /etc/nginx/sites-available/jalrakshak
ln -sf /etc/nginx/sites-available/jalrakshak /etc/nginx/sites-enabled/jalrakshak
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl enable --now php8.3-fpm nginx mysql

say "Done"
echo "Citizen page : http://${PUBLIC_HOST}/"
echo "Officer page : http://${PUBLIC_HOST}/officer"
echo "Health check : http://${PUBLIC_HOST}/api/health"
echo
echo "Officer login: $(grep '^OFFICER_EMAIL=' "$APP_DIR/api/.env" | cut -d= -f2)"
echo "Password     : ${OFFICER_PASS}"
echo "(also kept in ${OFFICER_PASS_FILE})"
