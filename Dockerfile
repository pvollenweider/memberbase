FROM php:8.2-apache

# System packages + Apache modules
# libpng-dev / libjpeg62-turbo-dev / libfreetype6-dev: required by the GD extension
# (used by endroid/qr-code for QR PNG rendering, vendored in html/vendor/)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        pdftk-java \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers expires deflate brotli

# Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN echo "error_reporting = E_ALL & ~E_NOTICE" > /usr/local/etc/php/conf.d/casa.ini

# Scheduled jobs (issue #150) — task generation + digest email, see
# html/tools/cron.php. docker/crontab runs it every 5 min for local dev
# convenience; production should use the daily cadence documented there.
COPY docker/crontab /etc/cron.d/memberbase
RUN chmod 0644 /etc/cron.d/memberbase
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissions
RUN mkdir -p /var/www/logs /var/www/conf && chown -R www-data:www-data /var/www/logs /var/www/conf

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
