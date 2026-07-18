FROM php:7.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mysqli \
    && rm -rf /var/lib/apt/lists/*

# Match the host user (uid 1000) so the web installer can write
# config/ and install/installconfig/ on the bind mount
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

COPY php.ini /usr/local/etc/php/conf.d/travian.ini
