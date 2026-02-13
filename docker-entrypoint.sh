#!/bin/bash
set -e

# Generate app key if not set
if [ -z "$APP_KEY" ] && [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Override .env with environment variables if provided
if [ ! -z "$APP_KEY" ]; then
    echo "Using environment variables for configuration"
fi

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Run migrations
php artisan migrate --force 2>/dev/null || true

# Cache config for production
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

exec "$@"
