FROM php:8.3-cli-alpine

# Install system dependencies & PHP build tools
RUN apk add --no-cache \
    sqlite \
    sqlite-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    icu-dev \
    bash \
    curl \
    git \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_sqlite \
        bcmath \
        mbstring \
        zip \
        intl \
        opcache

# Get latest Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . /var/www

# Configure Composer platform & install production dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Create SQLite database file and set permissions
RUN mkdir -p /var/www/database /var/www/storage/framework/{sessions,views,cache} /var/www/storage/logs \
    && touch /var/www/database/database.sqlite \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
