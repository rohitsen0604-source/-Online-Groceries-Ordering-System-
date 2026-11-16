#!/bin/bash
set -e

PORT="${PORT:-10000}"

# Adjust Apache configuration to listen on the dynamic port assigned by Render ($PORT)
sed -i -E "s/Listen [0-9]+/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Ensure upload directory permissions
mkdir -p /var/www/html/uploads/thumbnails /var/www/html/uploads/images
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

exec apache2-foreground
