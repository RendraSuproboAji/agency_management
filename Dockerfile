ARG BASE_REGISTRY=docker.io

FROM ${BASE_REGISTRY}/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM ${BASE_REGISTRY}/node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM ${BASE_REGISTRY}/php:8.4-fpm AS runtime

# Tidak ada paket yang perlu dipasang: image php:8.4-fpm sudah membawa
# pdo_sqlite dan sqlite3. Vendor dipasang di stage composer dengan --no-dev,
# jadi ext-zip pun tidak diperlukan di runtime.
COPY docker/php.ini /usr/local/etc/php/conf.d/agency.ini

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# nginx membawa salinan public/ sendiri, bukan berbagi volume kode dengan PHP:
# satu build menghasilkan dua image yang selalu sinkron, tanpa risiko aset basi.
FROM ${BASE_REGISTRY}/nginx:alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
# Berkas unggahan datang dari volume storage yang dipasang saat runtime.
RUN ln -sfn ../storage/app/public /var/www/html/public/storage
EXPOSE 80
