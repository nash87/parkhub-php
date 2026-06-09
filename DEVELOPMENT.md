# parkhub-php — Developer Guide

This doc covers the local dev loop, the local-first CI/CD gate, the internal
runner mirror, and the GitHub Actions hardening we rely on. The rule is: use
GitHub `nash87/parkhub-php` as the canonical repository, run the expensive
proof locally through `fop`, let the internal Gitea runner mirror catch
cluster-near issues, and keep GitHub as the external gate that checks the
posted `fop/local-ci/pr` status.

---

## 1. Quickstart

```bash
# Fresh clone: GitHub is canonical.
git clone git@github.com:nash87/parkhub-php.git
cd parkhub-php

# Optional: add a private mirror remote here (internal use only, not required).

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
| pre-push   | `make ci` (`fop-local-ci.sh --profile pr`) | local                             |

---

## 3. `make ci` — the canonical local gate

The Makefile delegates to `.github/scripts/fop-local-ci.sh`, which serializes
every non-trivial step through `fop build`. This is the local source of truth
for same-repo PRs. GitHub branch protection expects a successful
`fop/local-ci/pr` commit status, posted by `make ci-post`.

```bash
make ci          # fop local PR gate, no GitHub status post
make ci-post     # fop local PR gate + post fop/local-ci/pr
make full        # PR gate + Schemathesis/Infection/Playwright extras
make cd          # full + release-oriented audit/scan/smoke
make ci-security # strict local OSS security/workflow mirror
make lint        # pint --test (mirrors backend-quality job)
make static-analysis  # phpstan (mirrors static-analysis job)
make test        # full backend PHPUnit suite (mirrors backend-tests job)
make drift       # openapi snapshot diff (mirrors openapi-drift.yml)
make frontend    # npm ci + build (mirrors frontend job)
make pre-push    # alias for make ci
```

`make ci` covers the blocking local PR surface: Composer metadata/audit,
Pint, PHPStan, PHPUnit Unit+Feature, frontend install/typecheck/Vitest/build,
OpenAPI drift, PHP-side types drift, and workflow SAST when `zizmor` is
installed. Same-repo GitHub PRs skip the heavyweight duplicate jobs unless
the `github-ci-full` label is present; forks and `main` pushes still run the
full GitHub workflow.

`make ci-security` is the stricter local OSS pendant for the GitHub/Gitea
security and workflow jobs. It runs Composer and npm production audits,
gitleaks, actionlint, yamllint, Helm/Docker manifest validation, Zizmor, Typos,
and Trivy filesystem scanning locally. Default `make ci` uses the same script in
GitHub-mode so fresh clones report missing optional tools instead of failing;
`make ci-security` adds `--strict-tools --fail-advisory` for release cleanup.

The lower-level make targets remain as debugging entrypoints, but they are not
the merge gate. If a workflow job changes, update `.github/scripts/fop-local-ci.sh`,
the Makefile help text, and the relevant GitHub/Gitea workflow in the same
commit.

Shared feature/API changes also need the cross-runtime docs kept in sync:
[docs/parity-governance.md](docs/parity-governance.md),
[docs/openapi-parity.md](docs/openapi-parity.md), and
[docs/release-checklist.md](docs/release-checklist.md).

---

## 4. GitHub, Gitea mirror, and `act`

GitHub `nash87/parkhub-php` is the canonical repo. Gitea is only an internal
runner/mirror surface for this project. The `.gitea/workflows/*` files mirror
the GitHub workflows with action refs rewritten to the local Gitea mirror where
we have one, but the CI workflow intentionally remains fuller than a normal
same-repo GitHub PR. Use it to catch runner, packaging, and deploy-shape issues
before GitHub is asked to spend minutes failing on something local or internal
CI could have caught.

`act` is still useful for local YAML execution when you need runner semantics.

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

## 5. Remote convention

GitHub (`nash87/parkhub-php`) is the source of truth. Fresh clones should use
GitHub as `origin`.

```bash
git remote -v
git pull --rebase origin main
git push origin <branch>
```

Some workstation clones still have stale Gitea as `origin` and GitHub as
`github`. In those clones, use GitHub explicitly and do not base work on
`origin/main`:

```bash
git fetch github
git pull --rebase github main
git push github <branch>
```

Keep any Gitea remote named `gitea-restore` or similar unless an operator
explicitly asks to restore mirroring. Do not use it for PR bases, release
preflight updates, or GitOps build inputs.

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
  PR over the PR diff range; replaced trufflehog (AGPL) on 2026-04-25 (#365).
  Composer audit, npm audit, Zizmor, Typos, workflow hygiene, and Trivy are
  mirrored locally by `scripts/ci/local-security-audit.sh` / `make ci-security`.
- **Artifact attestations** — `docker/build-push-action@v7` chains
  `actions/attest-build-provenance@v4` to publish SLSA v1 provenance for every
  pushed image. See
  [docs.github.com/.../artifact-attestations](https://docs.github.com/en/actions/security-for-github-actions/using-artifact-attestations).
- **SBOM** — generated per build (Syft via buildx), uploaded alongside the
  provenance attestation.
- **Local-first branch protection** — same-repo PRs require the `required` job,
  which in turn requires `fop local CI attestation`. That job only accepts a
  successful `fop/local-ci/pr` commit status from `make ci-post`.
- **Full fallback** — fork PRs, `main` pushes, and PRs labelled `github-ci-full`
  run the heavy GitHub jobs directly. This keeps external contributions honest
  while avoiding duplicate GitHub failures for local branches.
- **Environments** — not wired yet (no external deploy targets on GitHub; Flux
  consumes ParkHub from the GitHub source-of-truth path). When we do wire them,
  use GitHub
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
