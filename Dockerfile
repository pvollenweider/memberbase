FROM php:8.2-apache

# System packages + Apache modules
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        pdftk-java \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers expires deflate brotli

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN echo "error_reporting = E_ALL & ~E_NOTICE" > /usr/local/etc/php/conf.d/casa.ini

# Permissions
RUN mkdir -p /var/www/logs /var/www/conf && chown -R www-data:www-data /var/www/logs /var/www/conf

# Install PHP runtime dependencies (QR bill generation)
# html/ is bind-mounted in dev so this layer runs at build time;
# in dev, also run: docker compose exec php sh -c "cd /var/www/html && composer install --no-dev"
COPY html/composer.json /var/www/html/composer.json
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true
