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

# Create default admin if none exists (needed for Rust frontend onboarding)
php artisan tinker --execute="
if (\App\Models\User::where('role', 'admin')->orWhere('role', 'superadmin')->count() === 0) {
    \App\Models\User::create([
        'username' => 'admin',
        'email' => 'admin@parkhub.local',
        'password' => bcrypt('admin'),
        'name' => 'Admin',
        'role' => 'admin',
        'is_active' => true,
        'preferences' => json_encode(['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true]),
    ]);
    \App\Models\Setting::set('needs_password_change', 'true');
    echo 'Default admin created (password: changeme)';
} else {
    echo 'Admin already exists';
}" 2>/dev/null || true

# Cache config for production
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

exec "$@"
