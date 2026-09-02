#!/usr/bin/env bash
set -e

# Ensure .env exists
if [ ! -f /var/www/.env ]; then
    echo "Creating .env from .env.example..."
    cp /var/www/.env.example /var/www/.env
fi

# Ensure application key is set
if ! grep -q "APP_KEY=base64" /var/www/.env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Ensure SQLite database exists
if [ ! -f /var/www/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/database/database.sqlite
fi

# Run database migrations and seeders if needed
echo "Running migrations..."
php artisan migrate --force

echo "Checking seed state..."
php artisan db:seed --force

echo "Optimizing application configuration..."
php artisan config:cache
php artisan route:cache

echo "Starting application..."
exec "$@"
