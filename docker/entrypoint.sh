#!/bin/sh
# Starts cron (for the scheduled jobs in docker/crontab) alongside Apache,
# which the base php:8.2-apache image's own entrypoint doesn't do on its own.
set -e

touch /var/www/logs/cron.log
chown www-data:www-data /var/www/logs/cron.log
cron

exec apache2-foreground
