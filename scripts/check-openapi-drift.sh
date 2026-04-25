#!/usr/bin/env bash
#
# Local CI gate: regenerate the PHP OpenAPI snapshot and fail if it
# differs from the committed `docs/openapi/php.json`.
#
# Mirrors the GitHub Actions "OpenAPI Drift" job so devs catch drift
# before push instead of after a 15-min CI round-trip.
#
# Notes:
#   - Scramble has a known silent-drop bug if `composer dump-autoload`
#     is stale; we always run it first.
#   - `scripts/dump-openapi.sh` swaps .env <-> .env.example for the
#     dump so local feature-flag fiddling doesn't corrupt the snapshot.
#   - Exit code is the only contract: 0 = clean, 1 = drift / failure.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SNAPSHOT="docs/openapi/php.json"

cd "$REPO_ROOT"

if [[ ! -f "$SNAPSHOT" ]]; then
    echo "error: $SNAPSHOT not present — committed OpenAPI snapshot is missing." >&2
    exit 1
fi

if [[ ! -x "$SCRIPT_DIR/dump-openapi.sh" ]]; then
    echo "error: scripts/dump-openapi.sh missing or not executable." >&2
    exit 1
fi

# Ensure autoloader is fresh — Scramble silently drops endpoints when
# the autoload map is stale after route changes. Set SKIP_COMPOSER_DUMP=1
# to bypass (used by the script smoke tests, which mock dump-openapi.sh).
if [[ "${SKIP_COMPOSER_DUMP:-0}" != "1" ]]; then
    composer dump-autoload --quiet
fi

# Regenerate (writes to docs/openapi/php.json in place).
"$SCRIPT_DIR/dump-openapi.sh" >/dev/null

if git diff --quiet -- "$SNAPSHOT"; then
    echo "OpenAPI snapshot in sync."
    exit 0
fi

echo "::error:: OpenAPI drift detected in $SNAPSHOT"
echo
echo "The committed spec does not match the regenerated one. To fix:"
echo
echo "  composer dump-autoload"
echo "  ./scripts/dump-openapi.sh"
echo "  git add $SNAPSHOT"
echo
echo "Diff (truncated):"
git --no-pager diff --stat -- "$SNAPSHOT"
git --no-pager diff -- "$SNAPSHOT" | head -60
exit 1
