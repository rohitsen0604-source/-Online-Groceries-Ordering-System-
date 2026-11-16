FROM php:8.2-apache

# Install system dependencies and PHP extensions for MySQL & SQLite
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Configure PHP settings to populate $_ENV from environment variables
RUN echo "variables_order = 'EGPCS'" >> "$PHP_INI_DIR/php.ini-production" \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Copy and set permissions for entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create uploads directories and set appropriate permissions
RUN mkdir -p /var/www/html/uploads/thumbnails /var/www/html/uploads/images \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["docker-entrypoint.sh"]
