FROM dunglas/frankenphp:php8.4-alpine AS php-base

RUN apk add --no-cache supervisor \
    && install-php-extensions \
    intl \
    mbstring \
    opcache \
    pcntl \
    pdo_pgsql \
    zip

WORKDIR /app

FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM node:22-alpine AS frontend

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN npm ci --include=dev && npm run build

FROM php-base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    REDIS_CLIENT=predis \
    SERVER_NAME=:8080

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY Caddyfile /etc/caddy/Caddyfile
COPY docker/supervisord.conf /etc/supervisord.conf

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /data/caddy /config/caddy \
    && rm -f public/storage \
    && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache /data /config \
    && chmod +x scripts/docker-entrypoint.sh

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/up >/dev/null || exit 1

ENTRYPOINT ["/app/scripts/docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]