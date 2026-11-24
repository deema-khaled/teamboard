# syntax=docker/dockerfile:1
# Build context: ./api
#   app -> PHP-FPM 8.4 (also used for queue, scheduler and Reverb containers)
#   web -> Nginx serving public/ (incl. the static API docs) and proxying PHP

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM php:8.4-fpm-alpine AS app
WORKDIR /var/www/html

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis opcache intl pcntl bcmath zip

COPY --from=dockerfiles php.ini /usr/local/etc/php/conf.d/zz-teamboard.ini
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --from=dockerfiles api-entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web
COPY --from=dockerfiles api-nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
