#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage: .github/scripts/fop-local-ci.sh [--profile pr|full|cd] [--dry-run] [--post-status]

Runs ParkHub-PHP's local-first CI through fop's build queue. The optional
--post-status flag publishes the commit status context for the selected
profile. The GitHub PR attestation gate expects this exact command:

  .github/scripts/fop-local-ci.sh --profile pr --post-status

Profiles:
  pr    Fast PR gate: composer + Pint + PHPStan + PHPUnit + Vitest +
        Astro tsc/build + Composer audit.
  full  PR gate plus Schemathesis contract fuzz (best effort), Infection
        mutation testing, and Playwright e2e smoke.
  cd    Release-oriented preflight: full + composer-audit prod-only +
        Trivy filesystem scan when available.

Environment overrides:
  FOP_LOCAL_CI_STATUS_REPO  owner/repo for status post (else autodetected
                            from git remotes named github → upstream → origin).
  FOP_LOCAL_CI_DIRECT       1 = bypass the `fop build` queue wrapper and
                            run each step directly in the current shell.
                            Use only for the bootstrap chicken-and-egg
                            run that introduces this script, or when
                            you have explicit reason to skip the queue.
                            Operators must guarantee memory headroom
                            themselves in this mode.
EOF
}

profile="pr"
dry_run=0
post_status=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --profile)
      profile="${2:?missing profile}"
      shift 2
      ;;
    --dry-run)
      dry_run=1
      shift
      ;;
    --post-status)
      post_status=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "unknown argument: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

case "$profile" in
  pr|full|cd) ;;
  *)
    echo "invalid profile: $profile" >&2
    exit 2
    ;;
esac

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

sha="$(git rev-parse HEAD)"
context="fop/local-ci/${profile}"
report_dir="$repo_root/.fop/reports"
report_path="$report_dir/local-ci-${profile}-${sha}.json"
started_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

status_repo() {
  if [[ -n "${FOP_LOCAL_CI_STATUS_REPO:-}" ]]; then
    printf '%s\n' "$FOP_LOCAL_CI_STATUS_REPO"
    return 0
  fi
  for remote in github upstream origin; do
    url="$(git remote get-url "$remote" 2>/dev/null || true)"
    if [[ "$url" =~ github.com[:/]([^/]+/[^/.]+)(\.git)?$ ]]; then
      printf '%s\n' "${BASH_REMATCH[1]}"
      return 0
    fi
  done
  echo "unable to derive GitHub owner/repo; set FOP_LOCAL_CI_STATUS_REPO" >&2
  return 1
}

post_commit_status() {
  local state="$1"
  local description="$2"
  if [[ "$post_status" -ne 1 || "$dry_run" -eq 1 ]]; then
    return 0
  fi
  if ! command -v gh >/dev/null 2>&1; then
    echo "gh is required for --post-status" >&2
    return 1
  fi

  # Tolerate "No commit found for SHA" (HTTP 422) — happens when this
  # script runs from the pre-push hook BEFORE the commit has reached
  # GitHub. The local-ci-attestation gate's extended polling window then
  # handles the missing status once the SHA appears on GitHub.
  # gh emits both the JSON body and the "(HTTP 422)" line on stdout,
  # so we capture stdout (not stderr) for the match.
  local out
  if ! out="$(gh api \
    --method POST \
    "repos/$(status_repo)/statuses/${sha}" \
    -f state="$state" \
    -f context="$context" \
    -f description="$description" 2>&1)"; then
    if echo "$out" | grep -qE "No commit found for SHA|HTTP 422"; then
      echo "Skipping status post — commit ${sha:0:8} not yet on GitHub (will land after push; gate falls back to timeout)." >&2
      return 0
    fi
    echo "$out" >&2
    return 1
  fi
}

write_report() {
  local state="$1"
  local failed_step="${2:-}"
  mkdir -p "$report_dir"
  cat > "$report_path" <<EOF
{
  "schema": "parkhub.local-ci.v1",
  "profile": "$profile",
  "state": "$state",
  "commit": "$sha",
  "started_at": "$started_at",
  "finished_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "failed_step": "$failed_step",
  "context": "$context"
}
EOF
}

# All non-trivial work goes through `fop build --backend local` so the
# fop queue can serialize concurrent runs and apply the OOM cap.
#
# `interactive-small` shrinks the per-step memory request to ~1-2 GiB
# instead of the 6 GiB default. PHP/Composer/Pint/PHPStan/Vitest steps
# rarely exceed 1 GiB resident, so the bigger request would just stall
# the queue under multi-tab pressure. Heavy builds (release artifacts,
# Playwright browser harness) opt back into a larger profile via
# `run_step_heavy` below.
#
# Setting FOP_LOCAL_CI_DIRECT=1 bypasses the fop queue wrapper and
# runs each step directly in the current shell. Use this for the
# bootstrap chicken-and-egg run that introduces this script (the queue
# would refuse capacity if a sibling tab already holds the parallelism
# slot), or when running outside fop entirely. Operators must still
# guarantee local memory headroom themselves in that mode.
run_step() {
  local name="$1"
  local command="$2"
  printf '\n==> %s\n' "$name"
  if [[ "$dry_run" -eq 1 ]]; then
    printf 'DRY-RUN: %s\n' "$command"
    return 0
  fi
  if [[ "${FOP_LOCAL_CI_DIRECT:-0}" == "1" ]] || ! command -v fop >/dev/null 2>&1; then
    # Direct mode (no fop queue): explicit opt-in OR fop binary not on
    # PATH (GitHub Actions runners, fresh contributor boxes). The kernel
    # + earlyoom handle resource pressure when fop isn't available.
    bash -euo pipefail -c "$command"
    return 0
  fi
  fop build --backend local --resource-profile interactive-small . --preset custom -- bash -euo pipefail -c "$command"
}

run_step_heavy() {
  local name="$1"
  local command="$2"
  printf '\n==> %s (heavy)\n' "$name"
  if [[ "$dry_run" -eq 1 ]]; then
    printf 'DRY-RUN: %s\n' "$command"
    return 0
  fi
  if [[ "${FOP_LOCAL_CI_DIRECT:-0}" == "1" ]] || ! command -v fop >/dev/null 2>&1; then
    bash -euo pipefail -c "$command"
    return 0
  fi
  fop build --backend local --resource-profile batch-medium . --preset custom -- bash -euo pipefail -c "$command"
}

run_advisory_step_heavy() {
  local name="$1"
  local command="$2"
  if ! run_step_heavy "$name" "$command"; then
    echo "$name returned non-zero (advisory; continuing)"
  fi
}

# `run_direct` is for instantaneous shell checks (git diff, etc.) that
# would be pure overhead inside the fop queue.
run_direct() {
  local name="$1"
  local command="$2"
  printf '\n==> %s\n' "$name"
  if [[ "$dry_run" -eq 1 ]]; then
    printf 'DRY-RUN: %s\n' "$command"
    return 0
  fi
  bash -euo pipefail -c "$command"
}

# `skip_step` records a skipped optional gate (advisory; tool not on PATH).
# Used by Trivy/zizmor/OSV-Scanner/Grype branches when contributors don't
# have the binary installed locally. Without this, calling skip_step blew
# up with `command not found` → exit 127 → make: Error 127 → release-yml
# Pre-release tests failure.
skip_step() {
  local name="$1"
  local reason="${2:-skipped}"
  printf '\n==> %s (skipped)\n%s\n' "$name" "$reason"
}

mark_failure() {
  local line="$1"
  # Best-effort report + status post: never let the failure handler
  # itself error out, since `set -e` would mask the originating
  # failure with a confusing handler trace. Original exit code is
  # preserved by the trap returning naturally.
  write_report "failure" "line:${line}" || true
  post_commit_status "failure" "fop local ${profile} failed" || true
}
trap 'mark_failure "$LINENO"' ERR

post_commit_status "pending" "fop local ${profile} running"

run_direct "working tree whitespace" "git diff --check"

# ---------------- Backend (PHP) ---------------------------------------------
run_step "composer validate" "composer validate --strict"

# composer audit is advisory-only on the pr profile so dev-only or
# unfixable advisories cannot block routine work. cd profile re-runs
# it with --no-dev for a stricter prod-only pass.
run_step "composer audit (advisory)" "composer audit --no-interaction || echo 'composer audit returned non-zero (advisory on pr profile)'"

run_step "composer install (sync)" "composer install --prefer-dist --no-interaction --no-progress"

run_step "pint format check" "./vendor/bin/pint --test"

run_step "phpstan level 5" "scripts/ci/phpstan-analyse.sh --memory-limit=512M --no-progress"

run_step "phpunit unit + feature" "./vendor/bin/phpunit --testsuite=Unit --no-coverage && ./vendor/bin/phpunit --testsuite=Feature --no-coverage"

# ---------------- Frontend (Astro 5 + React 19 + Vitest 3) ------------------
run_step "frontend npm install" "npm ci && npm ci --prefix parkhub-web"

run_step "frontend vitest" "cd parkhub-web && npm test"

run_step "frontend build" "cd parkhub-web && npm run build && cd .. && npm run build"

# tsc --noEmit on parkhub-web is not yet green on main as of 4.15.0 —
# the `chore/web-tsc-phase4c-*` series (PRs #379..#382 and ongoing) is
# still chipping away at hundreds of inherited TS errors. Keep this after
# hard frontend gates and make the fop wrapper advisory too, so host pressure
# cannot fail the PR gate before the intentionally non-gating check completes.
run_advisory_step_heavy "frontend typecheck (advisory until tsc-phase4 lands)" "cd parkhub-web && NODE_OPTIONS=\"\${NODE_OPTIONS:-} --max-old-space-size=4096\" ./node_modules/.bin/tsc --noEmit || echo 'tsc errors present (advisory while phase4 is in flight)'"

# ---------------- Drift gates -----------------------------------------------
# Both scripts already follow the same pattern as the rust side: they
# regenerate the snapshot, then fail if `git diff --exit-code` shows drift.
run_step "openapi drift" "scripts/check-openapi-drift.sh"

# In parkhub-php this is a no-op (the shared TS API types are
# generated by ts-rs in parkhub-rust and committed into parkhub-web
# read-only). Keep it for symmetry with parkhub-rust's local-ci so
# operators can read the same step list, but label it explicitly so
# nobody mistakes the always-pass for a real drift signal.
run_step "types drift (no-op in php; gated by parkhub-rust)" "scripts/check-types-drift.sh"

# ---------------- Local OSS security mirror ---------------------------------
# Mirrors the GitHub/Gitea security + workflow hygiene jobs with local
# open-source tools. Missing optional tools are surfaced but do not block the
# standard PR gate; run `make ci-security` for the strict local toolchain check.
security_profile="pr"
if [[ "$profile" == "cd" ]]; then
  security_profile="cd"
fi
run_step "local security audit (${security_profile} mirror)" "scripts/ci/local-security-audit.sh --profile ${security_profile}"

# `cd` profile is documented as `full + cd-specific steps`, so the full
# block runs for both `full` and `cd`. Without this, `cd` would skip
# Schemathesis / Infection / Playwright entirely and a release preflight
# could pass without the very checks the profile description promises.
if [[ "$profile" == "full" || "$profile" == "cd" ]]; then
  # Schemathesis is informational. Two gating layers, both soft:
  #   1. binary present? (skip cleanly if not installed)
  #   2. caller opted in via FOP_LOCAL_CI_RUN_SCHEMATHESIS=1?
  #      The step needs a running API server on :8082, which the local
  #      script does not start (the GHA workflow does). Without it, an
  #      installed schemathesis would always fail soft — meaningless
  #      signal. Keep the step disabled by default; the env flag lets
  #      a developer run it explicitly after starting `php artisan serve`.
  run_step "schemathesis contract fuzz (soft, opt-in)" "if [[ \"\${FOP_LOCAL_CI_RUN_SCHEMATHESIS:-0}\" != \"1\" ]]; then echo 'schemathesis disabled by default; export FOP_LOCAL_CI_RUN_SCHEMATHESIS=1 with a running php artisan serve on :8082 to enable'; elif command -v schemathesis >/dev/null 2>&1; then ./scripts/dump-openapi.sh && schemathesis run --checks=all --hypothesis-max-examples=50 docs/openapi/php.json --base-url=http://127.0.0.1:8082 || echo 'schemathesis returned non-zero (soft on full profile)'; else echo 'schemathesis not installed; skipping'; fi"

  # Infection mutation testing is informational. Two soft gating layers
  # (mirrors the schemathesis pattern above):
  #   1. caller opted in via FOP_LOCAL_CI_RUN_INFECTION=1?
  #   2. coverage extension present? (Infection without pcov/xdebug fails
  #      with a CoverageChecker error in <1s — meaningless signal.)
  # The nightly GHA workflow .github/workflows/infection.yml runs with
  # continue-on-error: true, so the local CD profile must not be stricter.
  run_step_heavy "infection mutation testing (soft, opt-in)" "if [[ \"\${FOP_LOCAL_CI_RUN_INFECTION:-0}\" != \"1\" ]]; then echo 'infection disabled by default; export FOP_LOCAL_CI_RUN_INFECTION=1 with pcov/xdebug enabled to run mutation testing'; elif ! php -m | grep -qE '^(pcov|xdebug)\$'; then echo 'infection requires pcov or xdebug for coverage; skipping (advisory like infection.yml continue-on-error)'; else ./vendor/bin/infection --threads=4 --no-progress || echo 'infection returned non-zero (soft on cd profile)'; fi"

  run_step_heavy "playwright chromium browser install" "npx playwright install --with-deps chromium"

  run_step_heavy "playwright chromium e2e" "e2e_db=\"\${FOP_LOCAL_CI_E2E_DB:-/tmp/parkhub-e2e-\$\$.sqlite}\"; rm -f \"\$e2e_db\"; export DB_CONNECTION=sqlite DB_DATABASE=\"\$e2e_db\" DEMO_MODE=true PARKHUB_ADMIN_PASSWORD=demo PARKHUB_DISABLE_RATE_LIMITS=true E2E_BASE_URL=http://127.0.0.1:8082; ./scripts/ci/bootstrap-laravel.sh && php artisan migrate:fresh --seed --seeder=ProductionSimulationSeeder --force --no-interaction && npm run build:php --prefix parkhub-web && pid=''; cleanup() { if [[ -n \"\${pid:-}\" ]]; then kill \"\$pid\" 2>/dev/null || true; fi; rm -f \"\$e2e_db\"; }; trap cleanup EXIT; { php artisan serve --host=127.0.0.1 --port=8082 >/tmp/parkhub-e2e.log 2>&1 & pid=\$!; }; ./scripts/ci/wait-for-url.sh http://127.0.0.1:8082/api/v1/health/live 60 && npx playwright test e2e/api.spec.ts e2e/pages.spec.ts e2e/v5-a11y.spec.ts --project=chromium"
fi

if [[ "$profile" == "cd" ]]; then
  run_step "release smoke (php artisan test --testsuite=Feature)" "./scripts/ci/bootstrap-laravel.sh && php artisan test --testsuite=Feature"
fi

# ─── Trivy filesystem scan ──────────────────────────────────────────────────
# Mirrors .github/workflows/security.yml trivy-fs job. Apache-2.0 license.
# Skips gracefully on `pr` profile if trivy isn't on PATH (so contributors
# without trivy installed can still pass the local gate); always required on
# `cd`/`full` profiles. Findings under .trivyignore (with justification
# comments) are filtered. Severity matches the workflow: CRITICAL,HIGH only.
trivy_required=0
[[ "$profile" == "cd" || "$profile" == "full" ]] && trivy_required=1
if command -v trivy >/dev/null 2>&1; then
  run_step "trivy filesystem scan" "trivy fs --quiet --exit-code 1 --scanners=vuln,misconfig --severity=CRITICAL,HIGH --ignorefile .trivyignore --skip-dirs=node_modules,vendor,parkhub-web/node_modules,resources/js/node_modules,.claude/worktrees ."
elif [[ $trivy_required -eq 1 ]]; then
  echo "✗ trivy filesystem scan FAILED: trivy not on PATH (required for ${profile} profile)" >&2
  write_report "failure" "trivy filesystem scan"
  post_commit_status "failure" "fop local ${profile} failed: trivy not installed"
  exit 1
else
  skip_step "trivy filesystem scan" "trivy not on PATH (install: https://aquasecurity.github.io/trivy/)"
fi

# ─── zizmor (GitHub Actions SAST, advisory) ─────────────────────────────────
# Mirrors .github/workflows/security.yml zizmor job. MIT license. Replaces
# CodeQL's `actions/missing-workflow-permissions` coverage and adds 30+ rules
# for CI/CD hardening (template injection, cache poisoning, persist-credentials,
# excessive-permissions). Uses --persona=auditor to match the workflow.
#
# Advisory mode: matches workflow's `continue-on-error: true` — zizmor surfaces
# findings as informational but does NOT fail the gate. Promote to a hard
# failure (drop the `|| true`) once the open-finding inventory is at zero.
# Suppressions live in zizmor.yml with per-rule justification.
if command -v zizmor >/dev/null 2>&1; then
  run_step "zizmor (GHA SAST, advisory)" "zizmor --persona=auditor --min-severity=high --no-online-audits .github/workflows/ .gitea/workflows/ || echo 'zizmor returned non-zero (advisory — see findings above)'"
else
  skip_step "zizmor (GHA SAST)" "zizmor not on PATH (install: cargo install zizmor or https://docs.zizmor.sh)"
fi

# ─── OSV-Scanner (supply-chain via OSV database) ────────────────────────────
# OSV-Scanner is invoked once via scripts/ci/local-security-audit.sh above
# (composer.lock + package-lock.json + parkhub-web/package-lock.json with
# osv-scanner.toml ignore config). Dedup'd here to avoid running the same
# scan twice per fop-local-ci invocation — addresses #409 review.

# ─── Grype (vuln scanner, defense-in-depth) ─────────────────────────────────
# Grype (Apache-2.0, Anchore) is a complementary vuln scanner to Trivy.
# Different DB sources catch different findings — defense-in-depth on the
# supply chain. Advisory only on `cd` profile (release path); skipped on `pr`.
if [[ "$profile" == "cd" ]] && command -v grype >/dev/null 2>&1; then
  run_step "grype (defense-in-depth, advisory)" "grype dir:. --fail-on critical --quiet 2>&1 | tail -20 || echo 'grype found vulns (advisory)'"
elif [[ "$profile" == "cd" ]]; then
  skip_step "grype" "grype not on PATH (install: https://github.com/anchore/grype#installation)"
fi

write_report "success"
post_commit_status "success" "fop local ${profile} passed"

printf '\nlocal CI passed: %s\n' "$report_path"
