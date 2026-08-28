# syntax=docker/dockerfile:1
ARG BASE_REGISTRY=docker.io

FROM ${BASE_REGISTRY}/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM ${BASE_REGISTRY}/php:8.3-apache AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libsqlite3-dev unzip curl \
    && docker-php-ext-install pdo_sqlite zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers expires \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/agency.conf \
    && a2enconf agency

COPY docker/php.ini /usr/local/etc/php/conf.d/agency.ini

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
