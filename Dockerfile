FROM php:8.3-fpm-bookworm AS runtime-base
RUN apt-get update && apt-get install -y --no-install-recommends \
      libonig-dev libxml2-dev libzip-dev libsqlite3-dev unzip git gosu \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite mbstring bcmath zip opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
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
    && php artisan l5-swagger:generate \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

FROM runtime-base AS test
COPY . .
RUN composer install --no-interaction --prefer-dist \
    && mkdir -p .phpunit.cache storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data .phpunit.cache storage bootstrap/cache
CMD ["sh", "docker/test.sh"]

FROM runtime-base AS runtime
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
EXPOSE 9000

FROM nginx:stable-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=dependencies /var/www/html/public /var/www/html/public
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD wget -q -O /dev/null http://127.0.0.1/healthz || exit 1
