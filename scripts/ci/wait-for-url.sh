#!/usr/bin/env bash

set -euo pipefail

url="${1:?url is required}"
timeout_seconds="${2:-60}"

for ((i = 1; i <= timeout_seconds; i++)); do
  if curl -fsS "${url}" > /dev/null; then
    exit 0
  fi

  sleep 1
done

echo "Timed out waiting for ${url}" >&2
exit 1
