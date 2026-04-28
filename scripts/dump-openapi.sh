#!/usr/bin/env bash
#
# Dump the current PHP OpenAPI spec into docs/openapi/php.json via
# `php artisan scramble:export`.
#
# Intended for developer use before committing an API change: run this,
# commit the updated docs/openapi/php.json alongside the code, and the
# PR diff will show the contract change.
#
# The dump always runs against .env.example so developers can't produce
# a snapshot that drifts from what the CI drift gate regenerates. Any
# module-flag experimentation in your local .env is temporarily shelved.

set -euo pipefail

OUT="docs/openapi/php.json"

mkdir -p "$(dirname "$OUT")"

# Swap .env with .env.example for the duration of the dump so scramble
# sees the shipped-default feature flags.
RESTORE=0
DUMP_DB=""
if [ -f .env ]; then
    cp .env .env.dump-openapi.bak
    RESTORE=1
fi
cleanup() {
    if [ "$RESTORE" = "1" ]; then
        mv -f .env.dump-openapi.bak .env
    else
        rm -f .env.dump-openapi.bak
    fi
    if [ -n "$DUMP_DB" ]; then
        rm -f "$DUMP_DB"
    fi
}
trap cleanup EXIT
cp .env.example .env

# Scramble walks Eloquent models at export time, which triggers SQLite schema
# introspection. Keep the dump script self-contained so fresh worktrees and
# pre-push hooks do not depend on CI-only bootstrap steps or mutate a local DB.
DUMP_DB="$(mktemp "${TMPDIR:-/tmp}/parkhub-openapi.XXXXXX.sqlite")"
php artisan config:clear --quiet
php artisan key:generate --force --quiet
DB_CONNECTION=sqlite DB_DATABASE="$DUMP_DB" php artisan migrate --graceful --force --quiet

# Scramble's reflection-based spec generation crosses the default 128M
# limit on larger API surfaces. Raise it explicitly so fresh clones work
# without the developer pre-patching php.ini.
DB_CONNECTION=sqlite DB_DATABASE="$DUMP_DB" php -d memory_limit=1G artisan scramble:export --path="$OUT"

# Pretty-print + normalise key ordering so diffs stay readable
jq -S '.' "$OUT" > "$OUT.tmp" && mv "$OUT.tmp" "$OUT"

echo "wrote $OUT ($(wc -c < "$OUT") bytes, $(jq '.paths | length' "$OUT") paths)"
