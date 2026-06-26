FROM php:8.2-apache

# Extensions PHP
RUN docker-php-ext-install pdo pdo_mysql

# mod_rewrite
RUN a2enmod rewrite

# pdftk-java
RUN apt-get update && apt-get install -y pdftk-java && rm -rf /var/lib/apt/lists/*

# Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Permissions
RUN mkdir -p /var/www/logs && chown -R www-data:www-data /var/www/logs
