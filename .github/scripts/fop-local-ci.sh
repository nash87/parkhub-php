#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
  cat <<'EOF'
Usage: .github/scripts/fop-local-ci.sh [--profile pr|full|cd] [--dry-run] [--post-status] [--background]

Runs ParkHub-PHP's local-first CI through the Nido/fop build queue. The optional
--background flag runs the gate in a detached subshell, logs to
.fop/reports/local-ci-<profile>-<sha>-bg.log, and returns immediately.
Combine with --post-status for fire-and-forget full runs that publish their
own commit status context when complete.

The optional --post-status flag publishes the commit status context for the
selected profile. The GitHub PR attestation gate expects this exact command:

  .github/scripts/fop-local-ci.sh --profile pr --post-status

Profiles:
  pr    Fast PR gate: composer + Pint + PHPStan + PHPUnit + Vitest +
        Astro tsc/build + Composer audit. Diff-aware: skips PHP, frontend,
        workflow, e2e, and image/security mirrors when the PR diff does not
        touch their inputs. Set FOP_LOCAL_CI_NO_DIFF_AWARE=1 to force every
        PR step.
  full  PR gate plus Schemathesis contract fuzz (best effort), Infection
        mutation testing, and Playwright e2e smoke.
  cd    Release-oriented preflight: full + composer-audit prod-only +
        Trivy filesystem scan when available.

Environment overrides:
  FOP_LOCAL_CI_STATUS_REPO     owner/repo for status post (else autodetected
                               from git remotes named github → upstream → origin).
  FOP_LOCAL_CI_NO_DIFF_AWARE=1 disable diff-aware skipping on pr profile.
  FOP_LOCAL_CI_DIFF_PATHS      newline-delimited diff path override for
                               contract tests and explicit local reruns.
  FOP_LOCAL_CI_BG_LOG_DIR      background log directory override.
  FOP_LOCAL_CI_QUEUE_BIN       queue wrapper binary. Defaults to `nido` when it
                               supports `build`, otherwise `fop`.
  FOP_LOCAL_CI_DIRECT          1 = bypass the queue wrapper and
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
background=0

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
    --background)
      background=1
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

if [[ "$background" -eq 1 ]]; then
  repo_root_for_bg="$(git rev-parse --show-toplevel)"
  bg_log_dir="${FOP_LOCAL_CI_BG_LOG_DIR:-$repo_root_for_bg/.fop/reports}"
  mkdir -p "$bg_log_dir"
  bg_sha="$(git rev-parse HEAD)"
  bg_log="$bg_log_dir/local-ci-${profile}-${bg_sha:0:8}-bg.log"
  bg_args=("--profile" "$profile")
  [[ "$dry_run" -eq 1 ]] && bg_args+=("--dry-run")
  [[ "$post_status" -eq 1 ]] && bg_args+=("--post-status")
  echo "fop-local-ci backgrounded: profile=$profile log=$bg_log"
  nohup "$0" "${bg_args[@]}" >"$bg_log" 2>&1 < /dev/null &
  bg_pid=$!
  disown 2>/dev/null || true
  echo "PID=$bg_pid sha=${bg_sha:0:8}"
  echo "watch: tail -f $bg_log"
  exit 0
fi

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

queue_bin="${FOP_LOCAL_CI_QUEUE_BIN:-}"
if [[ -z "$queue_bin" ]]; then
  supports_queue_build() {
    command -v "$1" >/dev/null 2>&1 && "$1" build --help >/dev/null 2>&1
  }

  if supports_queue_build nido; then
    queue_bin="nido"
  else
    queue_bin="fop"
  fi
fi

sha="$(git rev-parse HEAD)"
context="fop/local-ci/${profile}"
report_dir="$repo_root/.fop/reports"
report_path="$report_dir/local-ci-${profile}-${sha}.json"
started_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
diff_paths=""
diff_touch_design_smoke=0

compute_design_smoke_gate() {
  if [[ "${FOP_LOCAL_CI_NO_DIFF_AWARE:-}" == "1" ]] || [[ "$profile" != "pr" ]]; then
    diff_touch_design_smoke=1
    return 0
  fi

  local design_diff_paths=""
  local base_label=""
  if [[ -n "${FOP_LOCAL_CI_DIFF_PATHS:-}" ]]; then
    design_diff_paths="${FOP_LOCAL_CI_DIFF_PATHS}"
    base_label="override"
  fi

  local base_ref
  if [[ -z "$design_diff_paths" ]]; then
    for candidate in github/main upstream/main origin/main main; do
      if git rev-parse --verify --quiet "$candidate" >/dev/null; then
        base_ref="$candidate"
        break
      fi
    done

    if [[ -z "${base_ref:-}" ]]; then
      printf 'ℹ design-smoke gate: no base ref resolvable; enabling\n'
      diff_touch_design_smoke=1
      return 0
    fi

    local merge_base
    merge_base="$(git merge-base "$base_ref" HEAD 2>/dev/null || echo "$base_ref")"
    design_diff_paths="$(git diff --name-only "${merge_base}..HEAD" 2>/dev/null || true)"
    base_label="$base_ref"
  fi

  if [[ -z "$design_diff_paths" ]]; then
    printf 'ℹ design-smoke gate: empty diff vs %s; enabling\n' "$base_label"
    diff_touch_design_smoke=1
    return 0
  fi

  # `git diff --name-only` emits FILE paths (e.g. parkhub-web/src/design-v5/screens/Policies.tsx),
  # so directory alternatives must allow a tail (`/.*`). Also include `hooks/` since v5 screens
  # import shared hooks like useDraftFromActive — edits there can break the gate.
  if grep -qE '^(parkhub-web/(src/(design-v5|views|components|context|api|lib|styles|hooks)/.*|src/(App|main)\.tsx|package(-lock)?\.json|astro\.config\.mjs|playwright\.config\.ts)|resources/js/.*|e2e/.*|playwright\.config\.ts|package(-lock)?\.json)$' <<<"$design_diff_paths"; then
    diff_touch_design_smoke=1
  fi

  printf 'ℹ design-smoke gate (vs %s): enabled=%d (%d files)\n' \
    "$base_label" "$diff_touch_design_smoke" "$(wc -l <<<"$design_diff_paths")"
}

# Allocate a Laravel dev-server port that is unlikely to collide with a
# sibling fop-local-ci run for a different PR worktree on the same desktop.
# Concurrent runs all spawn `php artisan serve` via `scripts/e2e-local.sh`
# (design-smoke) or the playwright e2e step (cd/full); without a unique port
# the second-and-later runners fail with `Failed to listen on 127.0.0.1:8082
# (Address already in use)` and the whole gate posts a false `failure`.
#
# Order of preference:
#   1. FOP_LOCAL_CI_LARAVEL_PORT (explicit operator override)
#   2. SERVER_PORT (caller may already have one in env from outer wrapper)
#   3. 8082 if free (preserves docs + screenshots + muscle memory)
#   4. random free port in the ephemeral range (49152-65535) from `ss`
#   5. fallback: 8083 + small random offset 0-199 (best-effort if `ss`
#      or `shuf` is missing). Note: tiers 3+4 inspect *listening* sockets
#      only; a TOCTOU race window remains between observation and bind,
#      so tier 5 acts as the deterministic-fallback for parallel runs.
allocate_laravel_port() {
  if [[ -n "${FOP_LOCAL_CI_LARAVEL_PORT:-}" ]]; then
    printf '%s' "${FOP_LOCAL_CI_LARAVEL_PORT}"
    return 0
  fi
  if [[ -n "${SERVER_PORT:-}" ]]; then
    printf '%s' "${SERVER_PORT}"
    return 0
  fi
  if command -v ss >/dev/null 2>&1; then
    local in_use
    # -ltn = listening TCP, numeric; ignores non-listening sockets so we
    # don't spuriously avoid ports that are only client-side in use.
    in_use="$(ss -ltn 2>/dev/null | awk 'NR>1 {sub(/.*:/,"",$4); print $4}' | sort -un)"
    if ! grep -qx '8082' <<<"$in_use"; then
      printf '%s' '8082'
      return 0
    fi
    if command -v shuf >/dev/null 2>&1; then
      local picked
      picked="$(comm -23 <(seq 49152 65535) <(printf '%s\n' "$in_use") 2>/dev/null | shuf -n 1)"
      if [[ -n "$picked" ]]; then
        printf '%s' "$picked"
        return 0
      fi
    fi
  fi
  printf '%s' "$((8083 + RANDOM % 200))"
}

diff_paths=""
diff_aware_enabled=0
diff_touch_php=0
diff_touch_frontend=0
diff_touch_workflows=0
diff_touch_e2e=0
diff_touch_image=0
diff_touch_nix_dev=0
diff_touch_security=0

enable_all_pr_steps() {
  diff_touch_php=1
  diff_touch_frontend=1
  diff_touch_workflows=1
  diff_touch_e2e=1
  diff_touch_image=1
  diff_touch_nix_dev=1
  diff_touch_security=1
}

compute_diff_paths() {
  if [[ "$profile" != "pr" || "${FOP_LOCAL_CI_NO_DIFF_AWARE:-}" == "1" ]]; then
    enable_all_pr_steps
    return 0
  fi

  diff_aware_enabled=1
  if [[ -n "${FOP_LOCAL_CI_DIFF_PATHS:-}" ]]; then
    diff_paths="${FOP_LOCAL_CI_DIFF_PATHS}"
  else
    local base_ref=""
    for candidate in github/main upstream/main origin/main main; do
      if git rev-parse --verify --quiet "$candidate" >/dev/null; then
        base_ref="$candidate"
        break
      fi
    done

    if [[ -z "$base_ref" ]]; then
      printf 'diff-aware: no base ref resolvable; running full pr profile\n'
      enable_all_pr_steps
      return 0
    fi

    local merge_base
    merge_base="$(git merge-base "$base_ref" HEAD 2>/dev/null || echo "$base_ref")"
    diff_paths="$(
      {
        git diff --name-only "${merge_base}..HEAD" 2>/dev/null || true
        git diff --name-only 2>/dev/null || true
        git diff --cached --name-only 2>/dev/null || true
      } | sort -u
    )"

    if [[ -z "$diff_paths" ]]; then
      printf 'diff-aware: empty diff vs %s; running full pr profile\n' "$base_ref"
      enable_all_pr_steps
      return 0
    fi
  fi

  if grep -qE '(^app/|^bootstrap/|^config/|^database/|^routes/|^tests/|\.php$|^artisan$|^composer\.(json|lock)$|^phpunit\.xml|^phpstan\.neon|^pint\.json|^scripts/ci/|^scripts/check-openapi-drift\.sh|^docs/openapi/php\.json)' <<<"$diff_paths"; then
    diff_touch_php=1
  fi
  if grep -qE '(^parkhub-web/|^resources/js/|^package(-lock)?\.json$|^vite\.config\.|^tsconfig\.json$|^tailwind\.config\.|^postcss\.config\.)' <<<"$diff_paths"; then
    diff_touch_frontend=1
  fi
  if grep -qE '(^\.github/(workflows|scripts|actions)/|^\.gitea/workflows/|^Makefile$|^scripts/tests/|^scripts/check-local-ci-report\.sh|^\.devcontainer/|^flake\.(nix|lock)$|^garnix\.yaml$)' <<<"$diff_paths"; then
    diff_touch_workflows=1
  fi
  if grep -qE '(^e2e/|^playwright\.config\.(ts|js|mjs|cjs)$)' <<<"$diff_paths"; then
    diff_touch_e2e=1
  fi
  if grep -qE '(^Dockerfile$|^Containerfile|^\.devcontainer/Containerfile$|^docker/|^helm/|^composer\.lock$|^package-lock\.json$|^parkhub-web/package-lock\.json$)' <<<"$diff_paths"; then
    diff_touch_image=1
  fi
  if grep -qE '(^\.devcontainer/|^flake\.(nix|lock)$|^garnix\.yaml$)' <<<"$diff_paths"; then
    diff_touch_nix_dev=1
  fi
  if (( diff_touch_php || diff_touch_frontend || diff_touch_workflows || diff_touch_e2e || diff_touch_image || diff_touch_nix_dev )); then
    diff_touch_security=1
  fi

  printf 'diff-aware: php=%d frontend=%d workflows=%d e2e=%d image=%d nix_dev=%d security=%d (%d files)\n' \
    "$diff_touch_php" "$diff_touch_frontend" "$diff_touch_workflows" "$diff_touch_e2e" \
    "$diff_touch_image" "$diff_touch_nix_dev" "$diff_touch_security" "$(wc -l <<<"$diff_paths")"
}

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
  if [[ "$dry_run" -eq 1 ]]; then
    echo "DRY-RUN: not writing local-ci ${state} report for ${sha:0:8}"
    return 0
  fi
  mkdir -p "$report_dir"
  cat > "$report_path" <<EOF
{
  "schema": "parkhub.local-ci.v2",
  "profile": "$profile",
  "state": "$state",
  "commit": "$sha",
  "started_at": "$started_at",
  "finished_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "failed_step": "$failed_step",
  "context": "$context",
  "diff_aware": {
    "enabled": $([[ "$diff_aware_enabled" -eq 1 ]] && echo true || echo false),
    "php": $([[ "$diff_touch_php" -eq 1 ]] && echo true || echo false),
    "frontend": $([[ "$diff_touch_frontend" -eq 1 ]] && echo true || echo false),
    "workflows": $([[ "$diff_touch_workflows" -eq 1 ]] && echo true || echo false),
    "e2e": $([[ "$diff_touch_e2e" -eq 1 ]] && echo true || echo false),
    "image": $([[ "$diff_touch_image" -eq 1 ]] && echo true || echo false),
    "nix_dev": $([[ "$diff_touch_nix_dev" -eq 1 ]] && echo true || echo false),
    "security": $([[ "$diff_touch_security" -eq 1 ]] && echo true || echo false)
  }
}
EOF
}

# All non-trivial work goes through the local queue wrapper (`nido build`
# on current workstations, `fop build` on older hosts) so concurrent runs
# are serialized and the OOM cap is applied.
#
# `interactive-small` shrinks the per-step memory request to ~1-2 GiB
# instead of the 6 GiB default. PHP/Composer/Pint/PHPStan/Vitest steps
# rarely exceed 1 GiB resident, so the bigger request would just stall
# the queue under multi-tab pressure. Heavy builds (release artifacts,
# Playwright browser harness) opt back into a larger profile via
# `run_step_heavy` below.
#
run_fop_step() {
  local resource_profile="$1"
  local command="$2"
  local marker="__PARKHUB_FOP_STEP_OK_${RANDOM}_${RANDOM}__"
  local log_file
  local wrapped_command

  log_file="$(mktemp -t parkhub-fop-step.XXXXXX.log)"
  printf -v wrapped_command '%s\nprintf "%%s\\n" "$PARKHUB_FOP_STEP_MARKER"' "$command"

  set +e
  PARKHUB_FOP_STEP_MARKER="$marker" \
    "$queue_bin" build --backend local --resource-profile "$resource_profile" . --preset custom -- \
      bash -euo pipefail -c "$wrapped_command" 2>&1 | tee "$log_file"
  local status=${PIPESTATUS[0]}
  set -e

  if ! grep -Fq "$marker" "$log_file"; then
    echo "ERROR: ${queue_bin} build reported success but the inner step completion marker was missing." >&2
    echo "This usually means the wrapped command exited before completion or ${queue_bin} masked its status." >&2
    rm -f "$log_file"
    return 1
  fi

  if [[ "$status" -ne 0 ]]; then
    echo "WARN: ${queue_bin} build exited ${status} after the wrapped step completed; continuing because the completion marker was present." >&2
    echo "This can happen when the active queue wrapper flags advisory-only output after the command already handled it." >&2
  fi

  rm -f "$log_file"
}

# Setting FOP_LOCAL_CI_DIRECT=1 bypasses the queue wrapper and
# runs each step directly in the current shell. Use this for the
# bootstrap chicken-and-egg run that introduces this script (the queue
# would refuse capacity if a sibling tab already holds the parallelism
# slot), or when running without the active queue wrapper. Operators
# must still guarantee local memory headroom themselves in that mode.
run_step() {
  local name="$1"
  local command="$2"
  printf '\n==> %s\n' "$name"
  if [[ "$dry_run" -eq 1 ]]; then
    printf 'DRY-RUN: %s\n' "$command"
    return 0
  fi
  if [[ "${FOP_LOCAL_CI_DIRECT:-0}" == "1" ]] || ! command -v "$queue_bin" >/dev/null 2>&1; then
    # Direct mode (no queue wrapper): explicit opt-in OR queue binary not on
    # PATH (GitHub Actions runners, fresh contributor boxes). The kernel
    # + earlyoom handle resource pressure when the active wrapper is not
    # available.
    bash -euo pipefail -c "$command"
    return 0
  fi
  run_fop_step interactive-small "$command"
}

run_step_heavy() {
  local name="$1"
  local command="$2"
  printf '\n==> %s (heavy)\n' "$name"
  if [[ "$dry_run" -eq 1 ]]; then
    printf 'DRY-RUN: %s\n' "$command"
    return 0
  fi
  if [[ "${FOP_LOCAL_CI_DIRECT:-0}" == "1" ]] || ! command -v "$queue_bin" >/dev/null 2>&1; then
    bash -euo pipefail -c "$command"
    return 0
  fi
  run_fop_step batch-medium "$command"
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
  printf '\n==> %s [SKIP: %s]\n' "$name" "$reason"
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

compute_diff_paths
compute_design_smoke_gate

# Allocate a unique Laravel dev-server port for this run before any step
# spawns `php artisan serve`. Exported so child processes (scripts/e2e-local.sh,
# the inline playwright block, schemathesis, wait-for-url) all see it.
SERVER_PORT="$(allocate_laravel_port)"
export SERVER_PORT
printf 'ℹ Laravel dev-server port: %s (override with FOP_LOCAL_CI_LARAVEL_PORT)\n' "$SERVER_PORT"

post_commit_status "pending" "fop local ${profile} running"

run_direct "working tree whitespace" "git diff --check"
run_direct "ui polish contract" "scripts/tests/test-ui-polish-contract.sh"
run_direct "recommendation contract gate" "bash scripts/check-recommendation-contract.sh"
run_direct "legal-readiness wording contract" "scripts/tests/test-legal-readiness-wording.sh"
run_direct "legal/module OpenAPI contract" "scripts/tests/test-legal-openapi-contract.sh"

# ---------------- Backend (PHP) ---------------------------------------------
if (( diff_touch_php )); then
  run_step "composer validate" "composer validate --strict"

  # composer audit is advisory-only on the pr profile so dev-only or
  # unfixable advisories cannot block routine work. cd profile re-runs
  # it with --no-dev for a stricter prod-only pass.
  run_step "composer audit (advisory)" "composer audit --no-interaction || echo 'composer audit returned non-zero (advisory on pr profile)'"

  run_step "composer install (sync)" "composer install --prefer-dist --no-interaction --no-progress"

  run_step "pint format check" "./vendor/bin/pint --test"

  run_step "phpstan level 5" "scripts/ci/phpstan-analyse.sh --memory-limit=512M --no-progress"

  run_step "phpunit unit + feature" "./vendor/bin/phpunit --testsuite=Unit --no-coverage && ./vendor/bin/phpunit --testsuite=Feature --no-coverage"
else
  skip_step "composer validate" "diff-aware: no PHP backend inputs touched"
  skip_step "composer audit (advisory)" "diff-aware: no PHP backend inputs touched"
  skip_step "composer install (sync)" "diff-aware: no PHP backend inputs touched"
  skip_step "pint format check" "diff-aware: no PHP backend inputs touched"
  skip_step "phpstan level 5" "diff-aware: no PHP backend inputs touched"
  skip_step "phpunit unit + feature" "diff-aware: no PHP backend inputs touched"
fi

# ---------------- Frontend (Astro 5 + React 19 + Vitest 3) ------------------
if (( diff_touch_frontend || diff_touch_e2e )); then
  run_step "frontend npm install" "npm ci && npm ci --prefix parkhub-web"

  run_step "frontend vitest" "cd parkhub-web && npm test"

  run_step "frontend build" "cd parkhub-web && CI=true npm run build && cd .. && CI=true npm run build"

  if (( diff_touch_design_smoke )); then
    # The design-smoke harness boots Laravel even for frontend-only diffs.
    # Clean Dependabot worktrees may not have vendor/ when PHP gates are
    # diff-skipped, so ensure Composer deps exist before scripts/e2e-local.sh.
    run_step "composer install for Laravel design smoke" "if [[ -f vendor/autoload.php ]]; then echo 'vendor/autoload.php already present'; else composer install --prefer-dist --no-interaction --no-progress; fi"
    run_step_heavy "frontend route + v5 design smoke" "npm run test:e2e:design-smoke"
  else
    skip_step "frontend route + v5 design smoke" "diff-aware: no route/design/e2e files touched"
  fi

  # tsc --noEmit on parkhub-web is not yet green on main as of 4.15.0 —
  # the `chore/web-tsc-phase4c-*` series (PRs #379..#382 and ongoing) is
  # still chipping away at hundreds of inherited TS errors. Keep this after
  # hard frontend gates and make the fop wrapper advisory too, so host pressure
  # cannot fail the PR gate before the intentionally non-gating check completes.
  run_advisory_step_heavy "frontend typecheck (advisory until tsc-phase4 lands)" "cd parkhub-web && NODE_OPTIONS=\"\${NODE_OPTIONS:-} --max-old-space-size=4096\" ./node_modules/.bin/tsc --noEmit || echo 'tsc errors present (advisory while phase4 is in flight)'"
else
  skip_step "frontend npm install" "diff-aware: no frontend/e2e inputs touched"
  skip_step "frontend vitest" "diff-aware: no frontend/e2e inputs touched"
  skip_step "frontend build" "diff-aware: no frontend/e2e inputs touched"
  skip_step "frontend route + v5 design smoke" "diff-aware: no route/design/e2e files touched"
  skip_step "frontend typecheck (advisory until tsc-phase4 lands)" "diff-aware: no frontend/e2e inputs touched"
fi

# ---------------- Drift gates -----------------------------------------------
# Both scripts already follow the same pattern as the rust side: they
# regenerate the snapshot, then fail if `git diff --exit-code` shows drift.
if (( diff_touch_php )); then
  run_step "openapi drift" "scripts/check-openapi-drift.sh"
else
  skip_step "openapi drift" "diff-aware: no PHP API inputs touched"
fi

# In parkhub-php this is a no-op (the shared TS API types are
# generated by ts-rs in parkhub-rust and committed into parkhub-web
# read-only). Keep it for symmetry with parkhub-rust's local-ci so
# operators can read the same step list, but label it explicitly so
# nobody mistakes the always-pass for a real drift signal.
if (( diff_touch_php || diff_touch_frontend )); then
  run_step "types drift (no-op in php; gated by parkhub-rust)" "scripts/check-types-drift.sh"
else
  skip_step "types drift (no-op in php; gated by parkhub-rust)" "diff-aware: no PHP or frontend inputs touched"
fi

# ---------------- Local OSS security mirror ---------------------------------
# Mirrors the GitHub/Gitea security + workflow hygiene jobs with local
# open-source tools. Missing optional tools are surfaced but do not block the
# standard PR gate; run `make ci-security` for the strict local toolchain check.
security_profile="pr"
if [[ "$profile" == "cd" ]]; then
  security_profile="cd"
fi
if (( diff_touch_security )); then
  run_step "local security audit (${security_profile} mirror)" "scripts/ci/local-security-audit.sh --profile ${security_profile}"
else
  skip_step "local security audit (${security_profile} mirror)" "diff-aware: no security-relevant inputs touched"
fi

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

  run_step_heavy "playwright chromium e2e" "e2e_db=\"\${FOP_LOCAL_CI_E2E_DB:-/tmp/parkhub-e2e-\$\$.sqlite}\"; rm -f \"\$e2e_db\"; export DB_CONNECTION=sqlite DB_DATABASE=\"\$e2e_db\" DEMO_MODE=true PARKHUB_ADMIN_PASSWORD=demo PARKHUB_DISABLE_RATE_LIMITS=true E2E_BASE_URL=http://127.0.0.1:\${SERVER_PORT}; ./scripts/ci/bootstrap-laravel.sh && php artisan migrate:fresh --seed --seeder=ProductionSimulationSeeder --force --no-interaction && CI=true npm run build:php --prefix parkhub-web && pid=''; cleanup() { if [[ -n \"\${pid:-}\" ]]; then kill \"\$pid\" 2>/dev/null || true; fi; rm -f \"\$e2e_db\"; }; trap cleanup EXIT; { php artisan serve --host=127.0.0.1 --port=\${SERVER_PORT} >/tmp/parkhub-e2e-\${SERVER_PORT}.log 2>&1 & pid=\$!; }; ./scripts/ci/wait-for-url.sh http://127.0.0.1:\${SERVER_PORT}/api/v1/health/live 60 && npx playwright test e2e/api.spec.ts e2e/pages.spec.ts e2e/v5-a11y.spec.ts --project=chromium"
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
trivy_should_run="$trivy_required"
[[ "$profile" == "pr" && "$diff_touch_security" -eq 1 ]] && trivy_should_run=1
if [[ "$trivy_should_run" -eq 0 ]]; then
  skip_step "trivy filesystem scan" "diff-aware: no security-relevant inputs touched"
elif command -v trivy >/dev/null 2>&1; then
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
if (( ! diff_touch_workflows )); then
  skip_step "zizmor (GHA SAST)" "diff-aware: no workflow inputs touched"
elif command -v zizmor >/dev/null 2>&1; then
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

if [[ "$dry_run" -eq 1 ]]; then
  printf '\ndry-run local CI completed; no success report or commit status was written.\n'
else
  write_report "success"
  post_commit_status "success" "fop local ${profile} passed"

  printf '\nlocal CI passed: %s\n' "$report_path"
fi
