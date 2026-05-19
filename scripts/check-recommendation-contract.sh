#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fixture="docs/recommendation-engine-fixtures/weighted_v1.basic.json"
expected_fixture_sha="fe8ffc6a8cdb645f48ded1bebcaf3f48eb4d8576c95520a75378e2f4394b4bfa"

require_file() {
  local path="$1"
  if [[ ! -f "$path" ]]; then
    echo "ERROR: missing $path" >&2
    exit 1
  fi
}

require_grep() {
  local pattern="$1"
  shift
  if ! grep -R -n --fixed-strings "$pattern" "$@" >/dev/null; then
    echo "ERROR: missing recommendation contract pattern: $pattern" >&2
    echo "       in: $*" >&2
    exit 1
  fi
}

require_grep_each() {
  local pattern="$1"
  shift
  local path
  for path in "$@"; do
    require_grep "$pattern" "$path"
  done
}

sha256_file() {
  local path="$1"
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$path" | awk '{print $1}'
  elif command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$path" | awk '{print $1}'
  else
    echo "ERROR: neither sha256sum nor shasum is available" >&2
    exit 1
  fi
}

require_file "$fixture"
actual_fixture_sha="$(sha256_file "$fixture")"
if [[ "$actual_fixture_sha" != "$expected_fixture_sha" ]]; then
  echo "ERROR: $fixture hash drifted: $actual_fixture_sha" >&2
  echo "       expected: $expected_fixture_sha" >&2
  echo "       Update both PHP/Rust fixtures, tests, and this gate together." >&2
  exit 1
fi

require_grep '"algorithm": "weighted_v1"' "$fixture"
require_grep '"slot_id": "slot-usual"' "$fixture"
require_grep '"score": 69' "$fixture"

require_grep_each 'fop_pipeline_v1' \
  app/Http/Controllers/Api/RecommendationController.php \
  app/Services/ModuleRegistry.php \
  tests/Feature/RecommendationExtendedTest.php \
  docs/recommendation-engine-contract.md
require_grep "'algorithm' => env('RECOMMENDATION_ALGORITHM', 'weighted_v1')" config/recommendations.php
require_grep 'fallback_algorithm=weighted_v1' docs/recommendation-engine-contract.md
require_grep "'fallback_algorithm' => 'weighted_v1'" app/Http/Controllers/Api/RecommendationController.php
require_grep "data_get(\$request->data(), 'fallback_algorithm') === 'weighted_v1'" tests/Feature/RecommendationExtendedTest.php
require_grep_each 'RecommendationServed' app/Http/Controllers/Api/RecommendationController.php docs/recommendation-engine-contract.md
require_grep "'adapter' =>" app/Http/Controllers/Api/RecommendationController.php
require_grep "'event_type' => 'RecommendationServed'" app/Http/Controllers/Api/RecommendationController.php
require_grep 'pipeline_endpoint rejected' app/Http/Controllers/Api/RecommendationController.php
require_grep 'test_fop_pipeline_v1_success_reorders_known_candidates' tests/Feature/RecommendationExtendedTest.php
require_grep 'test_fop_pipeline_v1_falls_back_when_endpoint_missing' tests/Feature/RecommendationExtendedTest.php
require_grep 'test_fop_pipeline_v1_rejects_external_endpoint_and_falls_back' tests/Feature/RecommendationExtendedTest.php
require_grep "str_ends_with(\$host, '.svc')" app/Http/Controllers/Api/RecommendationController.php
require_grep "str_ends_with(\$host, '.svc.cluster.local')" app/Http/Controllers/Api/RecommendationController.php
require_grep "str_ends_with(\$host, '.test')" app/Http/Controllers/Api/RecommendationController.php
require_grep 'https://example.com' tests/Feature/RecommendationExtendedTest.php
require_grep "'enum' => ['weighted_v1', 'fop_pipeline_v1']" app/Services/ModuleRegistry.php
require_grep "'execution_allowed' => false" app/Http/Controllers/Api/RecommendationController.php
require_grep "['legal_boundary']['execution_allowed']" tests/Feature/RecommendationExtendedTest.php
require_grep 'execution_allowed=false' docs/recommendation-engine-contract.md

echo "ParkHub PHP recommendation contract gate OK."
