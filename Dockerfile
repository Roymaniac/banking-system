# syntax=docker/dockerfile:1.7

# Install PHP extensions once, then reuse the same PHP runtime for dependency
# installation and the final application image. This prevents Composer from
# resolving packages for a platform that differs from production.
FROM php:8.4-fpm-alpine AS php-base

WORKDIR /var/www/html

RUN apk add --no-cache \
        curl \
        icu-libs \
        libxml2 \
        libpq \
        libzip \
        oniguruma \
        sqlite-libs \
    && apk add --no-cache --virtual .build-dependencies \
        $PHPIZE_DEPS \
        curl-dev \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        simplexml \
        xml \
        xmlreader \
        xmlwriter \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-dependencies \
    && rm -rf /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-banking-system.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.conf

# Composer runs in a build-only stage, so it is not present in the smaller
# production image and cannot be used to change dependencies at runtime.
FROM php-base AS vendor

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache git unzip

COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --classmap-authoritative \
    && composer clear-cache

FROM php-base AS app

ARG APP_UID=10001
ARG APP_GID=10001

RUN addgroup -g "${APP_GID}" -S banking \
    && adduser -u "${APP_UID}" -S -D -G banking banking

COPY --from=vendor --chown=banking:banking /var/www/html /var/www/html
COPY --chown=banking:banking docker/php/entrypoint.sh /usr/local/bin/application-entrypoint

RUN chmod +x /usr/local/bin/application-entrypoint \
    && mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R banking:banking bootstrap/cache storage

USER banking

EXPOSE 9000

ENTRYPOINT ["application-entrypoint"]
CMD ["php-fpm", "--nodaemonize", "--fpm-config", "/usr/local/etc/php-fpm.conf"]

# Nginx contains only the public directory. Application source remains inside
# the private PHP container and cannot accidentally be served as a static file.
FROM nginx:1.28-alpine AS web

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public

USER nginx

EXPOSE 8080

