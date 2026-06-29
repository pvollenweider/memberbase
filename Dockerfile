FROM php:8.2-apache

# Extensions PHP
RUN docker-php-ext-install pdo pdo_mysql

# System packages + Apache modules
RUN apt-get update \
    && apt-get install -y --no-install-recommends pdftk-java \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers expires deflate brotli

# Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN echo "error_reporting = E_ALL & ~E_NOTICE" > /usr/local/etc/php/conf.d/casa.ini

# Permissions
RUN mkdir -p /var/www/logs /var/www/conf && chown -R www-data:www-data /var/www/logs /var/www/conf
