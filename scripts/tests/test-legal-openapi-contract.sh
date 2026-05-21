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

const specPath = 'docs/openapi/php.json';
if (!fs.existsSync(specPath)) {
  console.error(`ERROR: required OpenAPI snapshot not found: ${specPath}`);
  console.error('Regenerate and commit the PHP OpenAPI snapshot before running this guard.');
  process.exit(1);
}

let spec;
try {
  spec = JSON.parse(fs.readFileSync(specPath, 'utf8'));
} catch (error) {
  console.error(`ERROR: failed to read or parse ${specPath}: ${error.message}`);
  console.error('Regenerate and commit a valid PHP OpenAPI snapshot before running this guard.');
  process.exit(1);
}

const paths = spec.paths || {};

const required = [
  ['GET', '/api/v1/legal/impressum'],
  ['GET', '/legal/privacy'],
  ['GET', '/api/v1/admin/privacy'],
  ['PUT', '/api/v1/admin/privacy'],
  ['GET', '/api/v1/admin/impressum'],
  ['PUT', '/api/v1/admin/impressum'],
  ['GET', '/api/v1/users/me/export'],
  ['POST', '/api/v1/users/me/anonymize'],
  ['DELETE', '/api/v1/users/me/delete'],
  ['GET', '/api/v1/admin/compliance/report'],
  ['GET', '/api/v1/admin/compliance/data-map'],
  ['GET', '/api/v1/admin/compliance/audit-export'],
  ['GET', '/api/v1/modules'],
  ['GET', '/api/v1/modules/{name}'],
  ['PATCH', '/api/v1/admin/modules/{name}'],
  ['GET', '/api/v1/admin/plugins'],
  ['GET', '/api/v1/admin/plugins/{id}/config'],
  ['PUT', '/api/v1/admin/plugins/{id}/config'],
  ['PUT', '/api/v1/admin/plugins/{id}/toggle'],
];

function candidatePaths(path) {
  if (path.startsWith('/api/v1/')) {
    return [path, path.replace(/^\/api\/v1/, '/v1')];
  }
  return [path];
}

let failed = false;

for (const [method, path] of required) {
  const entry = candidatePaths(path)
    .map((candidate) => paths[candidate])
    .find((candidate) => candidate && candidate[method.toLowerCase()]);
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
