---
title: "T-2705 ParkHub CI and local-dev reconciliation handoff"
repo_path: "/var/home/florian/dev/parkhub-php"
type: "handoff"
priority: "high"
status: "in_progress"
task_id: "T-2705"
updated: "2026-05-05 02:41 CEST"
---

# T-2705 ParkHub CI and local-dev reconciliation handoff

## Scope

Work on ParkHub only. GitHub `nash87/parkhub-rust` and `nash87/parkhub-php`
are the source of truth; local Gitea origins are stale and must not be used as
the base for ParkHub work.

## Prompt-to-artifact checklist

Objective: get ParkHub local dev CI/CD, GitHub CI/CD, GitOps build chain, Rust,
PHP, and fop-first coordination into a working, auditable state.

| Requirement | Current evidence | Status |
|---|---|---|
| Work ParkHub-only | `T-2705` is the active fop task; all current branches are `parkhub-*` or `flux-infra.parkhub-gitops`. | In progress |
| GitHub is canonical for Rust/PHP | Rust PR #588 and PHP PR #447 merged the canonical-remote / mirror-maintenance docs and workflow updates into GitHub. | Done |
| Rust GitHub PR for current bug fix | PR #587 (`fix/absence-config-icon-mock`) merged on GitHub at `2026-05-04T20:29:25Z`, merge commit `add3cd40e07dec3e6d5aae336d7f8506bea531e7`. | Done |
| PHP local PR gate | PR #447 pushed at `c59ab0dd8339f6cbab673562d4a505fd808ab87e`; required checks and full local CI attestation passed before merge. | Done for shipped PHP PRs |
| PHP release/preflight e2e base URL | PR #446 merged on GitHub at `2026-05-04T20:30:41Z`, merge commit `75fc7bb139d59feada26f2ad0b2537a57f8b2325`. | Done |
| PHP Admin Updates route/API contract | Current `github/main` contains `routes/modules/updates.php`, `UpdateController`, `AdminUpdatesRouteTest`, and OpenAPI `/v1/admin/updates/*` paths; local history shows hardening commit `317f93e`. | Done |
| PHP local GitHub VEX metadata | PR #448 merged on GitHub at `2026-05-04T20:31:42Z`, merge commit `2aafe330ce1042fc89024c63e3fce1172a936e37`; the follow-up main Release Container run signed and SBOM-attested successfully. | Done |
| Rust local GitHub VEX metadata | PR #588 merged on GitHub at `2026-05-04T20:30:12Z`, merge commit `5a0ebabb2378161c07d5aa298d16ce7f0c5b6c3d`. | Done |
| Rust workflow drift gate | Covered by merged Rust PR #588 (`docs/github-canonical-remotes`). | Done |
| GitHub action refs pinned | PHP Dependabot PR #445 merged at `2026-05-04T23:43:36Z`, merge commit `7f00134ea0ab33c5d66af8c425b21db1e4d7d73e`, after cosign/SBOM rerun and full local PR gate passed at `679ea5b91d8da219e5e00d175ba0ebd2f3351cfe`. | Done |
| PHP Gitea mirror IP refresh | PHP PR #447 merged on GitHub at `2026-05-04T20:31:12Z`, merge commit `93fcd4f8b744ac603401d6168b1f4ac8e3c2bc46`. | Done |
| Flux/GitOps build chain | Gitea Flux PR #178 is open with an explicit review-only body; host-network `tea pulls` confirmed it at `2026-05-05 00:37 CEST`. | Review-only |
| Local `.test` router | Host-network probe at `2026-05-05 00:38 CEST` returned `ok=true` through `127.0.0.1:39080/_parkhub-local/health` for `parkhub.test`, `parkhub-rust.test`, and `parkhub-php.test`; Rust port `8081`, PHP port `8082`. | Done |
| fop-first coordination | `T-2705` claimed/checkpointed with branch heads, verification, blockers, and resume commands. | Current |

Known incomplete items before the objective can be called done:

- PHP Dependabot PR #443 has a passing local PR gate at
  `642ead927f7293c36d2b4fb282bf3056be0d63b4`; its GitHub CI rerun/merge is
  blocked by the Codex usage-limit window until `2026-05-05 05:35 CEST`.
- PHP Dependabot PR #444 is still open and should wait until #443 merges or
  rebases because both touch the `parkhub-web` dependency surface.
- The remaining open PHP Dependabot PRs' GitHub CI workflows are waiting on the
  separate `fop/local-ci/pr` commit status from
  `.github/scripts/fop-local-ci.sh --profile pr --post-status`.
- PHP Dependabot PR #441 merged at `2026-05-05T00:05:09Z`, merge commit
  `b2fcf7d03443a2162c899fa691d983f0857ccc56`, after local PR gate report
  `.fop/reports/local-ci-pr-02773f832105a6e586841005677822aa1d440687.json`
  and CI rerun `25342003627` passed.
- PHP Dependabot PR #442 merged at `2026-05-05T00:25:16Z`, merge commit
  `10ff140f61c8707d6dae65b1c801fc1c0424780a`, after local PR gate report
  `.fop/reports/local-ci-pr-23b58ad998f59f354303f93aac6b2e294223d467.json`
  and CI rerun `25342004322` passed.
- Do not start local CI attestations while `fop queue status` shows an active
  build reservation. The latest source blocker is external GitHub access, not
  local ParkHub source drift.
- Re-query security findings after the Dependabot PRs merge and after the
  completed PHP Release Container run has propagated its new Trivy/code-scanning
  results.
- Keep Flux PR #178 review-only until an operator reviews the GitOps rollout.
- Commit/push this handoff update from a clean branch or worktree; the current
  `parkhub-php` checkout is still on `fix/release-preflight-e2e-base-url` with
  `docs/plans/` untracked.

## Live continuation update: 2026-05-04 22:40 CEST

- Installed/configured Gitea CLI path is `tea` at `/home/florian/.local/bin/tea`
  (`Tea version 0.1.0-dev`). Flux PR #178 was created through Gitea and remains
  review-only.
- Merged Rust PR #587 and #588 with head-SHA guards after required checks were
  green.
- Merged PHP PR #446, #447, and #448 with head-SHA guards after required checks
  were green.
- Updated PHP Dependabot PR branches #441-#445 through the GitHub update-branch
  API. New heads: #441 `02773f832105a6e586841005677822aa1d440687`,
  #442 `23b58ad998f59f354303f93aac6b2e294223d467`,
  #443 `642ead927f7293c36d2b4fb282bf3056be0d63b4`,
  #444 `3cc9482df36cb138adbcd009d602aec7184fc204`,
  #445 `679ea5b91d8da219e5e00d175ba0ebd2f3351cfe`.
- The initial #445 cosign failure was a race with the just-merged PHP main
  release image. Main run `25341955517` finished successfully, including
  provenance, cosign signing, SBOM generation, and SBOM attestation; rerun
  `25342026033` for #445 then passed.
- Current blocker is not source drift: same-repo PR CI is waiting for local
  `fop/local-ci/pr` statuses. Run the local attestation only after fop capacity
  is free.
- Security refresh: Rust Dependabot alerts `0`, Rust secret-scanning alerts
  `0`, and Rust code-scanning alerts `0`; PHP Dependabot alerts `0` and PHP
  secret-scanning alerts `0`. PHP still has `444` open Trivy code-scanning
  alerts on `main` (`92` high, `309` medium, `2` low, `41` note), so the
  remaining security lane is image/base/runtime CVE cleanup rather than
  Dependabot or secret exposure.
- Trivy root cause and fix started: representative high alert #2166 is
  `linux-libc-dev 6.12.74-2` retained in the published PHP image, fixed in
  `6.12.85-1`. Created
  `/var/home/florian/dev/parkhub-php.trivy-runtime-prune` from `github/main`,
  branch `fix/trivy-runtime-build-deps`, commit `e9aa60c`. The Dockerfile now
  purges build-only Debian extension headers after `docker-php-ext-install` and
  keeps only runtime library packages/manual extension dependencies. Static
  review tightened the `dpkg-query -S` pipeline so unowned `ldd` paths cannot
  fail the image build while package-owned runtime libraries are still marked
  manual; ShellCheck also prompted replacing unquoted `$savedAptMark` splitting
  with `printf | xargs`. Verified with `git diff --check`, Trivy Dockerfile
  config scan, ShellCheck on the extracted RUN body, and pre-commit; still
  needs fop-backed image build and Trivy proof after external host actions are
  allowed.
- PHP Dependabot PR #445 local gate passed at
  `679ea5b91d8da219e5e00d175ba0ebd2f3351cfe`; report:
  `.fop/reports/local-ci-pr-679ea5b91d8da219e5e00d175ba0ebd2f3351cfe.json` in
  `/var/home/florian/dev/parkhub-php.dependabot-ci`. The JSON is a compact
  status manifest only (`schema=parkhub.local-ci.v1`, `state=success`,
  `context=fop/local-ci/pr`); step-level evidence came from the local
  `fop-local-ci.sh` run output and fop checkpoints. That run showed PHPStan no
  errors, PHPUnit Unit `448` tests / `976` assertions, Feature `1366` tests /
  `6178` assertions, Vitest `174` files / `2500` tests, Astro builds, OpenAPI
  sync, local security audit pass, Trivy filesystem clean, and final advisory
  Zizmor no findings after ignores.
- PHP Dependabot PR #445 merged at `2026-05-04T23:43:36Z`, merge commit
  `7f00134ea0ab33c5d66af8c425b21db1e4d7d73e`, after local PR gate
  `.fop/reports/local-ci-pr-679ea5b91d8da219e5e00d175ba0ebd2f3351cfe.json`,
  cosign/SBOM rerun, and required checks passed.
- PHP Dependabot PR #441 merged at `2026-05-05T00:05:09Z`, merge commit
  `b2fcf7d03443a2162c899fa691d983f0857ccc56`, after local PR gate
  `.fop/reports/local-ci-pr-02773f832105a6e586841005677822aa1d440687.json`,
  CI rerun `25342003627`, and required checks passed.
- PHP Dependabot PR #442 merged at `2026-05-05T00:25:16Z`, merge commit
  `10ff140f61c8707d6dae65b1c801fc1c0424780a`, after local PR gate
  `.fop/reports/local-ci-pr-23b58ad998f59f354303f93aac6b2e294223d467.json`,
  CI rerun `25342004322`, and required checks passed.
- PHP Dependabot PR #443 local PR gate passed at
  `642ead927f7293c36d2b4fb282bf3056be0d63b4`; report:
  `.fop/reports/local-ci-pr-642ead927f7293c36d2b4fb282bf3056be0d63b4.json`.
  The attempted GitHub CI rerun `25342004199` was rejected by the Codex
  usage-limit guard; retry after `2026-05-05 05:35 CEST` and do not
  workaround.

Follow-up proof at `2026-05-05 00:36-00:38 CEST`:

- `fop queue status`: idle; `fop guard preflight`: green with `11794 MiB`
  available, load `1.42/16.00`, no active builds, and no orphans.
- `/home/florian/.local/bin/tea --version`: `Tea version 0.1.0-dev`.
- Host-network `tea pulls --login gitea-test --repo florian/flux-infra
  --output tsv` lists Flux PR #178
  `fix(ci): restore ParkHub GitOps build cronjobs`; it remains review-only.
- Host-network `curl -fsS
  http://127.0.0.1:39080/_parkhub-local/health` returned `ok=true`,
  `rustPort=8081`, `phpPort=8082`, and the expected ParkHub `.test` hosts.
- Local Git remote hygiene: `/var/home/florian/dev/parkhub-rust.github-canonical-docs`
  had an embedded credential in its local `forgejo` remote URL. It was replaced
  with credential-free `http://localhost:3002/florian/parkhub-rust.git`. A
  redacted scan of `/var/home/florian/dev/parkhub-*` git remotes then found no
  remaining credential-bearing HTTP(S) remote URLs.
- Local tracked-file hygiene: filename-level scan found only expected
  secret-shaped tracked paths (`.env.example`, Helm secret template, Laravel
  personal-access-token migration, and CSS design tokens). A path-only content
  scan for private-key, GitHub-token, and AWS-key markers returned no matches.
  This complements, but does not replace, the live GitHub secret-scanning
  refresh required during final audit.
- Follow-up queue checks at `2026-05-05 00:43-00:50 CEST` showed fop preflight
  red due unrelated fop cargo jobs (`13461`, `13462`, then `13464`). No ParkHub
  local CI or Podman build was started while the shared queue was occupied.
- Follow-up check at `2026-05-05 00:52 CEST` showed queue idle and fop preflight
  green again; GitHub/host Podman work remains deferred only because the
  external-action window is still closed until `01:41 CEST`.

## Completion audit status: 2026-05-04 23:12 CEST

Objective restated as deliverables:

- ParkHub Rust and PHP use GitHub as canonical source of truth, not stale Gitea.
- Local dev `.test` routing is verified for Rust and PHP.
- GitHub PRs for Rust/PHP work are opened/merged where clean, with required
  review/check/status evidence.
- Remaining Dependabot/security PRs have local-first CI evidence and are merged
  only after GitHub branch protection is clean.
- Security findings are re-triaged, with real fixes for actionable findings and
  no silent dismissal of GitHub Security alerts.
- MD/docs/README/handoff artifacts are updated so another agent can resume from
  live truth instead of stale recap state.

Prompt-to-artifact audit:

| Deliverable | Current evidence | Missing / next proof |
|---|---|---|
| Rust GitHub work | Rust PR #587 and #588 merged; no open Rust PRs in last GitHub refresh; Rust Dependabot/secret/code alerts were `0`. | Reconfirm after external window opens if doing final audit. |
| PHP shipped work | PHP PR #446, #447, #448 merged; Release Container run `25341955517` completed signing/SBOM. | Reconfirm final GitHub PR list after external window opens. |
| PHP Dependabot #445 | Merged at `2026-05-04T23:43:36Z`, merge commit `7f00134ea0ab33c5d66af8c425b21db1e4d7d73e`. | Done. |
| PHP Dependabot #441 | Merged at `2026-05-05T00:05:09Z`, merge commit `b2fcf7d03443a2162c899fa691d983f0857ccc56`. | Done. |
| PHP Dependabot #442 | Merged at `2026-05-05T00:25:16Z`, merge commit `10ff140f61c8707d6dae65b1c801fc1c0424780a`. | Done. |
| PHP Dependabot #443 | Local PR gate passed at `642ead927f7293c36d2b4fb282bf3056be0d63b4`; report `.fop/reports/local-ci-pr-642ead927f7293c36d2b4fb282bf3056be0d63b4.json`. | Retry GitHub CI rerun `25342004199` after `2026-05-05 05:35 CEST`, then merge with head-SHA guard if required checks pass. |
| PHP Dependabot #444 | Exact head fetched locally: `3cc9482df36cb138adbcd009d602aec7184fc204`. | Wait until #443 merges/rebases because #443 and #444 both touch `parkhub-web/package*.json`; then run/post local gate and merge only after required checks pass. |
| PHP image security | Representative high alert #2166 traced to retained `linux-libc-dev`; Dockerfile fix committed as `e9aa60c` in `/var/home/florian/dev/parkhub-php.trivy-runtime-prune`. | Host Podman build + Trivy image proof; then push/open PR. Sandbox build failed on read-only `/run/user/1000/libpod`; external retry blocked until `01:41 CEST`. |
| Flux/GitOps | Gitea Flux PR #178 exists and is explicitly review-only; host-network `tea pulls` confirmed it at `2026-05-05 00:37 CEST`. | Operator review; do not auto-merge/reconcile production. |
| Local `.test` | Host-network health probe returned `ok=true` at `2026-05-05 00:38 CEST` for `parkhub.test`, `parkhub-rust.test`, and `parkhub-php.test`, with Rust `8081` and PHP `8082`. | Re-probe only after local runtime changes or immediately before final completion if the runtime may have drifted. |
| Docs/MD | Handoff docs branch `docs/t-2705-handoff-status` in `/var/home/florian/dev/parkhub-php.t-2705-handoff-docs` carries this audit file; the same content is mirrored in the original task plan path. | Push/open docs PR after external window and pre-push gate constraints are satisfied. |

## Historical state before live push/merge recovery

This section records the pre-recovery state from Codex at
`2026-05-04 18:44-18:50 CEST`. It is superseded by the live continuation update
above for PR/merge status, but remains useful for the exact earlier local
verification commands and worktree paths.

- `fop queue status`: no active jobs, `headroom:8.9G`, `backpressure:accept`.
- `fop guard preflight --json`: green, `mem_avail_mib:9105`,
  `load_1m:2.87`, no active builds.
- Retried the highest-impact push,
  `git -c core.sshCommand='ssh -F /dev/null' push -u github fix/release-preflight-e2e-base-url`,
  but the Codex escalation reviewer rejected it due the usage-limit guard:
  retry after `8:40 PM`. Do not route around this; retry the same push once the
  approval/usage window is open.
- At `2026-05-04 18:48 CEST`, host-network `.test` probes were also rejected
  by the same Codex usage-limit guard. Do not route around this; retry the host
  probe after the approval/usage window opens.
- Offline CI/CD audit added Rust follow-up commits:
  `/var/home/florian/dev/parkhub-rust.github-canonical-docs` now points at
  `9e6a7245`. It documents `pr-title-lint.yml` and `stale.yml` as intentional
  GitHub-only workflow-drift exemptions, and refreshes the older tracked
  `.gitea/workflows/ci.yml` mirror from stale `.212`/bare `actions/checkout@v4`
  to the current `.233` pinned checkout mirror. The Rust local workflow drift
  verifier now exits 0; remaining drift is advisory job-shape drift only.
- Offline CI/CD audit also added a separate PHP mirror-maintenance branch:
  `/var/home/florian/dev/parkhub-php.gitea-mirror-ip`, branch
  `ci/gitea-mirror-ip-refresh`, commit `60d8108`. It refreshes 18
  `.gitea/workflows/**` files from the stale `.212:3000` action mirror and
  `gitea.test:.212` aliases to `.233`, pins safe core action refs using SHAs
  already present in the repo, and leaves four compatibility/tag-lookup refs
  explicit in its PR body.
- Sandbox-local curls to `127.0.0.1:{80,39080,8081,8082}` fail because the
  Codex sandbox has its own restricted network namespace. This is not proof
  that the host `.test` stack is down.
- ParkHub local runtime logs under
  `/var/tmp/florian-offload/tmp/parkhub-local-sites/` show the Rust and PHP
  child apps serving `/api/v1/demo/status` through `18:45`, so the remaining
  `.test` acceptance check is specifically a host-network/browser probe.

### parkhub-rust

- Repo: `/var/home/florian/dev/parkhub-rust`
- Branch: `fix/absence-config-icon-mock`
- Commit: `c4b05bea test: update absence config icon mock`
- Remote: pushed to `github/fix/absence-config-icon-mock`
- PR: `https://github.com/nash87/parkhub-rust/pull/587`
- Verification already passed:
  `npm test -- --run src/constants/absenceConfig.test.ts`
- Worktree is clean.

Additional local Rust docs branch:

- Repo: `/var/home/florian/dev/parkhub-rust.github-canonical-docs`
- Branch: `docs/github-canonical-remotes`
- Commit: `62835aeb docs: mark GitHub canonical for ParkHub Rust`
- Additional commit: `a43acbb3 ci: default Rust VEX metadata to GitHub`
- Additional commit: `f4d696fa ci: document GitHub-only workflow drift exemptions`
- Additional commit: `9e6a7245 ci: refresh Rust Gitea mirror checkout host`
- Purpose: updates `AGENTS.md` and `DEVELOPMENT.md` so future Rust agents do
  not base work on stale Gitea remotes as canonical; updates VEX metadata
  defaults so locally generated Rust VEX points at GitHub when GitHub Actions
  env vars are absent; updates `scripts/local-workflow-drift.sh` so the
  GitHub-only PR-title and stale-PR automations are exempted instead of
  incorrectly gating the Gitea mirror check; updates the older tracked
  `.gitea/workflows/ci.yml` from stale `.212` host references and bare
  `actions/checkout@v4` to the current `.233` pinned checkout mirror.
- Verification:
  - `git diff --check`
  - `bash -n scripts/generate-vex.sh`
  - `bash scripts/generate-vex.sh cargo-audit /dev/null | jq -r '.document.publisher.namespace'`
    returned `https://github.com/nash87/parkhub-rust`.
  - `bash -n scripts/local-workflow-drift.sh`
  - `bash scripts/local-workflow-drift.sh` exits 0; remaining workflow drift is
    advisory job-shape drift only.
  - stale `.212`, bare `actions/checkout@v4`, and unpinned-action scan over
    `.gitea/workflows` and `.github/workflows` returned no matches.
  - workflow YAML parse check over `.gitea/workflows/*.y*ml` and
    `.github/workflows/*.yml` passed.
  - stale canonical-Gitea phrase scan over `AGENTS.md` and `DEVELOPMENT.md`
    returned no matches.
- Push/PR still blocked by the same GitHub egress/usage-limit blocker.

Additional local PHP VEX branch:

- Repo: `/var/home/florian/dev/parkhub-php.github-vex-defaults`
- Branch: `ci/github-vex-defaults`
- Commit: `a171ba1 ci: default PHP VEX metadata to GitHub`
- Purpose: updates `scripts/generate-vex.sh` so locally generated PHP VEX
  points at GitHub when GitHub Actions env vars are absent. Kept separate from
  `fix/release-preflight-e2e-base-url` so the already-green PHP PR branch stays
  at the verified `a49f728` head.
- Verification:
  - `bash -n scripts/generate-vex.sh`
  - `bash scripts/generate-vex.sh | jq -r '.document.publisher.namespace'`
    returned `https://github.com/nash87/parkhub-php`.
  - `git diff --check`
- Push/PR still blocked by the same GitHub egress/usage-limit blocker.

Additional local PHP Gitea mirror branch:

- Repo: `/var/home/florian/dev/parkhub-php.gitea-mirror-ip`
- Branch: `ci/gitea-mirror-ip-refresh`
- Commit: `60d8108 ci: refresh PHP Gitea workflow mirror host`
- Purpose: updates 18 `.gitea/workflows/**` files from stale
  `192.168.178.212:3000` action mirror URLs and `gitea.test:.212` aliases to
  `192.168.178.233`; pins safe older checkout/setup-node/setup-php refs using
  known SHAs already present in the repo.
- Verification:
  - stale `.212` scan over `.gitea/workflows` returned no matches.
  - workflow YAML parse check over `.gitea/workflows/*.yaml` and
    `.github/workflows/*.yml` passed.
  - `git diff --check`
- Residual:
  - three `actions/upload-artifact@v3` refs remain intentionally unpinned
    because those Gitea-only browser-audit workflows document `@v3` as the
    compatible artifact API.
  - `microsoft/accessibility-insights-action@v3` still needs a live tag SHA
    lookup before pinning.
- PR body: `.fop/pr-bodies/ci-gitea-mirror-ip-refresh.md`
- Patch artifact: `.fop/patches/0001-ci-refresh-PHP-Gitea-workflow-mirror-host.patch`
- Push/PR still blocked by the same GitHub egress/usage-limit blocker.

### parkhub-php

- Repo: `/var/home/florian/dev/parkhub-php`
- Branch: `fix/release-preflight-e2e-base-url`
- Local commits:
  - `0d19804 ci: set e2e base url in release preflight`
  - `5902bc6 fix: register admin updates routes`
  - `ff6ba84 fix: make admin updates remote configurable`
  - `dd11c8c docs: mark GitHub canonical for ParkHub PHP`
  - `54716de ci: make phpstan gate tolerate socket-restricted shells`
  - `5457b80 test: update module count for updates module`
  - `a49f728 docs: refresh OpenAPI for admin updates`
- Files changed:
  - `.github/scripts/fop-local-ci.sh`
  - `scripts/ci/local-security-audit.sh`
  - `scripts/ci/phpstan-analyse.sh`
  - `config/modules.php`
  - `routes/api_v1.php`
  - `routes/modules/updates.php`
  - `tests/Feature/AdminUpdatesRouteTest.php`
  - `tests/Feature/ModuleSystemExtendedTest.php`
  - `tests/Feature/ModuleSystemTest.php`
  - `config/parkhub.php`
  - `app/Http/Controllers/Api/UpdateController.php`
  - `docs/openapi/php.json`
  - `Makefile`
  - `composer.json`
  - `AGENTS.md`
  - `DEVELOPMENT.md`
- Patch artifacts:
  `.fop/patches/0001-ci-set-e2e-base-url-in-release-preflight.patch`
  `.fop/patches/0003-docs-mark-github-canonical.patch`
  `.fop/patches/0004-ci-make-phpstan-gate-tolerate-socket-restricted-shel.patch`
  `.fop/patches/0005-test-update-module-count-for-updates-module.patch`
  `.fop/patches/0006-docs-refresh-OpenAPI-for-admin-updates.patch`
- PR body:
  `.fop/pr-bodies/fix-release-preflight-e2e-base-url.md`
- Worktree is clean except this untracked `docs/plans/` handoff directory.

The PHP change does eight things:

1. Sets release/preflight e2e `E2E_BASE_URL` to `http://127.0.0.1:8082` so the
   PHP app is checked against the PHP dev server instead of the Rust service.
2. Makes offline Composer advisory metadata failures advisory in PR/non-strict
   mode while preserving hard failure for real advisories.
3. Registers the Admin Updates module under `/api/v1/admin/updates/*`.
   Before `5902bc6`, `routes/modules/updates.php` existed but was never loaded,
   and the frontend was backed only by a stub `check` route.
4. Makes update apply use configurable `PARKHUB_UPDATE_REMOTE` /
   `PARKHUB_UPDATE_BRANCH` instead of hard-coded `origin main`. This matters on
   this workstation because `origin` is stale Gitea and `github` is canonical.
5. Updates PHP agent/developer docs so future agents do not pull, rebase, or
   push ParkHub work against stale Gitea remotes as the canonical source.
6. Makes PHPStan local CI robust in socket-restricted shells by probing whether
   PHP can bind a loopback control socket and using serial `--debug` mode only
   when that capability is unavailable.
7. Updates module-count tests from 68 to 69 because the `updates` module is now
   part of the shipped module registry.
8. Refreshes `docs/openapi/php.json` so the committed contract includes the
   Admin Updates routes.

Verification already passed:

- `bash -n scripts/ci/local-security-audit.sh`
- `scripts/ci/local-security-audit.sh --profile pr`
- `FOP_LOCAL_CI_DIRECT=1 npm_config_ignore_scripts=true .github/scripts/fop-local-ci.sh --profile pr`
- `bash -n scripts/ci/phpstan-analyse.sh`
- `scripts/ci/phpstan-analyse.sh --memory-limit=512M --no-progress`
- `php -l config/modules.php`
- `php -l routes/api_v1.php`
- `php -l routes/modules/updates.php`
- `php -l tests/Feature/AdminUpdatesRouteTest.php`
- `DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan test --filter=AdminUpdatesRouteTest`
- `DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan test --filter=ModuleSystem`
- `DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan route:list --path=api/v1/admin/updates`
- `scripts/check-openapi-drift.sh`
- `node --check /var/home/florian/dev/parkhub-local-sites/parkhub-local-sites.mjs`
- `git diff --check`
- stale canonical-Gitea phrase scan over `AGENTS.md` and `DEVELOPMENT.md`
  returned no matches.

Latest full PR local CI report:

- `.fop/reports/local-ci-pr-a49f7288405f2e4e6baadd7ef8baa345498702bb.json`

Known advisory noise during the sandbox run:

- Composer/npm/OSV metadata fetches hit DNS/network failures and stayed advisory.
- Astro Google Fonts fetch hit DNS/network failure and completed with warning.
- Existing frontend typecheck and typos advisory debt remains non-blocking per
  script.

## Historical blockers (superseded)

The blockers below are retained only to explain the pre-recovery state. They
are superseded by the live continuation update and completion-audit sections
above.

1. Push of `parkhub-php` branch is blocked by workstation/sandbox egress:
   `github.com` DNS cannot resolve inside the sandbox. The normal SSH config
   path also hits a host SSH config permissions issue, so use
   `GIT_SSH_COMMAND='ssh -F /dev/null'` for the eventual push.
   Latest escalated push request at `2026-05-04 18:44 CEST` was rejected by the
   Codex usage-limit guard with retry-after `8:40 PM`; do not workaround it.
2. The local `.test` router at `/var/home/florian/dev/parkhub-local-sites`
   is not verifiable from sandbox-local `127.0.0.1` because Codex runs in a
   separate network namespace. Host-network/user systemd/browser verification
   is still needed.

## ParkHub GitOps chain

Flux worktree:

- `/var/home/florian/dev/flux-infra.parkhub-gitops`
- Branch: `t-2705-parkhub-gitops`
- Commit: `5a8babae fix(ci): restore ParkHub GitOps build cronjobs`

What changed:

- `infrastructure/ci/parkhub-build-cronjob.yaml` now clones canonical
  `https://github.com/nash87/parkhub-rust.git` instead of stale
  `192.168.178.212/florian/parkhub-docker`.
- `infrastructure/ci/parkhub-php-build-cronjob.yaml` is now a real
  `parkhub-php-build` CronJob cloning
  `https://github.com/nash87/parkhub-php.git`. Before this patch, that file
  contained a NanoClaw build despite being included from `kustomization.yaml`
  as the ParkHub PHP build job.

Verification:

- `rg -n "192\\.168\\.178\\.212|parkhub-docker|nanoclaw|NanoClaw|NANOCLAW" infrastructure/ci/parkhub-build-cronjob.yaml infrastructure/ci/parkhub-php-build-cronjob.yaml -S` returned no matches.
- `kubectl create --dry-run=client --validate=false -o name -f infrastructure/ci/parkhub-build-cronjob.yaml -f infrastructure/ci/parkhub-php-build-cronjob.yaml` returned both CronJobs.
- `git diff --check`
- Flux pre-commit hooks passed on commit.

## Local `.test` router follow-up

The router launcher had a concrete observability mismatch:

- `parkhub-local-sites.mjs` and `README.md` use
  `/var/tmp/florian-offload/tmp/parkhub-local-sites`
- `scripts/start.sh` and `scripts/stop.sh` still used `/tmp/parkhub-local-sites`
  for pid/router logs

This was patched locally in:

- `/var/home/florian/dev/parkhub-local-sites/scripts/start.sh`
- `/var/home/florian/dev/parkhub-local-sites/scripts/stop.sh`
- `/var/home/florian/dev/parkhub-local-sites/parkhub-local-sites.mjs`

Verification:

- `bash -n scripts/start.sh`
- `bash -n scripts/stop.sh`
- `node --check parkhub-local-sites.mjs`

The launcher now injects `PARKHUB_UPDATE_REMOTE=github` and
`PARKHUB_UPDATE_BRANCH=main` for both local Rust and PHP children instead of the
stale Gitea endpoint `192.168.178.212`.

The directory `/var/home/florian/dev/parkhub-local-sites/` is currently
untracked under the broad `/var/home/florian/dev` git worktree, so this change
is a local handoff patch rather than a committed repo diff.

Recoverable patch copy:

- `.fop/patches/0002-parkhub-local-sites-runtime-dir.patch`

## Resume commands

Post-`2026-05-05 01:41 CEST` external-action sequence:

```bash
date '+%Y-%m-%d %H:%M:%S %Z'
fop queue status
fop guard preflight
```

1. Verify and merge PHP Dependabot PR #445 if GitHub is clean:

```bash
gh pr view 445 --repo nash87/parkhub-php \
  --json number,title,headRefOid,mergeStateStatus,statusCheckRollup,url

gh api repos/nash87/parkhub-php/commits/679ea5b91d8da219e5e00d175ba0ebd2f3351cfe/status \
  --jq '{state:.state, contexts:[.statuses[] | {context,state,description,updated_at}]}'

# Only after mergeStateStatus is clean and fop/local-ci/pr is success:
gh pr merge 445 --repo nash87/parkhub-php --squash --auto \
  --match-head-commit 679ea5b91d8da219e5e00d175ba0ebd2f3351cfe
```

2. Fetch exact rebased heads for PHP Dependabot PRs #441-#444:

```bash
cd /var/home/florian/dev/parkhub-php.dependabot-ci
git fetch github \
  dependabot/npm_and_yarn/npm-tooling-deps-22a1f04088 \
  dependabot/npm_and_yarn/globals-17.6.0 \
  dependabot/npm_and_yarn/parkhub-web/npm-deps-9000fc1015 \
  dependabot/npm_and_yarn/parkhub-web/vite-8.0.10

git cat-file -e 02773f832105a6e586841005677822aa1d440687^{commit}
git cat-file -e 23b58ad998f59f354303f93aac6b2e294223d467^{commit}
git cat-file -e 642ead927f7293c36d2b4fb282bf3056be0d63b4^{commit}
git cat-file -e 3cc9482df36cb138adbcd009d602aec7184fc204^{commit}
```

3. Run/post the PHP local PR gate serially for #441-#444:

```bash
cd /var/home/florian/dev/parkhub-php.dependabot-ci

git checkout --detach 02773f832105a6e586841005677822aa1d440687
FOP_LOCAL_CI_STATUS_REPO=nash87/parkhub-php .github/scripts/fop-local-ci.sh --profile pr --post-status

git checkout --detach 23b58ad998f59f354303f93aac6b2e294223d467
FOP_LOCAL_CI_STATUS_REPO=nash87/parkhub-php .github/scripts/fop-local-ci.sh --profile pr --post-status

git checkout --detach 642ead927f7293c36d2b4fb282bf3056be0d63b4
FOP_LOCAL_CI_STATUS_REPO=nash87/parkhub-php .github/scripts/fop-local-ci.sh --profile pr --post-status

git checkout --detach 3cc9482df36cb138adbcd009d602aec7184fc204
FOP_LOCAL_CI_STATUS_REPO=nash87/parkhub-php .github/scripts/fop-local-ci.sh --profile pr --post-status
```

After each gate, re-query that PR and merge only with the matching head SHA once
GitHub reports clean branch protection:

```bash
gh pr list --repo nash87/parkhub-php --state open \
  --json number,title,headRefOid,mergeStateStatus,statusCheckRollup
```

4. Prove and open the PHP image-security PR:

```bash
cd /var/home/florian/dev/parkhub-php.trivy-runtime-prune
git status --short --branch
git log -1 --oneline

fop build --backend local --resource-profile batch-medium . --preset custom -- \
  podman build --pull=never -t localhost/parkhub-php:trivy-runtime-prune .

trivy image --quiet --severity CRITICAL,HIGH localhost/parkhub-php:trivy-runtime-prune

git push -u github fix/trivy-runtime-build-deps
gh pr create --repo nash87/parkhub-php --base main \
  --head fix/trivy-runtime-build-deps \
  --title "fix: purge PHP image build dependencies" \
  --body-file .fop/pr-bodies/fix-trivy-runtime-build-deps.md
```

5. Push/open the docs handoff PR:

```bash
cd /var/home/florian/dev/parkhub-php.t-2705-handoff-docs
git status --short --branch
git log -1 --oneline
git push -u github docs/t-2705-handoff-status
gh pr create --repo nash87/parkhub-php --base main \
  --head docs/t-2705-handoff-status \
  --title "docs: update T-2705 ParkHub handoff status" \
  --body-file .fop/pr-bodies/docs-t-2705-handoff-status.md
```

6. Keep Flux PR #178 review-only:

- Verify with `tea pulls --login gitea-test --repo florian/flux-infra --output tsv`.
- Do not auto-merge or reconcile production from this lane.

## Acceptance

- GitHub confirms Rust has no unexpected open PRs and Rust security alerts are
  still clean.
- PHP #445 is merged or has a documented non-source blocker after its successful
  local PR gate.
- PHP #441-#444 exact heads are fetched, locally gated, and merged only after
  GitHub branch protection is clean.
- PHP image-security branch has host Podman build proof, Trivy image proof, and
  a GitHub PR.
- Docs handoff branch has a GitHub PR.
- Flux PR #178 remains review-only unless an operator explicitly approves the
  GitOps rollout.
- Local `.test` router status is re-verified from the host network before the
  final completion audit.
- fop checkpoints mention exact PR URLs, merge commits, reports, and any
  residual blockers.
