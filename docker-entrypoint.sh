#!/bin/bash
set -e

# ---------------------------------------------------------------------------
# Apache runtime identity
#
# The php:*-apache base image writes `User ${APACHE_RUN_USER}` /
# `Group ${APACHE_RUN_GROUP}` into apache2.conf and defines those variables
# in /etc/apache2/envvars, which `apache2-foreground` sources before exec'ing
# httpd. If Apache is ever started without sourcing envvars -- a `command:`
# override in docker-compose, a different orchestrator, or a base-image swap
# -- those variables are undefined and Apache falls back to its built-in
# default user (`daemon` on upstream httpd builds).
#
# This script used to hardcode `www-data`. When the two disagreed, every
# Laravel writable path (storage/, bootstrap/cache) ended up owned by a user
# the workers are not, so any request touching the session, cache or log
# failed with `permission denied` and the app returned 500. Worse, because
# this script re-applied the wrong ownership on every container start, an
# operator's manual `chown` appeared to "revert" on restart -- which is
# exactly the symptom reported in nash87/parkhub-php#578.
#
# Resolve the identity Apache will actually use instead of assuming it.
# Paths are overridable so the resolution logic can be tested off-container.
# ---------------------------------------------------------------------------
APACHE_ENVVARS_FILE="${APACHE_ENVVARS_FILE:-/etc/apache2/envvars}"
APACHE_CONF_FILE="${APACHE_CONF_FILE:-/etc/apache2/apache2.conf}"

# Echoes "user:group". Precedence: envvars file, then the ambient
# environment, then a literal User/Group directive in apache2.conf, then the
# Debian default. A directive that is still an unexpanded ${...} placeholder
# is ignored -- it tells us nothing about the effective user.
resolve_apache_identity() {
    local user="" group=""

    if [ -r "$APACHE_ENVVARS_FILE" ]; then
        user="$( . "$APACHE_ENVVARS_FILE" >/dev/null 2>&1; printf '%s' "${APACHE_RUN_USER:-}" )"
        group="$( . "$APACHE_ENVVARS_FILE" >/dev/null 2>&1; printf '%s' "${APACHE_RUN_GROUP:-}" )"
    fi

    [ -z "$user" ] && user="${APACHE_RUN_USER:-}"
    [ -z "$group" ] && group="${APACHE_RUN_GROUP:-}"

    if [ -z "$user" ] && [ -r "$APACHE_CONF_FILE" ]; then
        user="$(awk 'tolower($1)=="user" && $2 !~ /\$\{/ {v=$2} END{if (v) print v}' "$APACHE_CONF_FILE")"
    fi
    if [ -z "$group" ] && [ -r "$APACHE_CONF_FILE" ]; then
        group="$(awk 'tolower($1)=="group" && $2 !~ /\$\{/ {v=$2} END{if (v) print v}' "$APACHE_CONF_FILE")"
    fi

    [ -z "$user" ] && user="www-data"
    [ -z "$group" ] && group="$user"

    printf '%s:%s' "$user" "$group"
}

# Allow the test suite to source this file for the helpers alone.
if [ "${PARKHUB_ENTRYPOINT_LIB_ONLY:-}" = "1" ]; then
    return 0 2>/dev/null || exit 0
fi

RUNTIME_IDENTITY="$(resolve_apache_identity)"
RUNTIME_USER="${RUNTIME_IDENTITY%%:*}"
RUNTIME_GROUP="${RUNTIME_IDENTITY##*:}"
echo "Apache runtime identity resolved to ${RUNTIME_USER}:${RUNTIME_GROUP}"

# Make the Laravel-writable paths owned by whoever Apache actually runs as.
# Failures are reported instead of swallowed: a silent chown failure here is
# indistinguishable, hours later, from an application bug.
ensure_writable_paths() {
    local failed=0
    chown -R "${RUNTIME_USER}:${RUNTIME_GROUP}" "$@" || failed=1
    chmod -R 775 "$@" || failed=1
    if [ "$failed" -ne 0 ]; then
        echo "WARNING: could not fully apply ${RUNTIME_USER}:${RUNTIME_GROUP} ownership to: $*" >&2
    fi
}

# Prove the runtime user can actually write, rather than assuming the chown
# above was sufficient (bind mounts, restrictive volume drivers and
# read-only filesystems all defeat it). Failing here with a precise message
# beats serving opaque 500s from every session-backed route.
assert_writable_as_runtime_user() {
    local target="$1"
    command -v gosu >/dev/null 2>&1 || return 0
    if gosu "$RUNTIME_USER" sh -c "test -w '$target'" 2>/dev/null; then
        return 0
    fi
    echo "FATAL: ${target} is not writable by the Apache runtime user (${RUNTIME_USER})." >&2
    echo "       Laravel cannot write sessions, caches or logs, so every request would 500." >&2
    echo "       Current ownership:" >&2
    ls -ld "$target" >&2 || true
    echo "       If this path is a bind mount, chown it on the host to ${RUNTIME_USER}:${RUNTIME_GROUP}" >&2
    echo "       or run the container with a matching --user." >&2
    exit 1
}

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
ensure_writable_paths storage bootstrap/cache database

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
ensure_writable_paths storage bootstrap/cache

# Everything above ran as root. Confirm the workers can still write before
# handing control to Apache.
assert_writable_as_runtime_user storage/logs
assert_writable_as_runtime_user storage/framework/sessions
assert_writable_as_runtime_user bootstrap/cache

# Start Laravel scheduler in background (needed for auto-release, demo
# resets, etc.). Run the scheduler as www-data too so it doesn't
# re-introduce files under storage/ that the workers cannot rewrite.
(while true; do gosu "$RUNTIME_USER" php artisan schedule:run --no-interaction >> storage/logs/scheduler.log 2>&1; sleep 60; done) &

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
