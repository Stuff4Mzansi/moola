#!/bin/bash
set -euo pipefail
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
if [ "${1:-}" != "apache2-foreground" ]; then
    exec docker-php-entrypoint "$@"
fi

gosu www-data php artisan schedule:work --no-interaction &
scheduler_pid=$!
docker-php-entrypoint "$@" &
web_pid=$!
shutdown() {
    trap - TERM INT
    kill -TERM "$scheduler_pid" "$web_pid" 2>/dev/null || true
    wait "$scheduler_pid" "$web_pid" 2>/dev/null || true
}
trap 'shutdown; exit 0' TERM INT
status=0
wait -n "$scheduler_pid" "$web_pid" || status=$?
shutdown
if [ "$status" -eq 0 ]; then
    status=1
fi
exit "$status"
