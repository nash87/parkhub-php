# E7 Dependabot fop/local-ci/pr bridge — RESUME

## Status

Authored and staged. Commit `f1727364eab62c8da8ba471b981b66b4bbde4b7e` ready. Push blocked by 2 pre-existing parkhub-php gate issues, neither caused by this PR.

## What was done

New GHA + Gitea-Actions workflow that auto-posts `fop/local-ci/pr: success|failure` commit status on Dependabot-authored PRs. Detects `github.event.pull_request.user.login == 'dependabot[bot]'`, runs the headless equivalent of `make ci` (composer-audit hard, npm-audit advisory, gitleaks secret scan hard, osv-scanner advisory, typos advisory), posts via `gh api POST /repos/.../statuses/{sha}`.

Closes the architectural gap that's keeping these 8 PRs in MERGEABLE+BLOCKED state:
- parkhub-php: #493, #496, #498
- parkhub-rust: #638, #639, #640, #641, #642 (parallel PR needed for rust later)

Workflow uses SHA-pinned actions reused from `security.yml` + `ci.yml`. Both `.github/workflows/dependabot-local-ci-bridge.yml` (GHA) and `.gitea/workflows/dependabot-local-ci-bridge.yaml` (Gitea mirror) committed.

## Push blockers (pre-existing, NOT introduced by this PR)

### Blocker 1: `image-scan` lefthook gate — devalue 5.6.4 HIGH CVE

```
image-scan: Container image scan surfaced CRITICAL/HIGH vulnerabilities.
─────────────────────────────────────────────────────────────────────────────
Total: 1 (HIGH: 1, CRITICAL: 0)
devalue  CVE-2026-42570  HIGH  fixed  5.6.4  5.8.1
Svelte devalue: DoS via sparse array deserialization
```

- `parkhub-web/package.json` has `"devalue": "^5.6.4"` (caret range).
- A fresh `npm install` would resolve to `5.8.1`+ on most networks, but the LOCAL image cache holds a build pinned at 5.6.4.
- **Fix**: either (a) bump package.json pin to `"devalue": "^5.8.1"` so any rebuild forces ≥5.8.1, or (b) operator runs `docker build` + `scripts/local-image-scan.sh` to refresh the local image cache.

### Blocker 2: `local-ci` gate — `make ci` itself failing

`make ci` exits non-zero because the image-scan step (blocker 1) inside it fails. The lefthook gate sees the resulting `.fop/reports/local-ci-pr-f172736….json` state as `failure` and refuses the push.

This is downstream of blocker 1. Fixing devalue unblocks both.

## Push commands (run after blockers cleared)

```bash
cd /home/florian/dev/parkhub-php.t-dependabot-local-ci-bridge
# Refresh image cache OR bump devalue first, then:
FOP_LOCAL_CI_DIRECT=1 make ci   # regenerate local-ci report at state=success
git push github HEAD
flatpak-spawn --host gh -R nash87/parkhub-php pr create --base main \
  --head t-dependabot-local-ci-bridge \
  --title "feat(ci): bridge fop/local-ci/pr status for Dependabot PRs (unblock 8 stuck PRs)" \
  --body-file PR_BODY.md
```

## Why this is NOT a `--no-verify` bypass

Per CLAUDE.md L237 + the 2026-05-19 E4 incident memory, bypassing lefthook pre-push is forbidden. The right path when blocked by unrelated pre-existing failures is to STOP and let the operator address the upstream issue — exactly what this RESUME.md documents.

Sibling fix-forward branches checked (`git branch -r --no-merged origin/main | head`): none address the devalue image-scan issue.

## Cross-references

- Memory `feedback_subagent_no_verify_misuse_e4_schemathesis_2026_05_19.md` — the discipline this resume honors.
- Memory `feedback_parkhub_rust_prepush_layered_gates_2026_05_19.md` — analogous parkhub-rust pattern.
- Task #11 / #20 in the fop task board.
