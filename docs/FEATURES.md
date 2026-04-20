# ParkHub PHP Feature Notes

> **Self-hosted parking management for enterprises, universities, and residential complexes.**
> Laravel 13 · Apache / shared hosting / Render · Zero cloud · Zero tracking · 100% GDPR compliant.

[Live Demo](https://parkhub-php-demo.onrender.com) · [API Docs](API.md) · [Installation](INSTALLATION.md) · [GDPR Guide](GDPR.md)

---

This document is a **pointer doc**, not a replica.

The parkhub-rust edition carries the full product feature showcase in [its own `docs/FEATURES.md`](https://github.com/nash87/parkhub-rust/blob/main/docs/FEATURES.md). The PHP edition ships the same product — same user journeys, same admin surfaces, same API contract, same ten locales — so pointing at the Rust doc keeps a single source of truth instead of two drifting narratives.

This file exists to record:

1. The **Modular UX Platform** (shipped v4.13.0 — v1 + v2 + v3) which is new enough that the pointer doc alone isn't enough to orient a PHP reader.
2. A short list of **PHP-specific implementation differences** — anywhere the lang-level choice leaks into something a developer or operator actually sees.

For everything else — multi-tenant isolation, analytics dashboard, credits, Stripe, EV charging, webhooks, SAML/OAuth, GDPR export/erasure, PWA, mobile — read the Rust `FEATURES.md`. The behaviour described there holds here.

---

## Modular UX Platform

*Added in v4.13.0 (v1 + v2 + v3).*

Every compiled-in feature is a first-class **module**. The registry, admin dashboard, and command palette turn ParkHub from a fixed installation into a configurable product surface — operators can see what's installed, flip safe modules on and off, and tune per-module settings without a redeploy.

### Module Registry

Seventy modules across eleven categories (Core, Booking, Vehicle, Admin, Payment, Integration, Analytics, Compliance, Notification, Enterprise, Experimental). Each registry row declares its slug, category, description, compile-time availability, runtime-toggleable bit, config keys, UI route, dependency chain, and optional JSON Schema.

Introspection endpoints live under `/api/v1/modules*` — see [API.md § Modules](API.md#modules) for the full contract. The legacy flat `{modules: {name: bool}}` map is preserved in the response envelope so existing callers keep working, but public slugs are canonicalized (`realtime`, `push`) rather than leaking legacy transport/config names (`broadcasting`, `websocket`, `push_notifications`, `web_push`).

### Admin Dashboard — `/admin/modules`

Admin-only UI grouped by category with search + tag filter. Every card shows:

- Status pill: green (runtime on) · amber (runtime off) · gray (compile-time off)
- Version, dependency chain, config-keys count
- Export-Config JSON download for ops hand-off
- Per-card **runtime toggle** (on the safe-to-flip modules)
- Per-card **Configure** button for the five modules that ship a JSON Schema

Optimistic updates with toast rollback on failure; fully a11y-compliant.

The frontend is byte-identical with the parkhub-rust copy (verified via `diff -q` across `parkhub-web/` during v1/v2/v3 releases).

### Runtime Toggle

Thirteen safe modules can be flipped at runtime without redeploying:

`widgets` · `themes` · `favorites` · `lobby-display` · `accessible` · `calendar-drag` · `ev-charging` · `maintenance` · `geofence` · `map` · `graphql` · `api-docs` · `setup-wizard`

Security-sensitive modules (`auth`, `payments`, `rbac`, `webhooks`, `audit-export`, `multi-tenant`, `notifications`) keep `runtime_toggleable = false` and are invariant at runtime.

### JSON Schema Config Editor

Five modules ship a declared `config_schema` and surface a per-module config modal: `themes`, `announcements`, `notifications`, `email-templates`, `widgets`.

The config editor renders a hand-rolled form covering six field shapes (string, enum, email, time, integer with min/max, boolean) — zero external runtime dependencies. Writes are validated server-side against the schema via `opis/json-schema` 2.6.0; validation failures return `422 CONFIG_VALIDATION_FAILED` with a structured `details` array.

### Command Palette (Cmd+K / Ctrl+K / `/`)

Framework-agnostic `commandRegistry` with fuzzy search and predicate-gated visibility. Any view can `register()` commands and get automatic cleanup via `unregister()`. The provider auto-seeds default commands from `/api/v1/modules` — every active module with a `ui_route` appears as a "Go to …" entry.

All ten locales (en, de, es, fr, it, ja, pl, pt, tr, zh) carry the `command.*` and `admin.modules.*` key sets.

### Module Gate Middleware

`App\Http\Middleware\ModuleGate` returns `404 MODULE_DISABLED` for any route whose backing module is runtime-disabled — indistinguishable from a feature that was never installed. Applied to five representative routes in v1; broader coverage follows the route table.

### Pluginable by Design

Adding a new module is one declarative row in the module registry: the registry, dashboard, palette, and gate pick it up automatically. The UI does not special-case any module by name.

### Audit Trail

Every runtime toggle and every config write emits an `AuditLog` row with `action = 'module_config_updated'`, actor, module slug, before/after value, timestamp, and originating IP. Filter + export from the compliance dashboard ([GDPR.md](GDPR.md)).

---

## PHP-Specific Differences

The rows below are the places where the PHP edition diverges from the Rust edition in a user-visible or operator-visible way. If a row isn't listed, assume parity.

| Area | PHP edition | Rust edition |
|------|-------------|--------------|
| Module count | **70 modules** (registered in the Laravel registry) | 72 modules |
| Toggleable modules | 13 safe rows | 15 safe rows |
| Runtime | Laravel 13 on PHP 8.4 | Single Axum 0.8 binary |
| JSON Schema validator | `opis/json-schema` 2.6.0 | `jsonschema` 0.35 (Rust crate) |
| Authz layering | Laravel **Policies** (`Gate::policy`) + `admin` middleware — see `app/Policies/*` | axum extractors + `role=admin` guard |
| Validation error envelope | Shared `{success, data, error, meta}` envelope; some legacy 422s still use Laravel-native shape outside the module-config path | `{success, data, error, meta}` envelope |
| Audit store | `AuditLog` Eloquent model, retained via `PurgeAuditLogsJob` (90-day default) | `audit_log` redb table |
| Deployment targets | Apache / shared hosting / VPS / Docker / Render / K8s (Helm chart) | Single Rust binary, Docker, Helm, Raspberry Pi |
| Token format | Laravel Sanctum opaque Bearer tokens (7-day TTL) | JWT with family-rotation + optional Redis revocation (24-h TTL) |
| Rate limiting | Tenant-namespaced cache keys via Laravel RateLimiter | `tower_governor` IP bucket + per-identity `DashMap` buckets |
| Mutation testing | `infection/infection` 0.32.6 (nightly) | `cargo-mutants` 25.3.1 (nightly) |
| Contract fuzzing | `schemathesis` 4.15.2 against `docs/openapi/php.json` | manual property tests + OpenAPI drift gate |

The two modules that are Rust-only in v4.13.0 are all runtime-quirk rows that don't translate 1:1 to Laravel (e.g. specific Rust-only feature flags in the Cargo workspace). They ship no user-visible feature on the Rust side either — their state is `runtime_toggleable = false, runtime_enabled = false` by default — so the gap is an implementation artefact, not a product gap.

---

## Deployment

Installation, hosting options, and upgrade paths are covered in:

- [INSTALLATION.md](INSTALLATION.md) — bare-metal + Docker + Render
- [SHARED-HOSTING.md](SHARED-HOSTING.md) — managed hosting deploy
- [VPS.md](VPS.md) — VPS setup
- [DOCKER.md](DOCKER.md) — `docker compose up`
- [PAAS.md](PAAS.md) — Render + friends
- [../helm/README.md](../helm/README.md) — Kubernetes
- [CONFIGURATION.md](CONFIGURATION.md) — env + config reference

---

*For the full cross-edition feature narrative, read the parkhub-rust counterpart.*
*For the API contract, see [API.md](API.md).*
*For GDPR details, see [GDPR.md](GDPR.md).*
