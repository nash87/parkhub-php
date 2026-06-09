#!/usr/bin/env bash
#
# TDD tests for nido/fop dual-path report resolution in
# scripts/check-local-ci-report.sh.
#
# Covers:
#   (a) report in .nido/reports/ is accepted
#   (b) report only in .fop/reports/ (fop-compat path) is still accepted
#   (c) report missing from BOTH paths fails
#
# Run: bash scripts/tests/test-nido-local-ci-report-check.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

sha="$(git rev-parse HEAD)"
nido_report=".nido/reports/local-ci-pr-${sha}.json"
fop_report=".fop/reports/local-ci-pr-${sha}.json"
tmp_dir="$(mktemp -d)"
nido_backup=""
fop_backup=""

cleanup() {
    rm -rf "$tmp_dir"
    # Restore .nido report if it was backed up
    if [[ -n "$nido_backup" && -f "$nido_backup" ]]; then
        mkdir -p "$(dirname "$nido_report")"
        cp -p "$nido_backup" "$nido_report"
        rm -f "$nido_backup"
    else
        rm -f "$nido_report"
    fi
    # Restore .fop report if it was backed up
    if [[ -n "$fop_backup" && -f "$fop_backup" ]]; then
        mkdir -p "$(dirname "$fop_report")"
        cp -p "$fop_backup" "$fop_report"
        rm -f "$fop_backup"
    else
        rm -f "$fop_report"
    fi
}
trap cleanup EXIT

if [[ -f "$nido_report" ]]; then
    nido_backup="$(mktemp)"
    cp -p "$nido_report" "$nido_backup"
fi
if [[ -f "$fop_report" ]]; then
    fop_backup="$(mktemp)"
    cp -p "$fop_report" "$fop_backup"
fi

red()   { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }

expect_pass() {
    local name="$1"
    if bash scripts/check-local-ci-report.sh pr >"$tmp_dir/out" 2>&1; then
        green "    OK: ${name}"
    else
        red "    FAILED: ${name} was unexpectedly rejected"
        cat "$tmp_dir/out"
        exit 1
    fi
}

expect_fail() {
    local name="$1"
    if bash scripts/check-local-ci-report.sh pr >"$tmp_dir/out" 2>&1; then
        red "    FAILED: ${name} unexpectedly passed"
        cat "$tmp_dir/out"
        exit 1
    fi
    green "    OK: ${name}"
}

# --- (c) missing both paths fails --------------------------------------------
echo "==> (c) missing both .nido and .fop reports fails"
rm -f "$nido_report" "$fop_report"
expect_fail "both reports missing"

# --- (b) fop-only report still accepted --------------------------------------
echo "==> (b) fop-only report (.fop/reports/) is accepted"
rm -f "$nido_report"
mkdir -p "$(dirname "$fop_report")"
cat > "$fop_report" <<EOF
{
  "schema": "parkhub.local-ci.v2",
  "profile": "pr",
  "state": "success",
  "commit": "$sha",
  "context": "fop/local-ci/pr"
}
EOF
expect_pass "fop-path report"

# --- (a) nido-path report accepted -------------------------------------------
echo "==> (a) nido-path report (.nido/reports/) is accepted"
rm -f "$nido_report" "$fop_report"
mkdir -p "$(dirname "$nido_report")"
cat > "$nido_report" <<EOF
{
  "schema": "parkhub.local-ci.v2",
  "profile": "pr",
  "state": "success",
  "commit": "$sha",
  "context": "nido/local-ci/pr"
}
EOF
expect_pass "nido-path report"

# --- nido-path takes precedence over a fop failure ---------------------------
echo "==> nido-path success wins over stale fop failure"
mkdir -p "$(dirname "$fop_report")"
cat > "$fop_report" <<EOF
{
  "schema": "parkhub.local-ci.v1",
  "profile": "pr",
  "state": "failure",
  "commit": "$sha",
  "context": "fop/local-ci/pr"
}
EOF
# nido_report is still the success from the previous test
expect_pass "nido success wins over fop failure"

# --- nido failure still fails ------------------------------------------------
echo "==> nido failure report is rejected even when fop success exists"
rm -f "$fop_report"
cat > "$nido_report" <<EOF
{
  "schema": "parkhub.local-ci.v2",
  "profile": "pr",
  "state": "failure",
  "commit": "$sha",
  "context": "nido/local-ci/pr"
}
EOF
mkdir -p "$(dirname "$fop_report")"
cat > "$fop_report" <<EOF
{
  "schema": "parkhub.local-ci.v2",
  "profile": "pr",
  "state": "success",
  "commit": "$sha",
  "context": "fop/local-ci/pr"
}
EOF
expect_fail "nido failure (fop success does not override)"
