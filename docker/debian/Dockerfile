# syntax=docker/dockerfile:1.7

# Same extensions and settings as the dev stage of the main Dockerfile, on Debian (glibc) instead of Alpine
# (musl). Used only to compare both under load; the main image stays Alpine.

ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli

ARG SWOOLE_VERSION=6.2.5
ARG PHPREDIS_VERSION=6.3.0
ARG OPENTELEMETRY_VERSION=1.5.0
ARG PROTOBUF_VERSION=5.36.2
ARG PCOV_VERSION=1.0.12
ARG UID=1000
ARG GID=1000

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libssl-dev libbrotli-dev zlib1g-dev git unzip wget; \
    docker-php-ext-install -j"$(nproc)" pcntl pdo_mysql sockets; \
    pecl install -D 'enable-sockets="yes" enable-openssl="yes" enable-brotli="yes"' "swoole-${SWOOLE_VERSION}"; \
    pecl install "redis-${PHPREDIS_VERSION}"; \
    pecl install "opentelemetry-${OPENTELEMETRY_VERSION}"; \
    pecl install "protobuf-${PROTOBUF_VERSION}"; \
    pecl install "pcov-${PCOV_VERSION}"; \
    docker-php-ext-enable swoole redis opentelemetry protobuf pcov; \
    rm -rf /var/lib/apt/lists/* /tmp/pear ~/.pearrc; \
    cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; \
    groupadd -g "${GID}" app; \
    useradd -m -u "${UID}" -g app -d /home/app app

COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php/dev.ini "$PHP_INI_DIR/conf.d/zz-dev.ini"

WORKDIR /var/www/html
EXPOSE 8000
USER app
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
