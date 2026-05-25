# syntax=docker/dockerfile:1.7
#
# Antarctic production image — multi-stage build.
#
#   1. `vendor`  installs Composer dependencies once (cached layer).
#   2. `build`   adds the app source + warms route cache + OpenAPI dump.
#   3. `runtime` is the lean final image: PHP-FPM + extensions only.
#
# Build context is expected to be the repository root:
#   docker build -f docker/php-fpm/Dockerfile -t antarctic-fpm .
#

ARG PHP_VERSION=8.2

# ---------------------------------------------------------------------------
# Stage 1 — Composer dependencies.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# Copy only the dep manifests first so this layer caches as long as composer.*
# files do not change. Application source is added in the next stage.
COPY src/composer.json src/composer.lock /app/

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------------------------------------------------------------------------
# Stage 2 — Build: app source + caches (routes, OpenAPI, DI compilation).
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli-alpine AS build

WORKDIR /app

# Minimum extensions needed for `bin/console` to run during the cache warm.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
 && docker-php-ext-install opcache \
 && apk del .build-deps

COPY src/ /app/
COPY --from=vendor /app/vendor /app/vendor

# Warm the persistent caches that the runtime will read on every request.
# Failures are not fatal: a missing route cache falls back to runtime scan;
# a missing OpenAPI dump only affects /api/v1/docs.json. Compile what we can.
RUN set -eux \
 && mkdir -p /app/var/cache \
 && (php bin/console route:cache || echo "[build] route:cache skipped"); \
    (php bin/console openapi:dump || echo "[build] openapi:dump skipped")

# ---------------------------------------------------------------------------
# Stage 3 — Runtime: PHP-FPM with opcache + JIT, no build tools.
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS runtime

# pdo_pgsql + pdo_mysql for the two supported DB drivers; opcache for prod.
RUN apk add --no-cache postgresql-libs \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
 && docker-php-ext-install pdo pdo_pgsql pdo_mysql opcache \
 && apk del .build-deps

COPY docker/php-fpm/conf.d/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php-fpm/conf.d/php-prod.ini /usr/local/etc/php/conf.d/zz-php-prod.ini
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

COPY --from=build --chown=www-data:www-data /app /var/www

USER www-data

EXPOSE 9000

# `pm.status_path` is wired in www.conf; nginx can poll it for liveness.
CMD ["php-fpm", "--nodaemonize"]
