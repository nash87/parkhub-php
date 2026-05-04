#!/usr/bin/env bash
# generate-vex.sh — Generate VEX (Vulnerability Exploitability eXchange)
# from the accepted-risk baseline below, so downstream SBOM consumers can
# filter known non-exploitable or accepted findings.
#
# Usage:
#   bash scripts/generate-vex.sh > vex.csaf.json
#
# Integrates with:
#   - Syft SBOM (add --vex vex.csaf.json to syft scan)
#   - Grype (--vex vex.csaf.json)
#   - Trivy (--vex vex.csaf.json, v0.55+)

set -euo pipefail

if [[ $# -ne 0 ]]; then
  echo "usage: bash scripts/generate-vex.sh > vex.csaf.json" >&2
  exit 2
fi

DATE=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
REPO_HOST="${GITHUB_SERVER_URL:-https://github.com}"
REPO_PATH="${GITHUB_REPOSITORY:-nash87/parkhub-php}"
REPO_URL="${REPO_HOST}/${REPO_PATH}"
COMMIT="${GITHUB_SHA:-unknown}"

# ── Known accepted vulnerabilities (edit as baseline changes) ──
declare -A ACCEPTED_RISKS=(
  # Format: [CVE-YYYY-NNNN]="justification|impact_statement"
  # Keep PHP/Composer/npm accepted risks here. Do not copy RustSec IDs from
  # parkhub-rust; they describe a different package graph.
)

cat <<EOF
{
  "document": {
    "category": "csaf_vex",
    "csaf_version": "2.0",
    "publisher": {
      "category": "vendor",
      "name": "fop Security",
      "namespace": "${REPO_URL}"
    },
    "title": "VEX for ${REPO_URL} @ ${COMMIT}",
    "tracking": {
      "id": "vex-${COMMIT:0:8}",
      "status": "final",
      "version": "1.0.0",
      "initial_release_date": "${DATE}",
      "current_release_date": "${DATE}"
    }
  },
  "product_tree": {
    "branches": [
      {
        "category": "product_name",
        "name": "parkhub-php",
        "branches": [
          {
            "category": "product_version",
            "name": "${COMMIT:0:8}",
            "product": {
              "product_id": "parkhub-php:${COMMIT:0:8}"
            }
          }
        ]
      }
    ]
  },
  "vulnerabilities": [
EOF

FIRST=1
for id in "${!ACCEPTED_RISKS[@]}"; do
  IFS='|' read -r justification impact <<< "${ACCEPTED_RISKS[$id]}"
  if [ "$FIRST" -eq 0 ]; then
    echo ","
  fi
  FIRST=0
  cat <<ENTRY
    {
      "cve": "${id}",
      "product_status": {
        "known_not_affected": ["parkhub-php:${COMMIT:0:8}"]
      },
      "threats": [
        {
          "category": "impact",
          "details": "${impact}"
        }
      ],
      "notes": [
        {
          "category": "description",
          "text": "${justification}: ${impact}",
          "title": "VEX Justification"
        }
      ]
    }
ENTRY
done

echo ""
echo "  ]"
echo "}"
