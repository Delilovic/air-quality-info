FROM php:7.3-fpm-alpine

RUN apk add zlib-dev libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql

RUN sed -i 's/^pm.max_children.*/pm.max_children = 40/' /usr/local/etc/php-fpm.d/www.conf \
   && echo "request_slowlog_timeout = 5s" >> /usr/local/etc/php-fpm.d/www.conf \
   && echo "slowlog = /usr/local/var/log/php-fpm/slow.log" >> /usr/local/etc/php-fpm.d/www.conf \
   && mkdir /usr/local/var/log/php-fpm \
   && chown www-data:www-data /usr/local/var/log/php-fpm

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"