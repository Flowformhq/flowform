FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite-libs \
    libpng-dev \
    oniguruma-dev \
    libzip-dev \
    linux-headers \
    curl \
    git \
    bash

RUN docker-php-ext-install \
    pdo_sqlite \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    zip \
    gd \
    bcmath \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY .docker/php/php.ini /usr/local/etc/php/conf.d/flowform.ini
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY .docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY .docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan filament:assets \
    && mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache

RUN touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
