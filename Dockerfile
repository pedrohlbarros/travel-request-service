# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.5

# ---------------------------------------------------------------------------
# base: PHP + runtime extensions shared by every stage
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli-alpine AS base

ARG SWOOLE_VERSION=6.2.5
ARG PHPREDIS_VERSION=6.3.0
ARG UID=1000
ARG GID=1000

RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers openssl-dev brotli-dev zlib-dev; \
    docker-php-ext-install -j"$(nproc)" pcntl pdo_mysql sockets; \
    pecl install -D 'enable-sockets="yes" enable-openssl="yes" enable-brotli="yes"' "swoole-${SWOOLE_VERSION}"; \
    pecl install "redis-${PHPREDIS_VERSION}"; \
    docker-php-ext-enable swoole redis; \
    runDeps="$(scanelf --needed --nobanner --format '%n#p' --recursive /usr/local \
        | tr ',' '\n' | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }')"; \
    apk add --no-cache $runDeps; \
    apk del .build-deps; \
    rm -rf /tmp/pear ~/.pearrc; \
    addgroup -g "${GID}" app; \
    adduser -D -u "${UID}" -G app -h /home/app app

WORKDIR /var/www/html
EXPOSE 8000

# ---------------------------------------------------------------------------
# dev: local development, tests and CI (bind-mounted source)
# ---------------------------------------------------------------------------
FROM base AS dev

ARG PCOV_VERSION=1.0.12

RUN set -eux; \
    apk add --no-cache git unzip; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    pecl install "pcov-${PCOV_VERSION}"; \
    docker-php-ext-enable pcov; \
    apk del .build-deps; \
    rm -rf /tmp/pear ~/.pearrc; \
    cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php/dev.ini "$PHP_INI_DIR/conf.d/zz-dev.ini"

USER app
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]

# ---------------------------------------------------------------------------
# vendor: production dependencies
# ---------------------------------------------------------------------------
FROM dev AS vendor

COPY --chown=app:app composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------------------------------------------------------------------------
# runtime: immutable production-like image
# ---------------------------------------------------------------------------
FROM base AS runtime

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php/runtime.ini "$PHP_INI_DIR/conf.d/zz-runtime.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --chown=app:app --from=vendor /var/www/html/vendor ./vendor
COPY --chown=app:app . .
RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction \
    && rm /usr/bin/composer

USER app
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
