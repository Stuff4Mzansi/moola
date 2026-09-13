# syntax=docker/dockerfile:1.7

FROM composer:2.10.3 AS composer

FROM php:8.5.10-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" intl mbstring opcache pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-moola.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-moola.conf

FROM php-base AS vendor

WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist
COPY . .
RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction \
    --no-scripts

FROM php-base AS app

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/moola-entrypoint

RUN mkdir -p /var/lib/moola /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/lib/moola /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data
ENTRYPOINT ["moola-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]

FROM nginx:1.30.4-alpine3.24 AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /var/www/html/public /var/www/html/public

RUN mkdir -p /var/cache/nginx /var/run \
    && chown -R nginx:nginx /var/cache/nginx /var/run /etc/nginx/conf.d

USER nginx
