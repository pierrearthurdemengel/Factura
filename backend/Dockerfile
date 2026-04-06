FROM php:8.3-fpm-alpine

# Dépendances système + extensions PHP
RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    zip \
    nginx \
    supervisor \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql intl zip bcmath opcache gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# Répertoires nécessaires à Symfony + warm-up du cache
RUN mkdir -p var/cache var/log \
    && php bin/console cache:warmup --env=prod --no-debug || true \
    && chown -R www-data:www-data var/

# Configuration Nginx pour servir Symfony
RUN mkdir -p /run/nginx
COPY docker/nginx/fly.conf /etc/nginx/http.d/default.conf

# Configuration supervisord (Nginx + PHP-FPM dans un seul container)
COPY docker/supervisord.conf /etc/supervisord.conf

# Optimisation OPcache pour la production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
