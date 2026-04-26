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

  gh api \
    --method POST \
    "repos/$(status_repo)/statuses/${sha}" \
    -f state="$state" \
    -f context="$context" \
    -f description="$description" >/dev/null
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
  if [[ "${FOP_LOCAL_CI_DIRECT:-0}" == "1" ]]; then
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
  if [[ "${FOP_LOCAL_CI_DIRECT:-0}" == "1" ]]; then
    bash -euo pipefail -c "$command"
    return 0
  fi
  fop build --backend local --resource-profile batch-medium . --preset custom -- bash -euo pipefail -c "$command"
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

run_step "phpstan level 5" "./vendor/bin/phpstan analyse --memory-limit=2G --no-progress"

run_step "phpunit unit + feature" "./vendor/bin/phpunit --testsuite=Unit --no-coverage && ./vendor/bin/phpunit --testsuite=Feature --no-coverage"

# ---------------- Frontend (Astro 5 + React 19 + Vitest 3) ------------------
run_step "frontend npm install" "npm ci && npm ci --prefix parkhub-web"

# tsc --noEmit on parkhub-web is not yet green on main as of 4.15.0 —
# the `chore/web-tsc-phase4c-*` series (PRs #379..#382 and ongoing) is
# still chipping away at hundreds of inherited TS errors. Run the gate
# as advisory until phase 4 lands; the diff that makes it strict will
# be a separate PR.
run_step "frontend typecheck (advisory until tsc-phase4 lands)" "cd parkhub-web && ./node_modules/.bin/tsc --noEmit || echo 'tsc errors present (advisory while phase4 is in flight)'"

run_step "frontend vitest" "cd parkhub-web && npm test"

run_step "frontend build" "cd parkhub-web && npm run build && cd .. && npm run build"

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

# ---------------- Optional security linters ---------------------------------
# zizmor (GHA SAST, MIT-licensed Rust). Run if installed; skip cleanly
# otherwise so fresh clones do not block on a missing tool.
run_step "zizmor (gha lint)" "if command -v zizmor >/dev/null 2>&1; then zizmor .github/workflows; else echo 'zizmor not installed; skipping'; fi"

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

  run_step_heavy "infection mutation testing" "./vendor/bin/infection --threads=4 --no-progress"

  run_step_heavy "playwright chromium e2e" "./scripts/ci/bootstrap-laravel.sh && npm run build:php --prefix parkhub-web && pid=''; cleanup() { if [[ -n \"\${pid:-}\" ]]; then kill \"\$pid\" 2>/dev/null || true; fi; }; trap cleanup EXIT; { DEMO_MODE=true PARKHUB_ADMIN_PASSWORD=demo PARKHUB_DISABLE_RATE_LIMITS=true php artisan serve --host=127.0.0.1 --port=8082 >/tmp/parkhub-e2e.log 2>&1 & pid=\$!; }; ./scripts/ci/wait-for-url.sh http://127.0.0.1:8082/api/v1/health/live 60 && npx playwright test e2e/api.spec.ts e2e/pages.spec.ts e2e/v5-a11y.spec.ts --project=chromium"
fi

if [[ "$profile" == "cd" ]]; then
  run_step "composer audit (prod-only, strict)" "composer audit --no-dev --no-interaction"

  # Trivy filesystem scan when available (MIT-licensed). Skip cleanly
  # if not installed so cd profile remains runnable from a fresh clone.
  run_step "trivy filesystem scan" "if command -v trivy >/dev/null 2>&1; then trivy fs --severity HIGH,CRITICAL --exit-code 1 --skip-dirs vendor,node_modules,parkhub-web/node_modules .; else echo 'trivy not installed; skipping'; fi"

  run_step "release smoke (php artisan test --testsuite=Feature)" "./scripts/ci/bootstrap-laravel.sh && php artisan test --testsuite=Feature"
fi

write_report "success"
post_commit_status "success" "fop local ${profile} passed"

printf '\nlocal CI passed: %s\n' "$report_path"
