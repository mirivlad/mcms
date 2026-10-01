FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev libfreetype6-dev libicu-dev libjpeg62-turbo-dev \
        libonig-dev libpng-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" curl dom exif gd intl mbstring mysqli opcache pdo_mysql simplexml xml xmlreader xmlwriter zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . ./
COPY docker/app/start.sh /usr/local/bin/mcms-start

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p writable/cache writable/logs writable/session public/assets/uploads \
    && chown -R www-data:www-data writable public/assets/uploads \
    && chmod 0755 /usr/local/bin/mcms-start

USER www-data
CMD ["/usr/local/bin/mcms-start"]
