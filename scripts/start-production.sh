#!/usr/bin/env bash

set -euo pipefail

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan view:cache

node /assets/scripts/prestart.mjs /assets/nginx.template.conf /nginx.conf
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=120 --max-time=3600 &
php artisan schedule:work &
php-fpm -y /assets/php-fpm.conf &
exec nginx -c /nginx.conf