# ParkHub PHP Local-First CI/CD Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make local `fop` verification the primary ParkHub PHP PR gate, keep Gitea as the fuller internal runner surface, and keep GitHub as a thin external verifier.

**Architecture:** `.github/scripts/fop-local-ci.sh` is the canonical local PR/CD runner and the only path that posts `fop/local-ci/pr`. `.github/workflows/ci.yml` gates same-repo PRs on that status and skips heavy duplicates unless explicitly requested. `.gitea/workflows/ci.yaml` remains fuller internal CI so runner, package, Docker, e2e, and integration issues are caught before GitHub.

**Tech Stack:** Laravel/PHP 8.4, Astro/React/Vitest, GitHub Actions, Gitea Actions, `fop build`, `gh api`, Helm, Docker Buildx.

---

## Chunk 1: Restore Local-First Gate

### Task 1: Restore the canonical fop local CI script

**Files:**
- Restore: `.github/scripts/fop-local-ci.sh`
- Modify: `Makefile`
- Modify: `DEVELOPMENT.md`
- Modify: `AGENTS.md`

- [ ] **Step 1: Restore script from the latest protected mainline**

Run: `git restore --source=github/main -- .github/scripts/fop-local-ci.sh`

Expected: `.github/scripts/fop-local-ci.sh` exists, is executable, supports `--profile pr|full|cd`, and can post `fop/local-ci/pr`.

- [ ] **Step 2: Make `make ci` delegate to fop**

Update `Makefile` so:

```make
ci:
	.github/scripts/fop-local-ci.sh --profile pr

ci-post:
	.github/scripts/fop-local-ci.sh --profile pr --post-status

full:
	.github/scripts/fop-local-ci.sh --profile full

cd:
	.github/scripts/fop-local-ci.sh --profile cd
```

Expected: the default local gate cannot bypass fop queue/resource controls.

- [ ] **Step 3: Update operator docs**

Document that:

- `make ci` is local proof.
- `make ci-post` is the GitHub PR attestation path.
- `make cd` is the release-oriented local preflight.
- lower-level make targets are debugging helpers, not merge gates.

## Chunk 2: Fix GitHub/Gitea Roles

### Task 2: Make GitHub thin and Gitea full

**Files:**
- Modify: `.github/workflows/ci.yml`
- Modify: `.gitea/workflows/ci.yaml`

- [ ] **Step 1: Restore GitHub same-repo PR local-first logic**

Run: `git restore --source=github/main -- .github/workflows/ci.yml`

Expected:

- `local-ci-attestation` exists.
- same-repo PRs require successful `fop/local-ci/pr`.
- heavy jobs skip on same-repo PRs unless `github-ci-full` is set.
- fork PRs and `main` pushes still run full GitHub checks.

- [ ] **Step 2: Keep Gitea intentionally fuller**

Update `.gitea/workflows/ci.yaml` header so future agents do not blindly remirror the thin GitHub PR behavior over the internal runner.

Expected: Gitea remains the fuller CI/CD surface, GitHub remains thin, and the difference is documented.

## Chunk 3: Verification

### Task 3: Run cheap structural checks first

**Files:**
- Verify: `.github/workflows/ci.yml`
- Verify: `.gitea/workflows/ci.yaml`
- Verify: `.github/scripts/fop-local-ci.sh`

- [ ] **Step 1: Check syntax-relevant strings**

Run:

```bash
rg -n "local-ci-attestation|fop/local-ci|github-ci-full|LOCAL-FIRST|Required checks" .github/workflows/ci.yml
```

Expected: all local-first markers are present.

- [ ] **Step 2: Check script dry-run**

Run:

```bash
.github/scripts/fop-local-ci.sh --profile pr --dry-run
```

Expected: all PR steps print without executing heavy work.

- [ ] **Step 3: Check YAML if tools are available**

Run:

```bash
command -v actionlint >/dev/null && actionlint .github/workflows/ci.yml || true
```

Expected: no syntax errors if actionlint is installed.

### Task 4: Full verification when host pressure allows

- [ ] **Step 1: Run preflight**

Run:

```bash
free -m | awk 'NR==2{print $7" MiB available"}'
uptime | awk -F'load average: ' '{print $2}'
fop queue status
```

Expected: at least 4096 MiB available, load below hard gate, no conflicting active heavy jobs.

- [ ] **Step 2: Post GitHub attestation**

Run:

```bash
make ci-post
```

Expected: local CI passes and GitHub shows `fop/local-ci/pr` success for the branch head SHA.

- [ ] **Step 3: Push both remotes**

Run:

```bash
git push origin HEAD:ci/sota-2026-pipeline-tsc-and-zizmor-fixes
git push github HEAD:ci/sota-2026-pipeline-tsc-and-zizmor-fixes
```

Expected: Gitea and GitHub branches match the local head.
