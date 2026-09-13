#!/bin/sh
set -eu

fail() {
    echo "Moola configuration error: $1" >&2
    exit 1
}

[ "${APP_ENV:-}" = "production" ] || fail "APP_ENV must be production."
[ "${APP_DEBUG:-}" = "false" ] || fail "APP_DEBUG must be false."
[ -n "${APP_KEY:-}" ] || fail "APP_KEY is required. See README.md for the one-time key-generation command."
[ -n "${APP_URL:-}" ] || fail "APP_URL is required."
[ "${DB_CONNECTION:-}" = "sqlite" ] || fail "DB_CONNECTION must be sqlite."
[ -n "${DB_DATABASE:-}" ] || fail "DB_DATABASE is required."

case "$DB_DATABASE" in
    /*) ;;
    *) fail "DB_DATABASE must be an absolute container path." ;;
esac

database_directory=$(dirname "$DB_DATABASE")
[ -d "$database_directory" ] || fail "The SQLite directory does not exist: $database_directory"
[ -w "$database_directory" ] || fail "The SQLite directory is not writable: $database_directory"

touch "$DB_DATABASE"
php artisan config:cache --no-interaction
php artisan migrate --force --no-interaction

exec "$@"
