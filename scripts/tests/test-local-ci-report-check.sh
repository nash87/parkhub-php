#!/usr/bin/env bash
#
# Smoke test for scripts/check-local-ci-report.sh.
#
# The pre-push hook must stay fast because Git opens the remote receive-pack
# connection before running the hook. This test keeps that contract honest:
# the hook verifies a current local-ci success report instead of starting the
# full fop gate while github.com waits idle.
#
# Run: bash scripts/tests/test-local-ci-report-check.sh

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

expect_failure() {
    local name="$1"
    if bash scripts/check-local-ci-report.sh pr >"$tmp_dir/out" 2>&1; then
        red "    FAILED: ${name} unexpectedly passed"
        cat "$tmp_dir/out"
        exit 1
    fi
    green "    OK: ${name}"
}

echo "==> local-ci report check rejects missing reports"
rm -f "$report"
expect_failure "missing report"

echo "==> local-ci report check rejects failure reports"
mkdir -p "$(dirname "$report")"
cat > "$report" <<EOF
{
  "schema": "parkhub.local-ci.v1",
  "profile": "pr",
  "state": "failure",
  "commit": "$sha",
  "context": "fop/local-ci/pr"
}
EOF
expect_failure "failure report"

echo "==> local-ci report check rejects reports for a different commit"
cat > "$report" <<'EOF'
{
  "schema": "parkhub.local-ci.v1",
  "profile": "pr",
  "state": "success",
  "commit": "0000000000000000000000000000000000000000",
  "context": "fop/local-ci/pr"
}
EOF
expect_failure "wrong commit"

echo "==> local-ci report check accepts current success reports"
cat > "$report" <<EOF
{
  "schema": "parkhub.local-ci.v1",
  "profile": "pr",
  "state": "success",
  "commit": "$sha",
  "context": "fop/local-ci/pr"
}
EOF
if bash scripts/check-local-ci-report.sh pr >"$tmp_dir/out" 2>&1; then
    green "    OK"
else
    red "    FAILED: current success report was rejected"
    cat "$tmp_dir/out"
    exit 1
fi
