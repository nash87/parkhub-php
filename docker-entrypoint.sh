#!/bin/bash
set -e

# Configure Apache port from PORT env var (default: 10000 for Render, override for self-hosting)
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf 2>/dev/null || true
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/*.conf 2>/dev/null || true
fi

# Ensure .env exists so artisan commands work
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate app key if not provided via environment
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force --no-interaction
    echo "App key generated."
else
    echo "Using APP_KEY from environment."
fi

# Override .env with Docker env vars (env vars take precedence over .env.example defaults)
env_vars=(
    "PARKHUB_ADMIN_EMAIL"
    "PARKHUB_ADMIN_PASSWORD"
    "DEMO_MODE"
    "DB_CONNECTION"
    "DB_HOST"
    "DB_PORT"
    "DB_DATABASE"
    "DB_USERNAME"
    "DB_PASSWORD"
)

for var in "${env_vars[@]}"; do
    value="${!var}"
    if [ -n "$value" ]; then
        if grep -q "^${var}=" .env 2>/dev/null; then
            sed -i "s|^${var}=.*|${var}=${value}|" .env
        else
            echo "${var}=${value}" >> .env
        fi
    fi
done

# Support DATABASE_URL (e.g. from Render PostgreSQL addon)
[ -n "$DATABASE_URL" ] && echo "DATABASE_URL present — Laravel will use it preferentially."

# Ensure storage directories exist with correct permissions
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Demo mode: fresh DB + seed with realistic data on every container start
# SEED_DEMO_DATA=true: seed data once in production mode (no demo UI/auto-reset)
# Non-demo: incremental migrations only
if [ "${DEMO_MODE}" = "true" ] || [ "${SEED_DEMO_DATA}" = "true" ]; then
    echo "DEMO_MODE=true — running migrate:fresh + ProductionSimulationSeeder..."
    php artisan migrate:fresh --force --no-interaction 2>&1 || { echo "WARNING: Migrations failed"; }
    php artisan db:seed --class=ProductionSimulationSeeder --force --no-interaction 2>&1 || { echo "WARNING: Demo seeding failed"; }
    echo "Demo data seeded."
else
    echo "Running migrations..."
    php artisan migrate --force --no-interaction 2>&1 || { echo "WARNING: Migrations failed"; }
    # Create default admin if none exists (works without tinker in --no-dev)
    php artisan parkhub:create-admin --no-interaction 2>&1 || true
fi

# Generate VAPID keys for push notifications (once)
php artisan vapid:generate 2>&1 || true

# Prune expired Sanctum tokens (7 day expiry = 168 hours)
php artisan sanctum:prune-expired --hours=168 --no-interaction 2>&1 || true

# Clear old cache then rebuild — ensures Docker env vars are picked up
php artisan config:clear --no-interaction 2>&1 || true
php artisan route:clear --no-interaction 2>&1 || true
php artisan view:clear --no-interaction 2>&1 || true
php artisan config:cache --no-interaction 2>&1 || true
php artisan route:cache --no-interaction 2>&1 || true

# Every artisan command above ran as root (the entrypoint runs as root so
# it can touch /etc/apache2/* and later `exec apache2-foreground`). Apache's
# prefork workers run as www-data, so anything those artisan commands
# wrote — fresh config cache, route cache, cache subdirectories created
# during migrate:fresh + seed, vapid key files — has to be owned by
# www-data or the worker can't read/update it. Laravel's file cache
# failing with `file_put_contents` permission errors on /api/v1/discover
# (and any other endpoint that touches the cache) was a direct
# consequence of the mixed ownership.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Start Laravel scheduler in background (needed for auto-release, demo
# resets, etc.). Run the scheduler as www-data too so it doesn't
# re-introduce root-owned files under storage/ at runtime.
(while true; do gosu www-data php artisan schedule:run --no-interaction >> storage/logs/scheduler.log 2>&1; sleep 60; done) &

# Run Apache as root.
#
# A previous revision used `gosu www-data` here to satisfy a CodeQL
# "container-running-as-root" alert. On the php:8.4-apache base image,
# /var/log/apache2/error.log is a symlink to /proc/self/fd/2 which is
# owned by root and not writable by www-data, so dropping privileges
# made Apache fail with "AH00091: could not open error log file".
# Apache's own mpm_prefork still forks workers as www-data at runtime,
# so the master process needs root to open the log symlink.
exec "$@"
