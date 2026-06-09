---
version: "1.0"
last_reviewed: 2026-06-09
source: CONTRIBUTING.md, DEVELOPMENT.md, SECURITY.md
---

# ParkHub PHP — Project Constitution

This document captures the non-negotiable principles that govern every
contribution to `nash87/parkhub-php`. It is the authoritative reference for
spec-kit slash-commands and for resolving design disputes.

---

## Product boundaries

- ParkHub PHP is the self-hosted, privacy-first PHP edition of the ParkHub
  parking management product. It shares the same HTTP API contract and React
  frontend with `nash87/parkhub-rust`.
- The canonical deployment targets are: shared hosting (3 EUR/mo), Docker
  Compose, VPS/LAMP, PaaS (Render, Railway), and Kubernetes via the bundled
  Helm chart.
- Zero cloud, zero tracking, zero data-processing agreements required for
  basic use. Every data storage and processing decision must be defensible
  under GDPR/DSGVO by design — not as a retrofit.
- The Helm chart ships with the full Kubernetes Pod Security Standards
  **restricted** profile by default. Relaxing any security context requires a
  documented justification in the PR.

## API contract

- The PHP and Rust editions expose the same OpenAPI contract. Any endpoint
  change must land in both repos in the same PR window, or be explicitly
  documented as a parity gap in `docs/openapi-parity.md`.
- The OpenAPI snapshot at `docs/openapi/php.json` is the single source of
  truth. `make drift` blocks any handler change that forgets to regenerate it.
- Breaking API changes require a CHANGELOG entry, a migration guide in
  `docs/API.md`, and the `X-API-Version` deprecation header on removed
  endpoints.

## Security non-negotiables

- No raw SQL with user input. Use Eloquent ORM or bound Query Builder
  parameters at all times.
- Every new API endpoint requires: 401 without token, 403 for unauthorized
  role, 422 for invalid input, and at least one happy-path feature test.
- File uploads must be validated via GD content check
  (`imagecreatefromstring()`). SVG is blocked from branding uploads.
- No secrets, credentials, internal IP addresses, or operator home paths may
  appear in any committed file. `gitleaks` runs on every PR.
- Supply-chain integrity: `composer audit`, `npm audit --omit=dev`,
  `roave/security-advisories`, Trivy, and SBOM attestation run on every
  release. Do not suppress advisories — fix the dependency.

## Code quality floor

- PHP style: Laravel Pint (PSR-12). Run `./vendor/bin/pint --test` before
  every commit. The CI gate is `make lint`.
- Static analysis: Larastan/PHPStan level 4. No new baseline entries without
  a documented reason.
- Frontend style: Biome (replaces ESLint + Prettier). Run
  `npx biome check src/` before push.
- Test coverage contract: new features require feature tests for the happy
  path, authorization boundaries, and at least two edge cases. Mutation
  testing (Infection, nightly) tracks survivors — do not suppress.
- Lefthook pre-push is the local mirror of GitHub CI. Bypassing it with
  `--no-verify` is permitted only for time-critical hotfixes and must be
  flagged in the PR body.

## Module system

- Every module must be togglable via a `MODULE_*` environment variable.
- When a module is disabled, its routes must return 404 — not 403 or 500.
- Feature work on a new module requires tests that verify the disabled state.

## Spec-driven development

- Non-trivial features (labeled `feature` or `enhancement`) require a spec
  file at `specs/<feature-id>/spec.md` before a PR is opened.
  Use `/speckit.specify` to bootstrap the spec from an issue description.
- The spec describes requirements and user stories only — no implementation
  detail. The implementation plan goes in `specs/<feature-id>/plan.md`.
- Existing planning documents live in `docs/plans/` and remain valid; new
  work uses the `specs/` hierarchy going forward.
- Link your spec in the PR template "Spec reference" field and check the
  `spec_written` checkbox in the feature-request issue template.

## What we never do

- We do not publish internal IP addresses, operator home paths, or Gitea
  remote URLs in any file committed to the public GitHub repo.
- We do not suppress security advisories in `composer.json` or `deny.toml`.
  Fix the dependency or document the accepted risk explicitly.
- We do not relax `readOnlyRootFilesystem: true` in the Helm chart default
  profile without operator approval and a documented rollback path.
- We do not commit credentials or tokens. ESO/Vault manages secrets at
  runtime.
- We do not let the PHP SECURITY.md version table fall more than one minor
  behind the current release.
