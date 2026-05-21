#!/usr/bin/env bash
#
# Static guard for legal-readiness wording.
#
# ParkHub ships controls and templates that support legal-readiness work, but
# live legal posture depends on operator configuration, contracts, jurisdiction,
# and attorney review. Keep public docs from drifting back to absolute legal
# conclusions.
#
# Run: bash scripts/tests/test-legal-readiness-wording.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

scan_paths=(
    README.md
    docs
    legal
    parkhub-web/src
    resources/js/src
)

if ! command -v rg >/dev/null 2>&1; then
    echo "ERROR: ripgrep (rg) is required for the legal-readiness wording guard." >&2
    echo "Install ripgrep or run this check in the project CI image." >&2
    exit 1
fi

pattern="100%[[:space:]-]+GDPR|GDPR[[:space:]-]+compliant|DSGVO[[:space:]-]+konform|Compliance[[:space:]-]+Audited|complies with all GDPR|No DPA needed|no GDPR processor agreement needed|no mandatory data processor agreements|DPIA is NOT required|not required for typical deployments|legally compliant|guarantees compliance|legal compliance is guaranteed|certifies compliance|(GDPR|DSGVO|compliance|legal)[^.\n]{0,40}(certified|guaranteed)|(certified|guaranteed)[^.\n]{0,40}(GDPR|DSGVO|compliance|legal)"

if rg --pcre2 --ignore-case -n "$pattern" "${scan_paths[@]}"; then
    echo "ERROR: legal-readiness docs contain absolute compliance wording." >&2
    echo "Use deployment-dependent wording and require operator/legal review." >&2
    exit 1
fi

require_text() {
    local file="$1"
    local text="$2"

    if [ ! -r "$file" ]; then
        echo "ERROR: required file not found or unreadable: $file" >&2
        exit 1
    fi

    if ! grep -Fq "$text" "$file"; then
        echo "ERROR: $file is missing required legal-readiness text: $text" >&2
        exit 1
    fi
}

require_text docs/release-checklist.md "scripts/tests/test-legal-readiness-wording.sh"
require_text docs/release-checklist.md "scripts/tests/test-legal-openapi-contract.sh"
require_text docs/release-checklist.md "docs/legal-readiness.md"
require_text docs/release-checklist.md "docs/deployment-readiness-record.md"
require_text docs/release-checklist.md "docs/legal-readiness-parity.md"
require_text docs/release-checklist.md "legal-readiness evidence"
require_text docs/release-checklist.md "Nido/fop legal catalog service"
require_text docs/release-checklist.md "fop legal catalog --json"
require_text docs/release-checklist.md "nido legal"
require_text docs/release-checklist.md "not exposed by the installed Nido"
require_text docs/release-checklist.md "CLI yet"
require_text docs/release-checklist.md "reference-only"
require_text docs/release-checklist.md "attorney review"
require_text docs/release-checklist.md "citation"
require_text docs/release-checklist.md "human signoff"
require_text docs/release-checklist.md "deployment-specific configuration review"
require_text docs/release-checklist.md "source_revision"
require_text docs/release-checklist.md "generated_at"
require_text docs/release-checklist.md "requires_attorney_review"
require_text docs/release-checklist.md "requires_human_signoff"
require_text docs/release-checklist.md "execution_allowed"
require_text docs/release-checklist.md "safety_boundary"
require_text docs/release-checklist.md "docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md"
require_text docs/release-checklist.md 'GitHub `nash87/parkhub-php` remains the CI/review source of truth'
require_text README.md "docs/legal-readiness.md"
require_text README.md "docs/deployment-readiness-record.md"
require_text README.md "docs/legal-readiness-parity.md"
require_text README.md "Nido/fop legal catalog service"
require_text README.md "fop legal catalog --json"
require_text README.md "nido legal"
require_text README.md "not exposed by the installed Nido"
require_text README.md "attorney review"
require_text README.md "human signoff"
require_text docs/legal-readiness.md "Operator Legal Readiness Hub"
require_text docs/legal-readiness.md "It is not legal advice"
require_text docs/legal-readiness.md "not a legal conclusion"
require_text docs/legal-readiness.md "qualified counsel"
require_text docs/legal-readiness.md "attorney review"
require_text docs/legal-readiness.md "citation verification"
require_text docs/legal-readiness.md "human signoff"
require_text docs/legal-readiness.md "deployment-specific configuration review"
require_text docs/legal-readiness.md "Nido/fop legal catalog service"
require_text docs/legal-readiness.md "fop legal catalog --json"
require_text docs/legal-readiness.md "not exposed by the installed Nido"
require_text docs/legal-readiness.md "CLI yet"
require_text docs/legal-readiness.md "source_revision"
require_text docs/legal-readiness.md "generated_at"
require_text docs/legal-readiness.md "requires_attorney_review=true"
require_text docs/legal-readiness.md "requires_human_signoff=true"
require_text docs/legal-readiness.md "execution_allowed=false"
require_text docs/legal-readiness.md "reference-only"
require_text docs/legal-readiness.md "scripts/tests/test-legal-openapi-contract.sh"
require_text docs/legal-readiness.md "deployment-readiness-record.md"
require_text docs/legal-readiness.md "legal-readiness-parity.md"
require_text docs/deployment-readiness-record.md "# Deployment Readiness Record"
require_text docs/deployment-readiness-record.md "Personal, business, or mixed use"
require_text docs/deployment-readiness-record.md "Germany-specific obligations"
require_text docs/deployment-readiness-record.md "Module And Plugin Review"
require_text docs/deployment-readiness-record.md "AI/ML / recommendations"
require_text docs/deployment-readiness-record.md "Required Signoff"
require_text docs/deployment-readiness-record.md "Final human go-live signoff"
require_text docs/deployment-readiness-record.md "Nido/fop legal catalog service"
require_text docs/deployment-readiness-record.md "Legal Catalog Service Evidence"
require_text docs/deployment-readiness-record.md "NO_COLOR=true fop legal catalog --json"
require_text docs/deployment-readiness-record.md "legal-catalog-<deployment-or-sha>.json"
require_text docs/deployment-readiness-record.md "Catalog id / source / version"
require_text docs/deployment-readiness-record.md "Captured by / date"
require_text docs/deployment-readiness-record.md "source_revision"
require_text docs/deployment-readiness-record.md "generated_at"
require_text docs/deployment-readiness-record.md "requires_attorney_review"
require_text docs/deployment-readiness-record.md "requires_human_signoff"
require_text docs/deployment-readiness-record.md "execution_allowed"
require_text docs/deployment-readiness-record.md "safety_boundary"
require_text docs/deployment-readiness-record.md "docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "# 2026-05-21 Exact-Cover Nido/fop Legal Catalog Evidence"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "NO_COLOR=true fop legal catalog --json"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "source_revision"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "generated_at"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "requires_attorney_review"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "requires_human_signoff"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "execution_allowed"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "not legal advice"
require_text docs/release-evidence/2026-05-21-exact-cover-nido-legal-catalog.md "deployment-specific configuration review"
require_text docs/legal-readiness-parity.md "# Legal Readiness Parity"
require_text docs/legal-readiness-parity.md "Rust and PHP"
require_text docs/legal-readiness-parity.md "Module/plugin review"
require_text docs/legal-readiness-parity.md "Nido/fop legal catalog service"
require_text docs/legal-readiness-parity.md "fop legal catalog --json"
require_text docs/legal-readiness-parity.md "not exposed by the installed Nido"
require_text docs/legal-readiness-parity.md "CLI yet"
require_text docs/legal-readiness-parity.md "qualified counsel"
require_text docs/legal-readiness-parity.md "current head SHA"
require_text docs/legal-readiness-parity.md ".fop/reports/local-ci-pr-<full-sha>.json"
require_text docs/GDPR.md "Legal Readiness Checklist"
require_text docs/COMPLIANCE.md "Legal Readiness Checklist"
require_text .github/scripts/fop-local-ci.sh "FOP_LOCAL_CI_QUEUE_BIN"
require_text .github/scripts/fop-local-ci.sh "supports_queue_build nido"
require_text .github/scripts/fop-local-ci.sh "\"\$queue_bin\" build"

echo "ParkHub legal-readiness wording contract OK."
