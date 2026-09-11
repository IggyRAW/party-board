#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ -z "$APP_KEY" ]; then
    existing_key=$(grep -E '^APP_KEY=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' || true)
    if [ -z "$existing_key" ]; then
        php artisan key:generate --force
    fi
fi

php artisan migrate --force --seed

exec "$@"