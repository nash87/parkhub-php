#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage: scripts/ci/local-security-audit.sh [--profile pr|cd] [--strict-tools] [--fail-advisory]

Runs the local OSS security mirror for the GitHub/Gitea security and workflow
hygiene jobs. Default mode mirrors GitHub PR behavior: required gates fail,
advisory gates report findings without failing the run, and missing optional
OSS tools are reported. Use --strict-tools before a release to require the full
local toolchain to be installed.

Profiles:
  pr  Composer prod audit, npm prod audits, gitleaks, zizmor, typos, and
      workflow/manifest hygiene when local tools are present.
  cd  pr + Trivy filesystem scan for HIGH/CRITICAL vuln/misconfig findings.

Environment:
  FOP_SECURITY_BASE_REF  Base ref for PR-style gitleaks range (default:
                         github/main, falling back to origin/main).
  ZIZMOR_ARGS            Extra zizmor args (default: --persona=auditor).
EOF
}

profile="pr"
strict_tools=0
fail_advisory=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --profile)
      profile="${2:?missing profile}"
      shift 2
      ;;
    --strict-tools)
      strict_tools=1
      shift
      ;;
    --fail-advisory)
      fail_advisory=1
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
  pr|cd) ;;
  *)
    echo "invalid profile: $profile" >&2
    exit 2
    ;;
esac

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

missing_tools=()
advisory_failures=()

tool_path() {
  command -v "$1" 2>/dev/null || true
}

require_core_tool() {
  local tool="$1"
  if [[ -z "$(tool_path "$tool")" ]]; then
    echo "required tool missing: $tool" >&2
    exit 1
  fi
}

optional_tool() {
  local tool="$1"
  if [[ -n "$(tool_path "$tool")" ]]; then
    return 0
  fi
  missing_tools+=("$tool")
  return 1
}

section() {
  printf '\n==> %s\n' "$1"
}

run_required() {
  local name="$1"
  shift
  section "$name"
  "$@"
}

run_advisory() {
  local name="$1"
  shift
  section "$name (advisory)"
  if "$@"; then
    return 0
  fi
  advisory_failures+=("$name")
  echo "$name returned non-zero (advisory in GitHub mode)"
  if [[ "$fail_advisory" -eq 1 ]]; then
    return 1
  fi
}

run_if_available() {
  local tool="$1"
  local name="$2"
  shift 2
  if optional_tool "$tool"; then
    run_required "$name" "$@"
  else
    section "$name"
    echo "$tool not installed; skipping"
  fi
}

run_advisory_if_available() {
  local tool="$1"
  local name="$2"
  shift 2
  if optional_tool "$tool"; then
    run_advisory "$name" "$@"
  else
    section "$name (advisory)"
    echo "$tool not installed; skipping"
  fi
}

require_core_tool git
require_core_tool composer
require_core_tool npm
require_core_tool python3

section "local security profile"
echo "profile=$profile strict_tools=$strict_tools fail_advisory=$fail_advisory"

run_required "composer audit (prod locked)" composer audit --locked --no-dev --no-interaction

run_advisory "npm audit root (prod high)" npm audit --package-lock-only --omit=dev --audit-level=high
run_advisory "npm audit parkhub-web (prod high)" npm audit --prefix parkhub-web --package-lock-only --omit=dev --audit-level=high

if optional_tool gitleaks; then
  section "secret scan (gitleaks)"
  base_ref="${FOP_SECURITY_BASE_REF:-github/main}"
  if ! git rev-parse --verify --quiet "$base_ref" >/dev/null; then
    base_ref="origin/main"
  fi
  if git rev-parse --verify --quiet "$base_ref" >/dev/null; then
    base_sha="$(git merge-base HEAD "$base_ref")"
    gitleaks detect --source=. --redact --verbose --no-banner --log-opts="--no-merges ${base_sha}..HEAD"
  else
    echo "no base ref available; scanning all reachable history"
    gitleaks detect --source=. --redact --verbose --no-banner
  fi
else
  section "secret scan (gitleaks)"
  echo "gitleaks not installed; skipping"
fi

run_if_available actionlint "workflow lint (actionlint)" actionlint .github/workflows
run_if_available yamllint "yaml lint" yamllint -c .yamllint.yml .github/workflows .gitea/workflows docker-compose.yml render.yaml koyeb.yaml
run_required "fly config parse" python3 -c "import pathlib, tomllib; tomllib.loads(pathlib.Path('fly.toml').read_text())"
run_if_available helm "helm chart render" bash -euo pipefail -c "helm lint ./helm/parkhub --set config.appKey=base64:ci-dummy-app-key && helm template parkhub ./helm/parkhub --set config.appKey=base64:ci-dummy-app-key >/dev/null && helm template parkhub ./helm/parkhub --set config.appKey=base64:ci-dummy-app-key --set grafana.dashboardsEnabled=true >/dev/null && helm template parkhub ./helm/parkhub --set config.appKey=base64:ci-dummy-app-key --set monitoring.serviceMonitor.enabled=true >/dev/null && helm template parkhub ./helm/parkhub --set config.appKey=base64:ci-dummy-app-key --set monitoring.prometheusRule.enabled=true >/dev/null"
run_if_available docker "docker compose config" docker compose -f docker-compose.yml config -q

zizmor_args=()
if [[ -n "${ZIZMOR_ARGS:-}" ]]; then
  # shellcheck disable=SC2206
  zizmor_args=(${ZIZMOR_ARGS})
else
  zizmor_args=(--persona=auditor)
fi
run_advisory_if_available zizmor "zizmor (gha sast audit-mode)" zizmor "${zizmor_args[@]}" .github/workflows
run_advisory_if_available typos "typos" typos .

# ─── OSV-Scanner (Apache-2.0, github.com/google/osv-scanner) ────────────────
# Multi-ecosystem SCA against the OSV.dev database. Covers composer.lock,
# package-lock.json, parkhub-web/package-lock.json in one pass and surfaces
# CVEs that may not yet have a Composer/npm advisory ID. Advisory-only — the
# composer-audit + npm-audit gates above remain the required posture.
#
# Why OSV-Scanner instead of Bearer for "SAST coverage": Bearer is licensed
# under Elastic License 2.0 (source-available, banned per the platform's
# commercial-license-safe doctrine, same family as BSL/SSPL/FSL). Semgrep is
# LGPL-2.1 (also banned). OSV-Scanner is Google-maintained Apache-2.0 and
# delivers the equivalent multi-ecosystem secure-supply-chain signal.
run_advisory_if_available osv-scanner "osv-scanner (multi-ecosystem SCA)" \
  osv-scanner scan source \
    --config=osv-scanner.toml \
    -L composer.lock \
    -L package-lock.json \
    -L parkhub-web/package-lock.json

if [[ "$profile" == "cd" ]]; then
  run_if_available trivy "trivy filesystem scan" trivy fs --severity HIGH,CRITICAL --exit-code 1 --skip-dirs vendor,node_modules,parkhub-web/node_modules .
fi

if [[ "$strict_tools" -eq 1 && "${#missing_tools[@]}" -gt 0 ]]; then
  section "missing tools"
  printf '%s\n' "${missing_tools[@]}" | sort -u
  echo "install missing tools or rerun without --strict-tools" >&2
  exit 1
fi

if [[ "${#advisory_failures[@]}" -gt 0 ]]; then
  section "advisory failures"
  printf '%s\n' "${advisory_failures[@]}"
fi

section "local security audit passed"
