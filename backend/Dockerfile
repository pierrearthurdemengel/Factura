FROM php:8.3-fpm-alpine

# Dépendances système + extensions PHP
RUN apk add --no-cache \
    postgresql-dev \
    postgresql-client \
    aws-cli \
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

# Configuration supervisord (Nginx + PHP-FPM + Messenger dans un seul container)
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/messenger-worker.sh /var/www/html/docker/messenger-worker.sh
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /var/www/html/docker/messenger-worker.sh \
    /var/www/html/docker/backup-db.sh \
    /usr/local/bin/entrypoint.sh

# Logs PHP vers stderr pour visibilite dans fly logs
RUN echo "error_log = /proc/self/fd/2" >> /usr/local/etc/php/conf.d/zz-logging.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/zz-logging.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/zz-logging.ini

# Optimisation OPcache pour la production (ajuste pour 256 Mo)
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=64" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM limite a 3 workers pour rester dans 256 Mo
RUN echo "[www]" > /usr/local/etc/php-fpm.d/zz-fly.conf \
    && echo "pm = static" >> /usr/local/etc/php-fpm.d/zz-fly.conf \
    && echo "pm.max_children = 3" >> /usr/local/etc/php-fpm.d/zz-fly.conf

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
