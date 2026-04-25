#!/usr/bin/env bash
#
# Smoke test for scripts/check-openapi-drift.sh and scripts/check-types-drift.sh.
#
# Asserts:
#   1. check-types-drift.sh exits 0 (no-op on PHP side).
#   2. check-openapi-drift.sh exits 0 on a clean tree.
#   3. check-openapi-drift.sh exits 1 when docs/openapi/php.json is mutated.
#
# We mock scripts/dump-openapi.sh so the test does not require a working
# PHP/Composer/Scramble environment — the gate logic is what we care about.
#
# Run: bash scripts/tests/test-drift-scripts.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

SNAPSHOT="docs/openapi/php.json"
DUMP="scripts/dump-openapi.sh"
DUMP_BACKUP=""
SNAPSHOT_BACKUP=""

# Save the current snapshot file (may contain WIP edits) so we can restore
# byte-for-byte after the test mutates it. NEVER use `git checkout` here —
# it would silently discard a developer's unrelated in-progress changes.
SNAPSHOT_BACKUP="$(mktemp)"
cp -p "$SNAPSHOT" "$SNAPSHOT_BACKUP"

cleanup() {
    if [[ -n "$DUMP_BACKUP" && -f "$DUMP_BACKUP" ]]; then
        cp -p "$DUMP_BACKUP" "$DUMP"
        rm -f "$DUMP_BACKUP"
    fi
    if [[ -n "$SNAPSHOT_BACKUP" && -f "$SNAPSHOT_BACKUP" ]]; then
        cp -p "$SNAPSHOT_BACKUP" "$SNAPSHOT"
        rm -f "$SNAPSHOT_BACKUP"
    fi
}
trap cleanup EXIT

red() { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }

echo "==> 1. check-types-drift.sh exits 0"
if bash scripts/check-types-drift.sh >/dev/null; then
    green "    OK"
else
    red "    FAILED: check-types-drift.sh did not exit 0"
    exit 1
fi

# Stub dump-openapi.sh — re-emit the committed snapshot byte-for-byte so the
# diff is empty in the clean case. Preserve perms on the backup so the
# original exec bit survives the restore.
DUMP_BACKUP="$(mktemp)"
cp -p "$DUMP" "$DUMP_BACKUP"
cat > "$DUMP" <<'STUB'
#!/usr/bin/env bash
# Test stub: re-emit the committed snapshot so drift gate sees no diff.
set -euo pipefail
git show HEAD:docs/openapi/php.json > docs/openapi/php.json
STUB
chmod +x "$DUMP"

echo "==> 2. check-openapi-drift.sh exits 0 on clean tree"
if SKIP_COMPOSER_DUMP=1 bash scripts/check-openapi-drift.sh >/dev/null 2>&1; then
    green "    OK"
else
    red "    FAILED: drift script flagged a clean tree as drifted"
    exit 1
fi

echo "==> 3. check-openapi-drift.sh exits 1 when snapshot is mutated"
# Mutate the snapshot to simulate drift. Re-stub dump-openapi.sh to re-emit
# the *committed* version (so the regen recreates the original) but our
# pre-mutation diff is what trips the gate. In practice, the check runs
# `dump` THEN `git diff`. So we need the stub to write something different
# from the committed file.
cat > "$DUMP" <<'STUB'
#!/usr/bin/env bash
# Test stub: write a divergent snapshot to simulate drift.
set -euo pipefail
echo '{"drift":"yes"}' > docs/openapi/php.json
STUB
chmod +x "$DUMP"

if SKIP_COMPOSER_DUMP=1 bash scripts/check-openapi-drift.sh >/dev/null 2>&1; then
    red "    FAILED: drift script returned 0 on mutated snapshot"
    exit 1
else
    green "    OK"
fi

green "All drift-script smoke tests passed."
