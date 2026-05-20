#!/usr/bin/env bash
#
# Static OpenAPI guard for legal-readiness and module/plugin surfaces.
#
# This checks the generated OpenAPI snapshot only; it does not start Laravel.
# Keep this guard cheap so docs/legal policy work can run it under pressure.
#
# Run: bash scripts/tests/test-legal-openapi-contract.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

node <<'NODE'
const fs = require('fs');

const spec = JSON.parse(fs.readFileSync('docs/openapi/php.json', 'utf8'));
const paths = spec.paths || {};

const required = [
  ['GET', '/v1/legal/impressum'],
  ['GET', '/legal/privacy'],
  ['GET', '/v1/admin/privacy'],
  ['PUT', '/v1/admin/privacy'],
  ['GET', '/v1/admin/impressum'],
  ['PUT', '/v1/admin/impressum'],
  ['GET', '/v1/users/me/export'],
  ['POST', '/v1/users/me/anonymize'],
  ['DELETE', '/v1/users/me/delete'],
  ['GET', '/v1/admin/compliance/report'],
  ['GET', '/v1/admin/compliance/data-map'],
  ['GET', '/v1/admin/compliance/audit-export'],
  ['GET', '/v1/modules'],
  ['GET', '/v1/modules/{name}'],
  ['PATCH', '/v1/admin/modules/{name}'],
  ['GET', '/v1/admin/plugins'],
  ['GET', '/v1/admin/plugins/{id}/config'],
  ['PUT', '/v1/admin/plugins/{id}/config'],
  ['PUT', '/v1/admin/plugins/{id}/toggle'],
];

let failed = false;

for (const [method, path] of required) {
  const entry = paths[path];
  if (!entry || !entry[method.toLowerCase()]) {
    console.error(`ERROR: OpenAPI snapshot missing ${method} ${path}`);
    failed = true;
  }
}

if (failed) {
  process.exit(1);
}

console.log('ParkHub PHP legal/module OpenAPI contract OK.');
NODE
