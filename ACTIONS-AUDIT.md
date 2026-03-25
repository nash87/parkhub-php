# GitHub Actions Audit

## What existed before

| Workflow | Purpose | Key gaps found |
| --- | --- | --- |
| `ci.yml` | PHP tests, Pint, Composer audit, Astro frontend test/build | No workflow linting, no manifest validation, no Docker validation, no concurrency, no explicit job timeouts, no root Vite build coverage, no reliable E2E gate, and no PR-friendly required aggregate check. |
| `codeql.yml` | Code scanning | Only scanned `javascript-typescript`; PHP/Laravel was unscanned. |
| `dependency-review.yml` | PR dependency diff review | Used broader-than-needed permissions and had no concurrency controls. |
| `docker-publish.yml` | Build/push image, Trivy scan, Render deploy | Published only on tags/manual, scanned after push without gating, mixed image release with mutable Render env management, and lacked attestations/provenance. |
| `e2e.yml` | Playwright suite | Non-blocking by design, duplicated CI setup, and used a weaker setup path than the actual Laravel delivery path. |
| `lighthouse.yml` | Lighthouse audit | Ran on every PR and main push without being part of the release-critical lane. |
| `dependabot-auto-merge.yml` | Auto-merge Dependabot PRs | Too risky for Actions, Docker, and supply-chain updates in a public repo. |
| `copilot-setup-steps.yml` | Copilot bootstrap helper | Left intact; not part of the hardened merge/release path. |

## What changed

- Consolidated blocking PR and main-branch validation into a single hardened `ci.yml` with:
  - `actionlint`
  - `yamllint`
  - Helm lint/template validation
  - Docker Compose validation
  - Composer validation/install
  - PHP syntax linting
  - Laravel Pint check
  - full Laravel test suite on SQLite
  - root Vite build
  - `parkhub-web` test/build
  - blocking Chromium Playwright smoke tests
  - Docker build validation
  - a single `Required checks` gate job for branch protection
- Expanded `codeql.yml` to scan both `php` and `javascript-typescript`.
- Tightened `dependency-review.yml` permissions and added concurrency.
- Reworked `docker-publish.yml` to:
  - publish on `main` and release tags
  - fail the release path on High/Critical Trivy findings before publish
  - generate SBOM + provenance during push
  - attach GitHub build provenance attestations
  - separate safe demo deployment trigger from image publishing
  - remove Render environment mutation and hardcoded demo credential writes
- Moved deeper assurance into `nightly.yml`:
  - Composer production audit
  - `parkhub-web` production npm audit
  - MySQL smoke coverage for health/public endpoints
  - full cross-browser Playwright run
- Reduced noise in `lighthouse.yml` by moving it to scheduled/manual execution.
- Removed risky `dependabot-auto-merge.yml`.
- Added root npm Dependabot coverage in `.github/dependabot.yml`.
- Added small CI helper scripts for Laravel bootstrapping and readiness polling.
- Aligned release runtime versions by switching the Dockerfile to Node 22 LTS and PHP 8.4.
- Fixed release-path health probe mismatches in the Helm chart.

## Risks fixed

- Missing PHP CodeQL coverage on the primary backend attack surface.
- Non-blocking container vulnerability scanning that allowed vulnerable images to publish.
- Unbounded stale PR runs consuming CI minutes without concurrency cancellation.
- Overlapping CI/E2E workflows with inconsistent setup paths.
- Risky auto-merge of Dependabot PRs affecting GitHub Actions and Docker supply chain.
- Release automation that modified Render environment variables from CI, including known demo credentials.
- Deployment manifests pointing at inconsistent health endpoints.
- Untracked root npm dependency drift for repo tooling and Playwright assets.

## Intentionally out of scope

- Enabling Larastan/PHPStan as a blocking PR check. The repository currently has a non-trivial existing static-analysis baseline that must be cleaned up in application code first.
- Adding third-party SaaS services or heavyweight release orchestration.
- Reworking `copilot-setup-steps.yml`, which remains a utility workflow outside the merge/release gate.
- Pinning every third-party action to a commit SHA. The current update uses stable major versions to keep maintenance practical for a solo maintainer.

## Validation notes

- Blocking PR/main validation now uses SQLite because the existing PHPUnit configuration is explicitly optimized for in-memory SQLite.
- MySQL coverage is added in the nightly lane as a deterministic smoke path rather than a full PR matrix explosion.
- Playwright is split into:
  - required Chromium smoke tests on PRs and `main`
  - broader cross-browser coverage nightly
- Lighthouse remains available, but no longer burns contributor minutes on every PR.

## Recommended branch protection

### Mandatory PR checks

- `Required checks`
- `CodeQL / Analyze (php)`
- `CodeQL / Analyze (javascript-typescript)`
- `Dependency Review / Review dependency changes`

### Main-branch-only checks

- `Release Container / Build, scan, and publish image`
- `Release Container / Deploy demo` (only if the demo environment remains desired)

### Scheduled or nightly checks

- `Nightly Assurance / Dependency audit`
- `Nightly Assurance / MySQL smoke tests`
- `Nightly Assurance / Full cross-browser E2E`
- `Lighthouse CI / Lighthouse audit`

### Release-only checks

- Require signed tags or protected release tags matching `v*`
- Require the container publish workflow to complete successfully before any production promotion
- Prefer deployment by image digest, not mutable tags
