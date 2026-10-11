FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev libfreetype6-dev libicu-dev libjpeg62-turbo-dev \
        libonig-dev libpng-dev libwebp-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" curl dom exif gd intl mbstring mysqli opcache pdo_mysql simplexml xml xmlreader xmlwriter zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . ./
COPY docker/app/start.sh /usr/local/bin/mcms-start
COPY docker/app/uploads.ini /usr/local/etc/php/conf.d/mcms-uploads.ini

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p writable/cache writable/logs writable/session writable/modules-data/installed writable/modules-data/pending writable/modules-data/data writable/modules-data/core writable/themes-data/installed writable/themes-data/pending writable/themes-data/data \
    && chown -R www-data:www-data writable \
    && chmod 0755 /usr/local/bin/mcms-start

USER www-data
CMD ["/usr/local/bin/mcms-start"]
