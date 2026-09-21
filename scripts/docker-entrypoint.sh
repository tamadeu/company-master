#!/bin/sh

set -eu

attempt=1
max_attempts=10

until php artisan migrate --force; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "Database migration failed after ${max_attempts} attempts." >&2
        exit 1
    fi

    echo "Database unavailable; retrying migration (${attempt}/${max_attempts})..." >&2
    attempt=$((attempt + 1))
    sleep 3
done

php artisan storage:link || true
php artisan config:cache
php artisan view:cache

exec "$@"