# AGENTS.md — ParkHub PHP

## Overview
Laravel-based ParkHub deployment with PHP backend, React/Vite frontend assets, Playwright tests, container builds, and GitHub Actions automation. Treat this repository as production code with security, privacy, CI safety, and operational correctness as primary concerns.

## Stack
- PHP 8.4
- Laravel
- React + Vite frontend in `parkhub-web/`
- Additional frontend assets in `resources/js/`
- Composer, npm, Playwright, Docker

## Repo Map
- `app/`, `routes/`, `database/` backend code, routing, and schema changes
- `resources/js/` app frontend assets
- `parkhub-web/` standalone frontend bundle
- `tests/` PHP and browser test coverage
- `.github/` repository automation, PR review, and security scanning

## Build And Test
```sh
composer install
php artisan test
npm ci
npm run build
cd parkhub-web && npm ci && npm run build
```

## Pre-Push Gate (mandatory)
Every push must go through the local CI mirror first — it runs the same jobs as `.github/workflows/*.yml`:
```sh
composer ci         # mandatory pre-push gate (Pint + PHPStan level 4 + PHPUnit + frontend build)
# or equivalently:
make ci             # broader local gate: lint + static-analysis + test + frontend + drift
make act            # optional: run the actual workflow files locally via nektos/act (.actrc preconfigured)
```
Install pre-commit hooks once per clone: `pre-commit install` (config in `.pre-commit-config.yaml`). See [DEVELOPMENT.md](DEVELOPMENT.md) for the full loop. Mutation testing (Infection) runs weekly via `.github/workflows/infection.yml` (`infection.json5` gates survivors). OpenAPI parity with the Rust edition is tracked via [docs/openapi-parity.md](docs/openapi-parity.md) + `scripts/dump-openapi.sh` / `scripts/diff-openapi.sh`; current CI still hard-gates only self-snapshot drift.

## Dual-Remote Convention
Two remotes are always configured on this repo:
- `origin` — Gitea at `git@192.168.178.220:florian/parkhub-php.git` (primary, source of truth)
- `github` — `https://github.com/nash87/parkhub-php.git` (public mirror)

Always `git push origin <branch>` first, then `git push github <branch>`. Never push only to GitHub. CI runs on GitHub; operator review happens on Gitea.

## Code Conventions
- **`declare(strict_types=1);` is mandatory** on every PHP file in `app/` — 171/171 app files are strict today. Any new `.php` file under `app/` MUST start with `<?php` then a blank line then `declare(strict_types=1);` before the namespace.
- **PHPStan level 4** is the current gate (`phpstan.neon`). Do not regress level; raising is welcome.
- **Pint** runs clean; no style regressions allowed through `composer ci`.
- **Tenant-aware queries** (multi-tenancy scaffold, flag `MODULE_MULTI_TENANT` still off by default):
  - For **Eloquent models** — `Booking`, `ParkingLot`, `User`, and other tenant-scoped models apply the `App\Models\Concerns\BelongsToTenant` trait, which installs the `BelongsToTenantScope` global scope. Write queries as usual (`Booking::query()->...`) and isolation happens automatically when the flag is on.
  - For **raw `DB::table(...)` callsites** (LobbyDisplayController, MetricsController active-session counts, guest-bookings cleanup, etc.) the global scope cannot fire. Use `App\Support\TenantScope::applyTo($query, 'table_alias')` to conditionally add `WHERE tenant_id = ?`. When the flag is off, `applyTo` is a no-op.
  - Never hand-roll `->where('tenant_id', ...)` — always go through `TenantScope::applyTo` so the flag gate stays consistent.

## Audit Priorities
- Review authn/authz, session safety, CSRF, validation, file handling, and migration safety first.
- Treat legal/GDPR flows, audit logging, account recovery, and admin features as high-risk paths.
- Check CI/CD, GHCR publishing, and Render deployment workflows for token scope and unsafe triggers.
- Require regression tests for high-risk backend and frontend changes.

## Guardrails
- Never commit secrets, `.env`, demo credentials beyond explicit demo-only configuration, or production API keys.
- Do not relax validation, authorization, rate limits, or audit logging without explicit justification.
- Do not broaden workflow permissions when narrower permissions work.
- Prefer minimal safe fixes and document stronger long-term hardening separately.
- **SHA-pinned GitHub Actions** — every `uses:` in `.github/workflows/*.yml` must reference a full commit SHA with the v-tag as a trailing comment (e.g. `uses: actions/checkout@1d96c772d19495a3b5c517cd2bc0cb401ea0529f # v5.0.0`). 23+ actions are pinned this way today; never introduce a bare `@v5` / `@main` reference.
- Legal templates in `legal/` must be customized by the operator before production use (includes BFSG accessibility statement + EU AI Act Art. 50 transparency notice, both new in this sprint).
