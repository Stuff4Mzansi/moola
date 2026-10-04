# syntax=docker/dockerfile:1
FROM php:8.5-apache-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends \
    gosu libicu-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip \
    && php -r 'foreach (["intl", "mbstring", "pdo_sqlite", "sqlite3", "zip", "Phar"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: ".$extension."\n"); exit(1); } }' \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts \
    && composer check-platform-reqs --no-dev

FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
RUN npm run build

FROM php-base AS production
ENV APP_NAME=Moola APP_ENV=production APP_DEBUG=false APP_URL=http://localhost:8080 \
    APP_TIMEZONE=Africa/Johannesburg MOOLA_DATA_DIR=/data \
    DB_CONNECTION=sqlite DB_DATABASE=/data/database.sqlite SQLITE_JOURNAL_MODE=WAL \
    CACHE_STORE=database SESSION_DRIVER=database SESSION_ENCRYPT=true \
    QUEUE_CONNECTION=sync LOG_CHANNEL=stderr LOG_LEVEL=warning MAIL_MAILER=log
COPY --from=dependencies /var/www/html /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY docker-apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker-php.ini /usr/local/etc/php/conf.d/moola.ini
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/moola-entrypoint
RUN printf 'Listen 8080\n' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/moola.conf \
    && a2enconf moola \
    && rm -rf /var/www/html/storage \
    && ln -s /data/storage /var/www/html/storage \
    && mkdir -p /data /var/www/html/bootstrap/cache
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") === false ? 1 : 0);'
ENTRYPOINT ["moola-entrypoint"]
CMD ["apache2-foreground"]
