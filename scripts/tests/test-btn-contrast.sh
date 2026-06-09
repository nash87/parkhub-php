#!/usr/bin/env bash
# WCAG AA contrast contract for .btn-primary (parkhub-web, vendored
# securanido palette). Regression guard for the white-on-#ab7220 (4.07:1)
# violation that shipped with the v5 token vendoring.
set -euo pipefail

cd "$(dirname "$0")/../.."

echo "==> btn-primary WCAG AA contrast contract"
python3 scripts/check-btn-contrast.py
