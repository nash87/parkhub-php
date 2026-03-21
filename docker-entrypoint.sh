#!/bin/bash
set -e

echo "Starting ParkHub entrypoint script..."

# ────────────────────────────────────────────────
# 1. Configure Apache to listen on Render's $PORT
# ────────────────────────────────────────────────
if [ -n "$PORT" ]; then
    echo "Render PORT detected: $PORT — updating Apache configuration..."
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf 2>/dev/null || true
    # Update VirtualHost directive (more robust pattern)
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *: $PORT>/" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/*.conf 2>/dev/null || true
else
    echo "No PORT environment variable set — keeping Apache default (80)"
fi

# ────────────────────────────────────────────────
# 2. Handle APP_KEY generation / .env setup
# ────────────────────────────────────────────────
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "No valid APP_KEY provided → generating new key..."
    if [ ! -f .env ]; then
        echo "Creating .env from .env.example..."
        cp .env.example .env || { echo "ERROR: .env.example not found"; exit 1; }
    fi
    php artisan key:generate --force --no-interaction
    echo "New APP_KEY generated."
else
    echo "Using provided APP_KEY from environment variables."
    # Ensure .env exists even when key is provided externally
    [ ! -f .env ] && cp .env.example .env 2>/dev/null || true
fi

# ────────────────────────────────────────────────
# 3. Override selected .env values with Docker/Render env vars
# ────────────────────────────────────────────────
echo "Applying environment variable overrides to .env..."

declare -A overrides=(
    ["PARKHUB_ADMIN_EMAIL"]="$PARKHUB_ADMIN_EMAIL"
    ["PARKHUB_ADMIN_PASSWORD"]="$PARKHUB_ADMIN_PASSWORD"
    ["DEMO_MODE"]="$DEMO_MODE"
    ["DB_CONNECTION"]="$DB_CONNECTION"
    ["DB_HOST"]="$DB_HOST"
    ["DB_PORT"]="$DB_PORT"
    ["DB_DATABASE"]="$DB_DATABASE"
    ["DB_USERNAME"]="$DB_USERNAME"
    ["DB_PASSWORD"]="$DB_PASSWORD"
)

for key in "${!overrides[@]}"; do
