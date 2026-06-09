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
# nido-local-ci.sh (reached via fop-local-ci.sh shim) uses NIDO_LOCAL_CI_*
# primary env vars; FOP_LOCAL_CI_* are compat fallbacks also documented.
if .github/scripts/fop-local-ci.sh --help >"$tmp_dir/help" 2>&1 \
    && grep -q -- '--background' "$tmp_dir/help" \
    && grep -qE -- 'NIDO_LOCAL_CI_NO_DIFF_AWARE' "$tmp_dir/help"; then
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
# fop-local-ci.sh shim delegates to nido-local-ci.sh which prints
# "nido-local-ci backgrounded"; FOP_LOCAL_CI_BG_LOG_DIR maps to
# NIDO_LOCAL_CI_BG_LOG_DIR via compat fallback resolution.
FOP_LOCAL_CI_BG_LOG_DIR="$tmp_dir/bg" \
FOP_LOCAL_CI_DIFF_PATHS=$'docs/parkhub-notes.md' \
    .github/scripts/fop-local-ci.sh --profile pr --dry-run --background >"$tmp_dir/background" 2>&1

if ! grep -qE 'nido-local-ci backgrounded|fop-local-ci backgrounded' "$tmp_dir/background" \
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

for _ in $(seq 1 20); do
    if [[ -f "$bg_log" ]] && grep -q 'dry-run local CI completed; no success report or commit status was written' "$bg_log"; then
        green "    OK"
        exit 0
    fi
    sleep 0.1
done

red "    FAILED: background dry-run log did not finish"
cat "$tmp_dir/background"
[[ -f "$bg_log" ]] && cat "$bg_log"
exit 1
