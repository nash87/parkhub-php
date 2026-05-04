#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

args=("$@")
has_memory_limit=0
has_no_progress=0
has_debug=0

for arg in "${args[@]}"; do
  case "$arg" in
    --memory-limit|--memory-limit=*)
      has_memory_limit=1
      ;;
    --no-progress)
      has_no_progress=1
      ;;
    --debug)
      has_debug=1
      ;;
  esac
done

can_bind_loopback_socket() {
  php <<'PHP'
<?php
$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) {
    exit(1);
}

fclose($server);
PHP
}

cmd=(./vendor/bin/phpstan analyse)

if [[ "$has_memory_limit" -eq 0 ]]; then
  cmd+=(--memory-limit=512M)
fi

if [[ "$has_no_progress" -eq 0 ]]; then
  cmd+=(--no-progress)
fi

if [[ "$has_debug" -eq 0 ]] && ! can_bind_loopback_socket; then
  echo "phpstan: local TCP sockets unavailable; using serial --debug mode" >&2
  cmd+=(--debug)
fi

cmd+=("${args[@]}")
exec "${cmd[@]}"
