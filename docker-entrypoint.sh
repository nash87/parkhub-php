#!/bin/bash
set -e

# Generate app key if not provided via environment and not already in .env
if [ -z "$APP_KEY" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
    php artisan key:generate --force
    echo "App key generated."
else
    echo "Using APP_KEY from environment."
    # Ensure .env exists so artisan commands work
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
fi

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Run migrations
php artisan migrate --force 2>/dev/null || true

# Prune expired Sanctum tokens (7 day expiry = 168 hours)
php artisan sanctum:prune-expired --hours=168 2>/dev/null || true

# Create default admin if none exists
# Credentials configurable via PARKHUB_ADMIN_EMAIL and PARKHUB_ADMIN_PASSWORD env vars
ADMIN_EMAIL="${PARKHUB_ADMIN_EMAIL:-admin@parkhub.local}"
ADMIN_PASSWORD="${PARKHUB_ADMIN_PASSWORD:-admin}"

php artisan tinker --execute="
\$email = getenv('PARKHUB_ADMIN_EMAIL') ?: 'admin@parkhub.local';
\$password = getenv('PARKHUB_ADMIN_PASSWORD') ?: 'admin';
if (\App\Models\User::where('role', 'admin')->orWhere('role', 'superadmin')->count() === 0) {
    \App\Models\User::create([
        'username' => 'admin',
        'email' => \$email,
        'password' => bcrypt(\$password),
        'name' => 'Admin',
        'role' => 'admin',
        'is_active' => true,
        'preferences' => json_encode(['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true]),
    ]);
    \App\Models\Setting::set('needs_password_change', 'true');
    echo 'Default admin created: ' . \$email;
} else {
    echo 'Admin already exists';
}" 2>/dev/null || true

# Demo mode: seed with realistic data on every fresh start
if [ "${DEMO_MODE}" = "true" ]; then
    echo "DEMO_MODE=true — running ProductionSimulationSeeder..."
    php artisan db:seed --class=ProductionSimulationSeeder --force 2>/dev/null || true
    echo "Demo data seeded."
fi

# Cache config for production
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

exec "$@"
