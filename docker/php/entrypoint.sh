#!/bin/sh
set -e
cd /var/www/html

# Volume baru bisa kosong / milik root
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ "$1" = "php-fpm" ]; then
    php artisan package:discover --ansi

    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        php artisan migrate --force
    fi

    if [ "${APP_ENV}" = "production" ]; then
        php artisan optimize || true
    fi
fi

exec "$@"
