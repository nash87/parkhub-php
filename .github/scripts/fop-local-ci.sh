#!/usr/bin/env bash
# Deprecation shim: fop-local-ci.sh → nido-local-ci.sh
# This script is kept for backward compatibility with scripts, docs, and muscle
# memory referencing the old name. All logic lives in nido-local-ci.sh.
exec "$(dirname "$0")/nido-local-ci.sh" "$@"
