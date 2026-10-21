# syntax=docker/dockerfile:1

# ---------------------------------------------------------------
# Tahap 1: build aset frontend (Vite + Tailwind)
# ---------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY . .
RUN npm run build

# ---------------------------------------------------------------
# Tahap 2: install dependensi PHP (composer)
# INSTALL_DEV=true  -> ikut paket dev (pest, dll) supaya bisa php artisan test
# ---------------------------------------------------------------
FROM composer:2 AS vendor
ARG INSTALL_DEV=false
WORKDIR /app
COPY composer.json composer.lock ./
RUN if [ "$INSTALL_DEV" = "true" ]; then \
      composer install --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs; \
    else \
      composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs; \
    fi

# ---------------------------------------------------------------
# Tahap 3: image aplikasi (PHP-FPM)
# ---------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app
COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl opcache \
    && apk add --no-cache mysql-client

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --from=vendor /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-scripts --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------
# Tahap 4: image web server (nginx) + file statis dari /public
# ---------------------------------------------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
