FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-dev libzip-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql bcmath intl pcntl zip \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
