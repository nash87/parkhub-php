#!/usr/bin/env bash
#
# Contract tests for .github/scripts/fop-local-ci.sh developer ergonomics.
#
# Run: bash scripts/tests/test-fop-local-ci-ergonomics.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

sha="$(git rev-parse HEAD)"
report=".fop/reports/local-ci-pr-${sha}.json"
tmp_dir="$(mktemp -d)"
report_backup=""

cleanup() {
    rm -rf "$tmp_dir"
    if [[ -n "$report_backup" && -f "$report_backup" ]]; then
        mkdir -p "$(dirname "$report")"
        cp -p "$report_backup" "$report"
        rm -f "$report_backup"
    else
        rm -f "$report"
    fi
}
trap cleanup EXIT

if [[ -f "$report" ]]; then
    report_backup="$(mktemp)"
    cp -p "$report" "$report_backup"
fi

red() { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }

echo "==> fop-local-ci advertises background and diff-aware controls"
if .github/scripts/fop-local-ci.sh --help >"$tmp_dir/help" 2>&1 \
    && grep -q -- '--background' "$tmp_dir/help" \
    && grep -q -- 'FOP_LOCAL_CI_NO_DIFF_AWARE' "$tmp_dir/help"; then
    green "    OK"
else
    red "    FAILED: help output is missing ergonomics flags"
    cat "$tmp_dir/help"
    exit 1
fi

echo "==> fop-local-ci pr dry-run skips untouched PHP and frontend gates"
rm -f "$report"
FOP_LOCAL_CI_DIFF_PATHS=$'docs/parkhub-notes.md\nREADME.md' \
    .github/scripts/fop-local-ci.sh --profile pr --dry-run >"$tmp_dir/dry-run" 2>&1

for pattern in \
    'diff-aware' \
    'composer validate .*SKIP: diff-aware: no PHP backend inputs touched' \
    'frontend vitest .*SKIP: diff-aware: no frontend/e2e inputs touched' \
    'dry-run local CI completed; no success report or commit status was written'; do
    if ! grep -Eq "$pattern" "$tmp_dir/dry-run"; then
        red "    FAILED: dry-run output missing pattern: $pattern"
        cat "$tmp_dir/dry-run"
        exit 1
    fi
done

if [[ -f "$report" ]]; then
    red "    FAILED: dry-run wrote a local-ci success report"
    cat "$report"
    exit 1
fi

green "    OK"

echo "==> fop-local-ci pr dry-run prepares Composer deps before design smoke"
FOP_LOCAL_CI_DIFF_PATHS=$'package-lock.json\nparkhub-web/package-lock.json' \
    .github/scripts/fop-local-ci.sh --profile pr --dry-run >"$tmp_dir/design-smoke-dry-run" 2>&1

for pattern in \
    'composer install for Laravel design smoke' \
    'frontend route \+ v5 design smoke'; do
    if ! grep -Eq "$pattern" "$tmp_dir/design-smoke-dry-run"; then
        red "    FAILED: design-smoke dry-run output missing pattern: $pattern"
        cat "$tmp_dir/design-smoke-dry-run"
        exit 1
    fi
done

green "    OK"

echo "==> fop-local-ci background mode returns a PID and writes a log"
FOP_LOCAL_CI_BG_LOG_DIR="$tmp_dir/bg" \
FOP_LOCAL_CI_DIFF_PATHS=$'docs/parkhub-notes.md' \
    .github/scripts/fop-local-ci.sh --profile pr --dry-run --background >"$tmp_dir/background" 2>&1

if ! grep -q 'fop-local-ci backgrounded' "$tmp_dir/background" \
    || ! grep -Eq 'PID=[0-9]+' "$tmp_dir/background"; then
    red "    FAILED: background output missing PID/log details"
    cat "$tmp_dir/background"
    exit 1
fi

bg_log="$(grep -Eo 'log=[^ ]+' "$tmp_dir/background" | head -1 | cut -d= -f2-)"
if [[ -z "$bg_log" ]]; then
    red "    FAILED: background output did not include a log path"
    cat "$tmp_dir/background"
    exit 1
fi

bg_ok=0
for _ in $(seq 1 20); do
    if [[ -f "$bg_log" ]] && grep -q 'dry-run local CI completed; no success report or commit status was written' "$bg_log"; then
        bg_ok=1
        break
    fi
    sleep 0.1
done

if [[ "$bg_ok" -eq 1 ]]; then
    green "    OK"
else
    red "    FAILED: background dry-run log did not finish"
    cat "$tmp_dir/background"
    [[ -f "$bg_log" ]] && cat "$bg_log"
    exit 1
fi

# ── Fix 1: free-port allocator ───────────────────────────────────────────────
# Verify allocate_laravel_port() honours FOP_LOCAL_CI_LARAVEL_PORT and
# SERVER_PORT env vars.  These tests source a stripped-down harness rather than
# executing fop-local-ci.sh end-to-end, because the allocator is a shell
# function defined early in the script.

echo "==> allocate_laravel_port honours FOP_LOCAL_CI_LARAVEL_PORT"
# Source just the allocator function by running the script in dry-run mode
# with a pre-set diff path that skips all heavy gates, then check the output.
FOP_LOCAL_CI_LARAVEL_PORT=59999 \
FOP_LOCAL_CI_DIFF_PATHS=$'docs/parkhub-notes.md' \
    .github/scripts/fop-local-ci.sh --profile pr --dry-run >"$tmp_dir/port-override" 2>&1
if grep -q 'Laravel dev-server port: 59999' "$tmp_dir/port-override"; then
    green "    OK (port: 59999 from FOP_LOCAL_CI_LARAVEL_PORT)"
else
    red "    FAILED: FOP_LOCAL_CI_LARAVEL_PORT=59999 not reflected in output"
    grep 'Laravel' "$tmp_dir/port-override" || true
    exit 1
fi

echo "==> allocate_laravel_port honours SERVER_PORT env var"
SERVER_PORT=58888 \
FOP_LOCAL_CI_DIFF_PATHS=$'docs/parkhub-notes.md' \
    .github/scripts/fop-local-ci.sh --profile pr --dry-run >"$tmp_dir/server-port-override" 2>&1
if grep -q 'Laravel dev-server port: 58888' "$tmp_dir/server-port-override"; then
    green "    OK (port: 58888 from SERVER_PORT)"
else
    red "    FAILED: SERVER_PORT=58888 not reflected in output"
    grep 'Laravel' "$tmp_dir/server-port-override" || true
    exit 1
fi

# ── Fix 2: zizmor advisory semantics ─────────────────────────────────────────
# Verify that fop-local-ci advertises run_advisory_step and that the zizmor
# block uses run_advisory_step (not run_step) so fop build post-command gate
# failures for advisory findings do not propagate as hard CI failures.

echo "==> fop-local-ci.sh uses run_advisory_step for zizmor"
if grep -q 'run_advisory_step "zizmor' .github/scripts/fop-local-ci.sh; then
    green "    OK (zizmor routed through run_advisory_step)"
else
    red "    FAILED: zizmor still uses run_step — fop post-command gate will false-fail on advisory findings"
    grep 'run.*zizmor' .github/scripts/fop-local-ci.sh || true
    exit 1
fi

echo "==> fop-local-ci dry-run with workflow-touching diff succeeds even when zizmor would be advisory"
# Simulate a workflow-touching diff; dry-run must complete without error even
# though zizmor is advisory (the new run_advisory_step absorbs non-zero).
FOP_LOCAL_CI_DIFF_PATHS=$'.github/workflows/ci.yml' \
    .github/scripts/fop-local-ci.sh --profile pr --dry-run >"$tmp_dir/zizmor-dry-run" 2>&1
if grep -q 'dry-run local CI completed' "$tmp_dir/zizmor-dry-run"; then
    green "    OK (dry-run completed with workflow-touching diff)"
else
    red "    FAILED: dry-run with workflow diff did not complete"
    cat "$tmp_dir/zizmor-dry-run"
    exit 1
fi

green "All ergonomics + port-allocator + zizmor-advisory tests passed."
