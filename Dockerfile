FROM php:8.2-fpm-alpine3.20

RUN apk add --update --no-cache autoconf g++ make \
    && apk add linux-headers \
    && pecl install redis \
    && pecl install xdebug \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable xdebug \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
