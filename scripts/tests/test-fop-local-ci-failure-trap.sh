#!/usr/bin/env bash
#
# Smoke test for .github/scripts/fop-local-ci.sh failure handling.
#
# The local CI script runs most checks through shell functions. Bash does not
# inherit ERR traps into functions unless errtrace is enabled, so a failed
# inner command can otherwise leave the GitHub fop/local-ci/pr status pending.
#
# Run: bash scripts/tests/test-fop-local-ci-failure-trap.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

sha="$(git rev-parse HEAD)"
report=".fop/reports/local-ci-pr-${sha}.json"
tmp_dir="$(mktemp -d)"
gh_log="$tmp_dir/gh-statuses.log"
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

cat > "$tmp_dir/composer" <<'STUB'
#!/usr/bin/env bash
echo "intentional composer failure for fop-local-ci ERR trap test" >&2
exit 42
STUB
chmod +x "$tmp_dir/composer"

cat > "$tmp_dir/gh" <<'STUB'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$GH_STATUS_LOG"
printf '{}\n'
STUB
chmod +x "$tmp_dir/gh"

red() { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }

echo "==> fop-local-ci posts failure when a run_step command fails"
set +e
PATH="$tmp_dir:$PATH" \
GH_STATUS_LOG="$gh_log" \
FOP_LOCAL_CI_NO_DIFF_AWARE=1 \
FOP_LOCAL_CI_DIRECT=1 \
FOP_LOCAL_CI_STATUS_REPO=nash87/parkhub-php \
    .github/scripts/fop-local-ci.sh --profile pr --post-status >/tmp/parkhub-fop-local-ci-failure-trap.out 2>&1
status=$?
set -e

if [[ "$status" -eq 0 ]]; then
    red "    FAILED: fop-local-ci returned 0 despite failing composer"
    cat /tmp/parkhub-fop-local-ci-failure-trap.out
    exit 1
fi

if [[ ! -f "$report" ]]; then
    red "    FAILED: failure report was not written"
    cat /tmp/parkhub-fop-local-ci-failure-trap.out
    exit 1
fi

if ! grep -q '"state": "failure"' "$report"; then
    red "    FAILED: failure report does not record failure state"
    cat "$report"
    exit 1
fi

if ! grep -q -- '-f state=failure' "$gh_log"; then
    red "    FAILED: failure commit status was not posted"
    cat "$gh_log"
    exit 1
fi

green "    OK"

cat > "$tmp_dir/fop" <<'STUB'
#!/usr/bin/env bash
set -euo pipefail

args=("$@")
command_start=-1
for i in "${!args[@]}"; do
    if [[ "${args[$i]}" == "--" ]]; then
        command_start=$((i + 1))
        break
    fi
done

if [[ "$command_start" -lt 0 || "$command_start" -ge "${#args[@]}" ]]; then
    echo "stub fop did not receive a wrapped command" >&2
    exit 2
fi

set +e
"${args[@]:$command_start}"
inner_status=$?
set -e

if [[ "$inner_status" -ne 0 ]]; then
    exit "$inner_status"
fi

echo "[FAIL] compact classifier false positive after inner command success" >&2
exit 1
STUB
chmod +x "$tmp_dir/fop"

for tool in composer npm python3 node actionlint yamllint gitleaks helm docker zizmor typos osv-scanner; do
    cat > "$tmp_dir/$tool" <<'STUB'
#!/usr/bin/env bash
exit 0
STUB
    chmod +x "$tmp_dir/$tool"
done

cat > "$tmp_dir/rg" <<'STUB'
#!/usr/bin/env bash
# Static wording/polish guards treat matches as failures, so this isolated
# wrapper test only needs "no matches" behavior before it reaches the fop stub.
exit 1
STUB
chmod +x "$tmp_dir/rg"

echo "==> fop-local-ci accepts a nonzero fop wrapper exit when the inner step marker is present"
set +e
PATH="$tmp_dir:/usr/bin:/bin" \
FOP_LOCAL_CI_QUEUE_BIN=fop \
FOP_LOCAL_CI_DIFF_PATHS=$'.github/workflows/security.yml' \
    .github/scripts/fop-local-ci.sh --profile pr >"$tmp_dir/fop-marker.out" 2>&1
status=$?
set -e

if [[ "$status" -ne 0 ]]; then
    red "    FAILED: fop-local-ci rejected a completed wrapped step because fop returned nonzero"
    cat "$tmp_dir/fop-marker.out"
    exit 1
fi

if ! grep -q 'completion marker was present' "$tmp_dir/fop-marker.out"; then
    red "    FAILED: fop-local-ci did not explain the marker-backed fop false positive"
    cat "$tmp_dir/fop-marker.out"
    exit 1
fi

green "    OK"
