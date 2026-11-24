#!/bin/sh
set -e

# Only the main PHP-FPM container runs setup; queue/scheduler/Reverb just start.
if [ "$1" = "php-fpm" ]; then
    if [ -z "$APP_KEY" ]; then
        echo "APP_KEY is not set. Run: php artisan key:generate --show, then put it in api/.env" >&2
        exit 1
    fi

    php artisan config:cache
    php artisan route:cache
    php artisan migrate --force

    if [ "${SEED_DEMO_DATA:-false}" = "true" ] && [ "$(php artisan tinker --execute='echo \App\Models\User::count();')" = "0" ]; then
        php artisan db:seed --force
    fi
else
    php artisan config:cache
fi

exec "$@"
