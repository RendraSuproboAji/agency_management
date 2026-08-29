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

FROM ${BASE_REGISTRY}/php:8.4-apache AS runtime

# Tidak ada paket yang perlu dipasang: image php:8.4-apache sudah membawa
# pdo_sqlite, sqlite3, dan curl (dipakai healthcheck). Vendor dipasang di stage
# composer dengan --no-dev, jadi ext-zip pun tidak diperlukan di runtime.
RUN a2enmod rewrite headers expires \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/agency.conf \
    && a2enconf agency

COPY docker/php.ini /usr/local/etc/php/conf.d/agency.ini

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
