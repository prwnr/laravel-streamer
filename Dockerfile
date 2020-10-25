FROM php:fpm-alpine3.12

RUN apk add --update --no-cache autoconf g++ make \
    && pecl install redis \
    && pecl install xdebug \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable xdebug