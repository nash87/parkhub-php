#!/bin/bash
set -e

echo "Starting ParkHub entrypoint script..."

# 1. Configure Apache to use Render's PORT
if [ -n "$PORT" ]; then
    echo "Render PORT detected: $PORT — updating Apache configuration..."
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf 2>/dev/null || true
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/*.conf 2>/dev/null || true
else
    echo "No PORT set — Apache stays on default port 80"
fi

# 2. APP_KEY & .env basics
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" == "base64:"* ]]; then
    echo "No valid APP_KEY found → generating new one..."
    [ ! -f .env ] && cp .env.example .env || true
    php artisan key:generate --force --no-interaction
else
    echo "Using APP_KEY from environment variables."
    [ ! -f .env ] && cp .env.example .env 2>/dev/null || true
fi

# 3. Override .env with important Render env vars
echo "Applying environment overrides to .env file..."
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
        echo "  → Overrode ${var}"
    fi
done

# Prefer DATABASE_URL if set (Laravel native parsing)
[ -n "$DATABASE_URL" ] && echo "DATABASE_URL present — Laravel will use it preferentially"

# 4. Ensure directories & permissions
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 5. Database setup
if [ "${DEMO_MODE}" = "true" ]; then
    echo "DEMO_MODE=true → fresh migration + seeding..."
    php artisan migrate:fresh --force --no-interaction || echo "WARNING: migrate:fresh failed"
    php artisan db:seed --class=ProductionSimulationSeeder --force --no-interaction || echo "WARNING: Seeding failed"
else
    echo "Running standard migrations..."
    php artisan migrate --force --no-interaction || echo "WARNING: Migrations failed"
    php artisan parkhub:create-admin --no-interaction 2>/dev/null || true
fi

# 6. Maintenance tasks
php artisan vapid:generate --force 2>/dev/null || true
php artisan sanctum:prune-expired --hours=168 --no-interaction 2>/dev/null || true

# 7. Cache
echo "Optimizing configuration & routes..."
php artisan config:clear  --no-interaction 2>/dev/null || true
php artisan route:clear   --no-interaction 2>/dev/null || true
php artisan view:clear    --no-interaction 2>/dev/null || true
php artisan config:cache  --no-interaction
php artisan route:cache   --no-interaction || true

# 8. Background scheduler (for cron-like tasks on free tier)
echo "Launching scheduler in background..."
(while true; do
    php artisan schedule:run --no-interaction >> storage/logs/scheduler.log 2>&1
    sleep 60
done) &

# 9. Start Apache
echo "Starting Apache server..."
exec "$@"
