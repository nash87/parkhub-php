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
# fop queue can serialize concurrent runs and apply the 12 GiB OOM cap.
run_step() {
  local name="$1"
  local command="$2"
  printf '\n==> %s\n' "$name"
  if [[ "$dry_run" -eq 1 ]]; then
    printf 'DRY-RUN: %s\n' "$command"
    return 0
  fi
  fop build --backend local . --preset custom -- bash -euo pipefail -c "$command"
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
  write_report "failure" "line:${line}"
  post_commit_status "failure" "fop local ${profile} failed"
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

run_step "frontend typecheck" "cd parkhub-web && ./node_modules/.bin/tsc --noEmit"

run_step "frontend vitest" "cd parkhub-web && npm test"

run_step "frontend build" "cd parkhub-web && npm run build && cd .. && npm run build"

# ---------------- Drift gates -----------------------------------------------
# Both scripts already follow the same pattern as the rust side: they
# regenerate the snapshot, then fail if `git diff --exit-code` shows drift.
run_step "openapi drift" "scripts/check-openapi-drift.sh"

run_step "types drift" "scripts/check-types-drift.sh"

# ---------------- Optional security linters ---------------------------------
# zizmor (GHA SAST, MIT-licensed Rust). Run if installed; skip cleanly
# otherwise so fresh clones do not block on a missing tool.
run_step "zizmor (gha lint)" "if command -v zizmor >/dev/null 2>&1; then zizmor .github/workflows; else echo 'zizmor not installed; skipping'; fi"

if [[ "$profile" == "full" ]]; then
  # Schemathesis is informational on PRs in the GHA workflow too — keep
  # local parity by allowing soft failure with a logged note.
  run_step "schemathesis contract fuzz (soft)" "if command -v schemathesis >/dev/null 2>&1; then ./scripts/dump-openapi.sh && schemathesis run --checks=all --hypothesis-max-examples=50 docs/openapi/php.json --base-url=http://127.0.0.1:8082 || echo 'schemathesis returned non-zero (soft on full profile)'; else echo 'schemathesis not installed; skipping'; fi"

  run_step "infection mutation testing" "./vendor/bin/infection --threads=4 --no-progress"

  run_step "playwright chromium e2e" "./scripts/ci/bootstrap-laravel.sh && npm run build:php --prefix parkhub-web && pid=''; cleanup() { if [[ -n \"\${pid:-}\" ]]; then kill \"\$pid\" 2>/dev/null || true; fi; }; trap cleanup EXIT; { DEMO_MODE=true PARKHUB_ADMIN_PASSWORD=demo PARKHUB_DISABLE_RATE_LIMITS=true php artisan serve --host=127.0.0.1 --port=8082 >/tmp/parkhub-e2e.log 2>&1 & pid=\$!; }; ./scripts/ci/wait-for-url.sh http://127.0.0.1:8082/api/v1/health/live 60 && npx playwright test e2e/api.spec.ts e2e/pages.spec.ts e2e/v5-a11y.spec.ts --project=chromium"
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
