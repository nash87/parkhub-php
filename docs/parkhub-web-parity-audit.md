# parkhub-web parity audit (parkhub-php ↔ parkhub-rust)

**Date:** 2026-05-01
**Audited by:** autonomous parity-governance pass (cc-9f03eca3)
**Companion to:** [parity-governance.md](parity-governance.md), [openapi-parity.md](openapi-parity.md)

This document inventories every divergence between the shared `parkhub-web` SPA in `parkhub-rust` and `parkhub-php`. The two repos host the same Astro + React frontend but have drifted in features, configs, and assets. Goal: get to **byte-equivalence** for everything that isn't explicitly runtime-sensitive.

## Versioning

| Surface | parkhub-rust | parkhub-php | Drift |
|---|---|---|---|
| Last `v*` tag | v5.0.8 | v5.0.3 | rust ahead by 5 patches |
| `parkhub-web/package.json` `version` | `5.0.8` | `5.0.3` | matches tag |
| Root `package.json` `version` | `5.0.8` | n/a (php uses VERSION file) | runtime-sensitive |

The 5-patch gap is rust-side: PRs #441-#475 covered Tauri desktop installers, multi-arch container, cosign 3.x compat, fop-local-ci fallback, yaml CVE override, zizmor cleanup. **Of those, only PR #444 (service-worker register + apple-touch PNGs) and PR #443 (cosign sign-blob + SPDX SBOMs) are parkhub-web-impacting**; the rest are runtime-side.

## Divergence inventory (parkhub-web subtree)

Run this to reproduce: `diff -rq /var/home/florian/dev/parkhub-rust/parkhub-web /var/home/florian/dev/parkhub-php/parkhub-web | grep -vE 'node_modules|dist|\.astro|package-lock'`

### Files only on parkhub-rust (porting candidates)

| File | Category | Action |
|---|---|---|
| `e2e/design-accessibility.spec.ts` | e2e tests for v5 design system | Port (php should test the same v5 surfaces) |
| `e2e/design-assistant.spec.ts` | e2e | Port |
| `e2e/design-settings.spec.ts` | e2e | Port |
| `e2e/design-shortcuts-help.spec.ts` | e2e | Port |
| `e2e/v5-a11y.spec.ts` | e2e a11y | Port |
| `e2e/v5-happy-paths.spec.ts` | e2e happy paths | Port |
| `e2e/v5-helpers.ts` | e2e shared helpers | Port |
| `e2e/v5-visual.spec.ts` | e2e visual regression | Port |
| `e2e/v5-visual.spec.ts-snapshots/` | visual regression baselines | Port (will need regeneration on php-side baselines) |
| `e2e/fixtures/` | shared playwright fixtures | Port |
| `e2e/README.md` | docs | Port |
| `public/manifest.webmanifest` | PWA install manifest | Port (parkhub-php has manifest.json instead) |
| `public/icons/icon-192.png` | PWA install icon | **Done in PR #421** |
| `public/icons/icon-512.png` | PWA install icon | **Done in PR #421** |
| `scripts/spa-preview.mjs` | local SPA preview helper | Port |

### Files differ (need to converge)

| File | Notes | Action |
|---|---|---|
| `astro.config.mjs` | output config, base path, integrations may differ | Diff + reconcile |
| `biome.json` | linter rule set may have drifted | Sync |
| `package.json` | version field obviously, also dep versions | Sync deps; version handled by release-tag |
| `playwright.config.ts` | test parallelism, browsers, baseURL | Diff + reconcile |
| `public/manifest.json` (php) vs `public/manifest.webmanifest` (rust) | Different file names AND shapes — see below | Pick one canonical name |
| `README.md` (parkhub-web) | docs drift | Sync |
| `scripts/capture-screenshots.mjs` | tooling drift | Diff + reconcile |
| `src/api/client.test.ts` | API client tests | Diff (likely API surface drift between runtimes — could be legitimate) |
| `.gitignore` | tooling artifacts | Sync (low impact) |

### Manifest.json broken-references (parkhub-php side)

`parkhub-web/public/manifest.json` references PWA assets that **do not exist on disk**:

- `/icons/screenshot-wide.png` (manifest line 29) — missing
- `/icons/screenshot-mobile.png` (manifest line 36) — missing
- 3× `shortcut` icon refs to `/favicon.svg` — exist (no fix needed)

Side note: parkhub-rust uses `manifest.webmanifest` (proper W3C MIME type `application/manifest+json`) while parkhub-php uses `manifest.json` (works but technically less correct). Convergence target: both ship `manifest.webmanifest` referenced by the HTML `<link rel="manifest">`. Rust already does this; php still references `manifest.json` (need to grep `<link rel="manifest"` to confirm).

## Theme color drift (intentional? document)

Both manifests define a `theme_color`:

- parkhub-rust: `#dc2626` (red-600 — matches v5 design system)
- parkhub-php: `#0d9488` (teal-600 — older v4 brand)

This is a **real customer-visible difference** in the OS-level chrome color of the installed PWA on Android. Per `parity-governance.md`, this should be either:

1. Aligned (pick one), or
2. Explicitly documented as runtime-sensitive in this audit + the openapi-parity.md companion.

Recommended action: align to `#dc2626` (rust = v5 source of truth per governance), document the change in CHANGELOG.

Background color also drifts: rust `#0f0f0f` vs php `#0a0a0a`. Same recommendation.

## Closure plan

| Slice | Scope | Estimated PRs | Risk |
|---|---|---|---|
| 1 (DONE) | PR #421 — port icon-192 + icon-512 PNGs + manifest entries | 1 | low |
| 2 | Fix broken `screenshots` entries in manifest.json (remove or generate) | 1 | low |
| 3 | Align theme_color + background_color on php to match rust v5 | 1 | medium (visible UI change) |
| 4 | Port `e2e/v5-*` and `e2e/design-*` specs from rust to php | 1-2 | medium (php-specific baselines need regen) |
| 5 | Sync `astro.config.mjs`, `biome.json`, `playwright.config.ts` | 1 | low |
| 6 | Sync `parkhub-web/README.md` + `scripts/capture-screenshots.mjs` + `scripts/spa-preview.mjs` | 1 | low |
| 7 | Audit `src/api/client.test.ts` for legitimate vs. drift differences | 1 (audit only, may yield no fix) | low |
| 8 | Coordinated release: cut both at v5.0.9 (rust patch from v5.0.8 + php cluster catch-up) | tag-only | low |

After slice 8: both runtimes ship the same v5.0.9, and `manifest.json` filename + theme_color are aligned. The `parity-governance.md` rule "no v* release tag should ship unless the tag version matches the runtime's public version surfaces" is finally back in compliance.

## Coordination notes

- **Codex (parallel session)**: actively shipping CI hardening on parkhub-rust + parkhub-php (PRs #481, #420 as of 2026-05-01 ~03:00 UTC). Codex's scope is `.github/workflows/`, `Makefile`, `scripts/ci/`, `scripts/generate-vex.sh`. **Zero file overlap with this audit's slices.**
- **Other tabs**: T-2374 (forge-operator desktop CI runner fleet) and T-2358 (fop-symphony scheduler) are in flight elsewhere.

## How to update this doc

When closing a slice, update the row in the **Closure plan** table to mark `(DONE)` with the PR number. Re-run the divergence inventory section (`diff -rq` command at the top) and prune entries that are now byte-equivalent.

When parkhub-php catches up to parkhub-rust's tag, archive this whole document by moving it to `docs/parity-audit-2026-05.md` and closing the loop in `parity-governance.md`.
