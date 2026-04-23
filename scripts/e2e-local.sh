#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVER_HOST="${SERVER_HOST:-127.0.0.1}"
SERVER_PORT="${SERVER_PORT:-8082}"
SERVER_LOG="${REPO_ROOT}/storage/logs/e2e-server.log"
STORAGE_LINK="${REPO_ROOT}/public/storage"
HAD_STORAGE_LINK=0

if [[ -e "${STORAGE_LINK}" ]]; then
  HAD_STORAGE_LINK=1
fi

cd "$REPO_ROOT"

export CI="${CI:-true}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-database/database.sqlite}"
export DEMO_MODE="${DEMO_MODE:-true}"
export E2E_BASE_URL="http://${SERVER_HOST}:${SERVER_PORT}"
export PARKHUB_ADMIN_EMAIL="${PARKHUB_ADMIN_EMAIL:-admin@parkhub.test}"
export PARKHUB_ADMIN_PASSWORD="${PARKHUB_ADMIN_PASSWORD:-demo}"
export PARKHUB_DISABLE_RATE_LIMITS="${PARKHUB_DISABLE_RATE_LIMITS:-true}"
export RUN_STORAGE_LINK="${RUN_STORAGE_LINK:-1}"

echo "== build browser assets for Laravel =="
npm run build:php --prefix parkhub-web

echo "== bootstrap Laravel =="
./scripts/ci/bootstrap-laravel.sh
php artisan migrate:fresh --seed --seeder=ProductionSimulationSeeder --force

echo "== start Laravel app on ${E2E_BASE_URL} =="
php artisan serve --host="${SERVER_HOST}" --port="${SERVER_PORT}" > "${SERVER_LOG}" 2>&1 &
SERVER_PID=$!
cleanup() {
  kill "${SERVER_PID}" 2>/dev/null || true
  if [[ "${HAD_STORAGE_LINK}" -ne 1 ]]; then
    rm -rf "${STORAGE_LINK}"
  fi
}
trap cleanup EXIT

./scripts/ci/wait-for-url.sh "${E2E_BASE_URL}/api/v1/health/live" 60

echo "== Playwright against ${E2E_BASE_URL} =="
npx playwright test "$@"
