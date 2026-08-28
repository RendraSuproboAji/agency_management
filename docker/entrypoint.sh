#!/usr/bin/env bash
set -e

cd /var/www/html

# SQLite dan berkas unggahan hidup di volume storage/ supaya bertahan antar restart.
mkdir -p storage/app/public storage/database
: "${DB_DATABASE:=/var/www/html/storage/database/database.sqlite}"
export DB_DATABASE
touch "${DB_DATABASE}"

# APP_KEY dari environment dipakai apa adanya. Kalau kosong, kunci dibuat sekali
# lalu disimpan di volume — variabel environment menutupi isi .env, jadi kunci
# harus diekspor, bukan sekadar ditulis ke berkas.
if [ -z "${APP_KEY}" ]; then
    if [ ! -f storage/app.key ]; then
        php artisan key:generate --show > storage/app.key
        chmod 600 storage/app.key
    fi
    APP_KEY="$(cat storage/app.key)"
    export APP_KEY
fi

php artisan migrate --force
php artisan db:seed --force
php artisan storage:link || true

chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache

exec "$@"
