# composer.lock includes dependencies restricted to PHP 7.
FROM php:7.4-fpm-bullseye AS runtime-base
RUN printf '%s\n' \
      'deb [check-valid-until=no] https://archive.debian.org/debian bullseye main' \
      'deb [check-valid-until=no] https://archive.debian.org/debian-security bullseye-security main' > /etc/apt/sources.list \
    && apt-get update && apt-get install -y --no-install-recommends \
      libonig-dev libxml2-dev libzip-dev unzip git gosu \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/app-entrypoint
COPY --chmod=755 docker/scheduler.sh /usr/local/bin/app-scheduler
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]

FROM runtime-base AS dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

FROM runtime-base AS runtime
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
EXPOSE 9000

FROM nginx:stable-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=dependencies /var/www/html/public /var/www/html/public
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD wget -q -O /dev/null http://127.0.0.1/healthz || exit 1
