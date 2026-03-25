#!/usr/bin/env bash

set -euo pipefail

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

mkdir -p bootstrap/cache database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

db_connection="${DB_CONNECTION:-sqlite}"
db_database="${DB_DATABASE:-database/database.sqlite}"

if [[ "${db_connection}" == "sqlite" && "${db_database}" != ":memory:" ]]; then
  mkdir -p "$(dirname "${db_database}")"
  touch "${db_database}"
fi

php artisan key:generate --force
php artisan config:clear

if [[ "${RUN_STORAGE_LINK:-0}" == "1" ]]; then
  php artisan storage:link || true
fi
