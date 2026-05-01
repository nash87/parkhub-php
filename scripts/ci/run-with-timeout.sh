#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "usage: $0 <duration> <command> [args...]" >&2
  exit 2
fi

duration="$1"
shift

kill_after="${CI_TIMEOUT_KILL_AFTER:-30s}"
label="${CI_TIMEOUT_LABEL:-$*}"

if command -v timeout >/dev/null 2>&1; then
  timeout_bin="timeout"
elif command -v gtimeout >/dev/null 2>&1; then
  timeout_bin="gtimeout"
else
  echo "ERROR: GNU timeout is required; install coreutils or run in GitHub Actions/Linux CI." >&2
  exit 127
fi

emit_error() {
  if [ -n "${GITHUB_ACTIONS:-}" ]; then
    echo "::error::$1" >&2
  else
    echo "ERROR: $1" >&2
  fi
}

if [ -n "${GITHUB_ACTIONS:-}" ]; then
  echo "::group::${label} (timeout ${duration}, kill-after ${kill_after})" >&2
else
  echo "==> ${label} (timeout ${duration}, kill-after ${kill_after})" >&2
fi

set +e
"${timeout_bin}" --kill-after="${kill_after}" "${duration}" "$@"
status=$?
set -e

if [ -n "${GITHUB_ACTIONS:-}" ]; then
  echo "::endgroup::" >&2
fi

case "${status}" in
  0)
    exit 0
    ;;
  124)
    emit_error "Timed out after ${duration}: ${label}"
    ;;
  137)
    emit_error "Command was killed after timeout grace period: ${label}"
    ;;
  143)
    emit_error "Command was terminated: ${label}"
    ;;
esac

exit "${status}"
