#!/bin/sh
set -eu
composer validate --strict
composer check-platform-reqs
php vendor/bin/phpunit --fail-on-warning --fail-on-deprecation --fail-on-phpunit-deprecation
php artisan l5-swagger:generate
php artisan route:cache
php artisan route:clear
php artisan config:cache
php artisan config:clear
