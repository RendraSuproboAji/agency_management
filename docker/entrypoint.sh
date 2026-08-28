#!/usr/bin/env bash
set -e

cd /var/www/html

if [ -z "${APP_KEY}" ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    cp -n .env.example .env 2>/dev/null || true
    php artisan key:generate --force
fi

# SQLite hidup di volume storage/ supaya data bertahan antar restart.
mkdir -p storage/app/public storage/database
: "${DB_DATABASE:=/var/www/html/storage/database/database.sqlite}"
export DB_DATABASE
touch "${DB_DATABASE}"

php artisan migrate --force
php artisan db:seed --force
php artisan storage:link || true

chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache

exec "$@"
