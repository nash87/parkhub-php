# parkhub-php — Developer Guide

This doc covers the local dev loop, the local CI mirror (Makefile + pre-commit + act),
and the GitHub Actions hardening we rely on. It is the companion to the
`.github/workflows/*.yml` files — those workflows remain the source of truth, every
thing here exists to reproduce them locally before `git push`.

---

## 1. Quickstart

```bash
# Clone (Gitea = origin, GitHub = github)
git clone git@192.168.178.220:florian/parkhub-php.git
cd parkhub-php
git remote add github https://github.com/nash87/parkhub-php.git

# Bootstrap
composer install
npm ci
npm ci --prefix parkhub-web

# Env + Laravel
cp .env.example .env
php artisan key:generate
./scripts/ci/bootstrap-laravel.sh

# Run
php artisan serve
```

Requires: PHP 8.4, Composer v2, Node 22, npm. `pre-commit` (Python) and `act`
(Go / Docker) are optional but recommended.

---

## 2. Pre-commit hooks

We use the [`pre-commit`](https://pre-commit.com) framework. All hook revs in
`.pre-commit-config.yaml` are SHA-pinned — same discipline as the Actions
workflows. Bump with `pre-commit autoupdate --freeze`.

```bash
pip install --user pre-commit
pre-commit install                 # runs on every `git commit`
pre-commit install --hook-type pre-push   # runs PHPStan on every `git push`
pre-commit run --all-files         # one-off, entire repo
```

Hooks (summary):

| Stage      | Hook                                    | Source                               |
|------------|-----------------------------------------|--------------------------------------|
| pre-commit | trailing-whitespace, end-of-file-fixer  | `pre-commit/pre-commit-hooks@v6.0.0` |
| pre-commit | check-yaml, check-json, check-merge-conflict, check-added-large-files | same |
| pre-commit | `laravel/pint@v1.29.0` (`--test`)       | upstream                             |
| pre-commit | `composer validate --strict`            | local                                |
| pre-push   | `vendor/bin/phpstan` (memory-limit=512M) | local                               |

---

## 3. `make ci` — the core local gate

The Makefile mirrors the **reproducible local subset** of
`.github/workflows/ci.yml`. Run **`make ci` before `git push`** for fast local
feedback, then use `make act` when you need to execute the actual workflow
YAML.

```bash
make ci          # lint + static-analysis + test + frontend + drift
make lint        # pint --test (mirrors backend-quality job)
make static-analysis  # phpstan (mirrors static-analysis job)
make test        # full backend PHPUnit suite (mirrors backend-tests job)
make drift       # openapi snapshot diff (mirrors openapi-drift.yml)
make frontend    # npm ci + build (mirrors frontend job)
make pre-push    # alias for make ci
```

`make ci` intentionally covers the fast local checks: lint, static analysis,
backend tests, frontend build/tests, and OpenAPI drift. Workflow-only jobs such
as `workflow-hygiene`, `docker-validate`, `e2e-smoke`, and `integration` still
run in GitHub Actions / `act`.

See the comment block at the top of `Makefile` — any target that claims to
mirror a workflow job **must not diverge** from that job. If a workflow job
changes, update the corresponding make target in the same commit.

Shared feature/API changes also need the cross-runtime docs kept in sync:
[docs/parity-governance.md](docs/parity-governance.md),
[docs/openapi-parity.md](docs/openapi-parity.md), and
[docs/release-checklist.md](docs/release-checklist.md).

---

## 4. `act` — run the actual workflows locally

[`nektos/act`](https://github.com/nektos/act) executes the YAML workflows
inside a container. This catches Actions-syntax bugs that `make ci` misses.

```bash
# Install
brew install act                                             # macOS / Linuxbrew
curl -fsSL https://raw.githubusercontent.com/nektos/act/master/install.sh | sudo bash

make act                     # runs .github/workflows/ci.yml
act -W .github/workflows/openapi-drift.yml
act -l                       # list every job/workflow
```

`.actrc` (repo root) pins:

- `-P ubuntu-latest=catthehacker/ubuntu:act-latest` — smallest image that
  resolves ~95% of the marketplace actions we use (setup-php, setup-node,
  cache, buildx). The full (~15 GB) image is overkill; `micro`/`medium` break
  too many actions.
- `--container-architecture linux/amd64` — QEMU emulation on arm is flaky for
  Composer + npm installs, and our Docker targets are amd64 anyway.

---

## 5. Dual-remote push convention

Gitea is `origin` (private canonical). GitHub (`nash87/parkhub-php`) is a
mirror for Actions + visibility.

```bash
git push origin main
git push github main
```

One-liner helper (add to `~/.gitconfig`):

```ini
[alias]
    pa = "!git push origin \"$(git rev-parse --abbrev-ref HEAD)\" && git push github \"$(git rev-parse --abbrev-ref HEAD)\""
```

Then `git pa` pushes both. **Always `git pull --rebase origin main` before
either push** — Flux-style automation may have rewritten tags.

---

## 6. GitHub Pro hardening we leverage

All workflows live in `.github/workflows/` and use these 2025-current
primitives ([docs.github.com/en/actions](https://docs.github.com/en/actions)):

- **SHA-pinned actions** — every `uses:` references a commit SHA with a
  `# v<tag>` comment. Dependabot (Actions ecosystem, weekly) keeps them fresh.
- **Concurrency groups** — every workflow sets
  `concurrency: { group: <workflow>-<ref>, cancel-in-progress: true }` so
  superseded PR pushes auto-cancel. See
  [docs.github.com/.../using-concurrency](https://docs.github.com/en/actions/using-jobs/using-concurrency).
- **Caching** — `actions/cache@v5` for Composer (`~/.composer/cache`),
  npm (built into `setup-node@v6`), Playwright browsers
  (`~/.cache/ms-playwright`), and GHA-native BuildKit cache for Docker.
- **Artifact retention** — `actions/upload-artifact@v7` with
  `retention-days: 7` for Playwright reports + server logs.
- **CodeQL** — `codeql.yml` currently scans the JS/TS surfaces on every PR.
- **Dependency review** — PRs run `actions/dependency-review-action`, and the
  result is now folded into the main `required` gate in `ci.yml`.
- **Secret scan** — `gitleaks` (MIT) binary direct in `security.yml` on every
  PR over the full git history; replaced trufflehog (AGPL) on 2026-04-25 (#365).
  Composer audit weekly; Trivy FS + image scan on every Dockerfile change.
- **Artifact attestations** — `docker/build-push-action@v7` chains
  `actions/attest-build-provenance@v4` to publish SLSA v1 provenance for every
  pushed image. See
  [docs.github.com/.../artifact-attestations](https://docs.github.com/en/actions/security-for-github-actions/using-artifact-attestations).
- **SBOM** — generated per build (Syft via buildx), uploaded alongside the
  provenance attestation.
- **Branch protection** — `main` requires green `required` job
  (aggregates: workflow-hygiene, dependency-review, backend-tests,
  docker-validate, static-analysis, integration, openapi-drift, etc.) and 1
  review. Set in GitHub Settings → Branches.
- **Environments** — not wired yet (no external deploy targets on GitHub — we
  deploy from Gitea via Flux). When we do wire them, use GitHub
  [Environments](https://docs.github.com/en/actions/managing-workflow-runs-and-deployments/managing-deployments/managing-environments-for-deployment)
  with required reviewers + wait-timers.
- **Dependency graph** — native, used by Dependabot + dependency-review.

Periodic workflows: `nightly.yml` (extended tests), `infection.yml`
(mutation testing, weekly), `lighthouse.yml` (perf budget).

---

## 7. OpenAPI contract parity

`parkhub-php` and `parkhub-rust` both expose the same HTTP contract. Any
schema change must land in both repos in the same PR window.

- Snapshot: `docs/openapi/php.json`
- Drift gate: `make drift` (= bootstrap SQLite + `composer openapi:dump` + `git diff --exit-code`)
- Workflow: `.github/workflows/openapi-drift.yml` and the main `ci.yml`
  `openapi-drift` job
- Contract guide: [`docs/openapi-parity.md`](docs/openapi-parity.md)

If CI fails on `openapi-drift`, run `composer openapi:dump` and commit the
regenerated `docs/openapi/php.json`.

---

## 8. Troubleshooting

| Symptom                             | Fix                                                          |
|-------------------------------------|--------------------------------------------------------------|
| `pint --test` fails                 | `./vendor/bin/pint` (auto-fix), then commit                  |
| `phpstan` fails                     | Read the baseline in `phpstan-baseline.neon`; regenerate with `./vendor/bin/phpstan analyse --generate-baseline` only after fixing real issues |
| `openapi-drift` fails               | `composer openapi:dump && git add docs/openapi/php.json`     |
| `act` fails but CI is green         | You probably need `--container-architecture linux/amd64` (already in `.actrc`) or a larger runner image |
| Pre-commit wants to rewrite files   | It's auto-fixing whitespace/EOL — `git add -u` and commit again |

Always run `make pre-push` before pushing. CI on GitHub is slow; failing
locally is free.
