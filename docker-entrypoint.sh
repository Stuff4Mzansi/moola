#!/bin/sh
set -eu
umask 077
cd /var/www/html
mkdir -p /data/storage/app/private /data/storage/app/public \
    /data/storage/framework/cache/data /data/storage/framework/sessions \
    /data/storage/framework/views /data/storage/logs /var/www/html/bootstrap/cache
chown -R www-data:www-data /data /var/www/html/bootstrap/cache
rm -f /var/www/html/bootstrap/cache/config.php
gosu www-data php artisan moola:initialize --no-interaction
APP_KEY="$(cat /data/app.key)"
export APP_KEY
gosu www-data php artisan config:cache --no-interaction
gosu www-data php artisan route:cache --no-interaction
gosu www-data php artisan view:cache --no-interaction
exec docker-php-entrypoint "$@"
