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

if [ -n "${GITHUB_ACTIONS:-}" ]; then
  echo "::group::${label} (timeout ${duration}, kill-after ${kill_after})" >&2
else
  echo "==> ${label} (timeout ${duration}, kill-after ${kill_after})" >&2
fi

set +e
timeout --kill-after="${kill_after}" "${duration}" "$@"
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
    echo "::error::Timed out after ${duration}: ${label}" >&2
    ;;
  137)
    echo "::error::Command was killed after timeout grace period: ${label}" >&2
    ;;
  143)
    echo "::error::Command was terminated: ${label}" >&2
    ;;
esac

exit "${status}"
