<p align="center">
  <img src="public/favicon.svg" alt="ParkHub PHP" width="96">
</p>

<h1 align="center">ParkHub PHP -- Self-Hosted Parking Management</h1>

<p align="center">
  <a href="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml"><img src="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="CHANGELOG.md"><img src="https://img.shields.io/badge/Release-v4.14.0-brightgreen.svg?style=flat-square" alt="v4.14.0"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square" alt="MIT License"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white" alt="PHP 8.4"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-13-FF2D20.svg?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://astro.build/"><img src="https://img.shields.io/badge/Astro-6-BC52EE.svg?style=flat-square&logo=astro&logoColor=white" alt="Astro 6"></a>
  <a href="https://react.dev/"><img src="https://img.shields.io/badge/React-19-61DAFB.svg?style=flat-square&logo=react&logoColor=black" alt="React 19"></a>
  <a href="https://tailwindcss.com/"><img src="https://img.shields.io/badge/Tailwind_CSS-4-06B6D4.svg?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"></a>
  <img src="https://img.shields.io/badge/Tests-1754%2B-success.svg?style=flat-square" alt="1754+ tests">
  <a href="docs/GDPR.md"><img src="https://img.shields.io/badge/DSGVO-konform-green.svg?style=flat-square" alt="GDPR Compliant"></a>
  <a href="docs/COMPLIANCE.md"><img src="https://img.shields.io/badge/Compliance-Audited-brightgreen.svg?style=flat-square" alt="Compliance Audited"></a>
  <a href="docker-compose.yml"><img src="https://img.shields.io/badge/Docker-ready-2496ED.svg?style=flat-square&logo=docker&logoColor=white" alt="Docker Ready"></a>
  <a href="helm/README.md"><img src="https://img.shields.io/badge/Helm-chart-0F1689.svg?style=flat-square&logo=helm&logoColor=white" alt="Helm Chart"></a>
</p>

<p align="center">
  <strong>Ihre Daten. Ihr Server. Ihre Kontrolle.</strong><br>
  The on-premise parking management runtime for the canonical ParkHub product -- optimized for shared hosting, VPS, Docker, and Kubernetes.<br>
  Built with Laravel 13, Astro 6, React 19, and Tailwind CSS 4. Zero cloud. Zero tracking. 100% GDPR compliant by design.
</p>

<p align="center">
  <a href="https://parkhub-php-demo.onrender.com"><strong>Try the Live Demo</strong></a> &nbsp;|&nbsp;
  <a href="docs/INSTALLATION.md">Installation</a> &nbsp;|&nbsp;
  <a href="helm/README.md">Helm Chart</a> &nbsp;|&nbsp;
  <a href="docs/API.md">API Docs</a> &nbsp;|&nbsp;
  <a href="docs/GDPR.md">GDPR Guide</a> &nbsp;|&nbsp;
  <a href="docs/COMPLIANCE.md">Compliance</a> &nbsp;|&nbsp;
  <a href="docs/SECURITY.md">Security</a> &nbsp;|&nbsp;
  <a href="CHANGELOG.md">Changelog</a>
</p>

---

## Design v5 Showcase

<table>
  <tr>
    <td><img src="docs/screenshots/v5/dashboard-marble-light.png" alt="Dashboard — Marble Light"></td>
    <td><img src="docs/screenshots/v5/dashboard-void.png" alt="Dashboard — Void"></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/v5/buchungen-marble-light.png" alt="Buchungen — Marble Light"></td>
    <td><img src="docs/screenshots/v5/buchen-void.png" alt="Buchen — Void"></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/v5/analytics-marble-light.png" alt="Analytics — Marble Light"></td>
    <td><img src="docs/screenshots/v5/buchen-marble-light.png" alt="Buchen — Marble Light"></td>
  </tr>
</table>

> Live demo: <a href="https://parkhub-php-demo.onrender.com">parkhub-php-demo.onrender.com</a> · drücke <kbd>⌘K</kbd> / <kbd>Ctrl</kbd>+<kbd>K</kbd> für die Command-Palette · <kbd>?</kbd> blendet das Help-Overlay ein.

---

## Design v5 Status (26 / 26 screens shipped)

| Surface | Status |
|---------|--------|
| **All navigation screens** | 26 / 26 ported to `parkhub-web/src/design-v5/` -- the `<PlaceholderV5>` fallback has been retired. |
| **Themes** | OKLCH tokens across `marble_light`, `marble_dark`, `void`; self-hosted Inter-Variable keeps the LCP budget green. |
| **Command Palette** | cmdk-powered, mounted globally, reachable from every route with `Cmd+K` / `Ctrl+K`. |
| **Onboarding** | 3-step `/tour` wizard (Privacy -> Toggles -> Trust) guides first-time users before the Laravel app shell mounts. |
| **Accessibility** | axe-core runs in CI on the v5 surfaces; keyboard-only nav verified for the full shell + Assistent panel. |
| **Mobile** | Playwright now ships a `mobile-chrome` (Pixel 5) project so v5 specs can opt into mobile viewports on CI. |

Live demo: <https://parkhub-php-demo.onrender.com>.

---

## What's New in v4.13.0

| Feature | Description |
|---------|-------------|
| **Modular UX Platform** | 70-module registry with admin dashboard at `/admin/modules`, runtime enable/disable via `PATCH /api/v1/admin/modules/{name}`, per-module JSON Schema config editor, and Command Palette (`Cmd+K` / `Ctrl+K` / `/`). See [docs/FEATURES.md § Modular UX Platform](docs/FEATURES.md#modular-ux-platform) |
| **Service layer extraction** | 12 focused services extracted over 6 passes, replacing the fat-controller pattern: `BookingCreationService`, `AuthenticationService`, `StripeWebhookService`, `VehicleService`, `AdminSettingsService`, `ComplianceService`, `ModuleConfigurationService`, `UserAccountService`, `AdminUserManagementService`, `WebhookDispatchService`, `AuditLogQueryService`, plus supporting result DTOs |
| **Controller split** | `BookingController` (1035 LOC) decomposed into 5 focused controllers: `BookingController`, `BookingCalendarController`, `BookingCheckInController`, `BookingInvoiceController`, `BookingSwapController` |
| **Laravel Policies** | 11 policies covering the primary domain models (`Booking`, `Vehicle`, `Absence`, `Announcement`, `AuditLog`, `Favorite`, `Notification`, `ParkingLot`, `Tenant`, `Webhook`, `Widget`) — up from 3 |
| **Security hardening** | SVG dropped from branding logo uploads; cross-tenant admin write guards on user updates |
| **Testing depth** | 1,320 feature tests + 434 unit tests + `infection-php` (nightly) + `schemathesis` (OpenAPI contract fuzzing, nightly) |
| **Perf** | Admin list endpoints eager-load relations to eliminate N+1 queries |

---

## Product Model

ParkHub is one product with multiple runtimes. This PHP edition shares the same core product model as the Rust edition, while keeping a PHP-first deployment story: Laravel, shared hosting compatibility, and conventional web stack flexibility.

Not every advanced module is equally hardened or equally enabled by default across runtimes. Treat the shared booking, admin, compliance, and theme surfaces as the core product line; treat advanced integrations, pass/check-in surfaces, and enterprise modules as optional and runtime-sensitive.

Cross-runtime ownership and release discipline live in [docs/parity-governance.md](docs/parity-governance.md) and [docs/release-checklist.md](docs/release-checklist.md).

---

## Why Self-Hosted?

Most parking management SaaS costs 200--2,000 EUR/month, stores your data on US cloud infrastructure, and requires a data processing agreement just to get started.

ParkHub is different. It runs on your server -- a shared hosting plan, a VPS, or your company network. Your data never leaves your premises, which means **no GDPR processor agreement needed**, no CLOUD Act exposure, and no monthly fees. The entire source code is MIT-licensed and auditable.

---

## Quick Start

### Docker (recommended)

```bash
git clone https://github.com/nash87/parkhub-php.git && cd parkhub-php
cp .env.example .env                  # set MYSQL_ROOT_PASSWORD + admin creds — see docs/INSTALLATION.md
docker compose up -d
# Open http://localhost:8080 -- Login: the PARKHUB_ADMIN_EMAIL / PARKHUB_ADMIN_PASSWORD you set in .env
```

The first build takes 2--5 minutes (installs Composer + Node dependencies, builds the React frontend). After that, starts are instant. Skip `.env` bootstrap and pass credentials inline instead:

```bash
MYSQL_ROOT_PASSWORD=strong-root-pw \
MYSQL_PASSWORD=strong-db-pw \
DB_PASSWORD=strong-db-pw \
PARKHUB_ADMIN_EMAIL=you@company.com \
PARKHUB_ADMIN_PASSWORD=secure \
  docker compose up -d
```

### Shared Hosting

ParkHub PHP runs on any 3 EUR/month shared hosting with PHP 8.4+ and MySQL. Upload via FTP, open `install.php` in your browser, done. See [Installation Guide](docs/INSTALLATION.md).

### Laravel Sail

```bash
git clone https://github.com/nash87/parkhub-php.git && cd parkhub-php
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
# Open http://localhost -- Email: admin@parkhub.test  Password: demo
```

### Development

```bash
composer setup                        # Install + configure + migrate + build
composer dev                          # Dev server + Vite + queue + logs
php artisan test                      # Run PHPUnit (1,320 feature + 434 unit)
```

**[Live Demo](https://parkhub-php-demo.onrender.com)** | Login: `admin@parkhub.test` / `demo` | (auto-resets every 6 hours)

---

## Features

### v4.1.0 Highlights

- **Booking Sharing & Guest Invites** -- Share bookings via secure links with optional expiry, invite guests by email
- **Scheduled Reports (Email Digest)** -- Automated daily/weekly/monthly report delivery via email (occupancy, revenue, activity, trends)
- **API Versioning & Deprecation** -- `X-API-Version` header, deprecation notices, version changelog endpoint

### v4.0.0 Highlights

- **Plugin/Extension System** -- Event-hook based plugin architecture with admin marketplace UI, 2 built-in plugins (Slack Notifier, Auto-Assign Preferred Spot)
- **GraphQL API** -- Query parser mapped to REST handlers with interactive GraphiQL playground
- **Compliance Reports** -- GDPR/DSGVO compliance dashboard with 10 automated checks, Art. 30 data map, audit trail export

### v3.6--v3.9 Highlights

- **Parking History & Stats** -- Personal booking timeline with monthly trends and favourite lot stats
- **Geofencing & Auto Check-in** -- GPS proximity-based auto check-in
- **Enhanced Waitlist** -- Priority-based with accept/decline offers and 15-minute expiry
- **Digital Parking Pass** -- QR badge with public verification endpoint
- **Absence Approval Workflows** -- Submit/approve/reject with admin queue and comments
- **Calendar Drag-to-Reschedule** -- Drag events to new dates with conflict detection
- **Customizable Admin Widgets** -- 8 configurable dashboard widgets with per-user layout
- **Kubernetes Helm Chart** -- Production chart with HPA, PVC, Laravel-specific config
- **k6 Load Testing Suite** -- Smoke, load, stress, and spike test scripts
- **Postman Collection** -- 100+ requests with auto-token handling

### Core Highlights

- **Full booking lifecycle** -- One-tap quick booking, recurring reservations, guest bookings, swap requests, waitlists, automatic no-show release
- **Automatic pricing** -- Hourly rate x duration, 19% German VAT, daily max cap, monthly passes, dynamic pricing
- **Visual lot editor** -- Per-lot zones, slot types (standard, compact, handicap, EV, VIP, motorcycle), real-time occupancy, public lobby display
- **Interactive map** -- Leaflet-based map view with color-coded availability markers
- **4-tier RBAC** -- User, premium, admin, superadmin with Sanctum token auth and 2FA/TOTP
- **Vehicle management** -- Photo upload, German licence plate city-code lookup (400+ codes)
- **Absence tracking** -- Homeoffice, vacation, sick leave with iCal import/export and team overview
- **10 languages** -- EN, DE, FR, ES, IT, PT, TR, PL, JA, ZH with runtime hot-loading
- **12 switchable themes** -- theme switching is part of the product contract, but the exact runtime theme set is still being pulled onto a shared semantic registry and parity gate
- **PWA** -- Installable as native app, service worker for offline capability, Command Palette (Ctrl+K)
- **Observability** -- Prometheus metrics at `/api/metrics`, health endpoints, structured logging

### Auth Contract

- **Core auth** -- login, registration, password reset, RBAC, 2FA/TOTP, session management
- **Integration auth** -- OAuth providers such as Google and GitHub
- **Enterprise identity** -- SAML/SSO and similar flows remain optional and runtime-sensitive

### Theme Contract

- **Shared product surface** -- themes are a core ParkHub surface, not decorative runtime extras
- **Semantic parity first** -- theme switching must preserve state clarity, hierarchy, contrast, and critical controls across runtimes
- **Registry alignment in progress** -- the current PHP frontend exposes a different concrete theme inventory than the public README previously claimed, so public naming is gated until both runtimes match the shared registry

### Security

- **httpOnly cookie auth** with SameSite=Lax (XSS-proof, Bearer fallback for APIs)
- bcrypt password hashing (12 rounds), configurable password policies
- 2FA/TOTP with QR enrollment, backup codes
- **Laravel Policies** (11 total, covering `Booking`, `Vehicle`, `Absence`, `Announcement`, `AuditLog`, `Favorite`, `Notification`, `ParkingLot`, `Tenant`, `Webhook`, `Widget` -- up from 3 in previous releases)
- **Multi-tenancy hardening** -- tenant scope enforced on admin analytics + CSV exports + rate-limit cache keys, plus cross-tenant admin write guards on user updates
- Per-endpoint rate limiting (login, register, payments) with tenant-namespaced cache keys
- Nonce-based CSP, security headers middleware
- SVG blocked from branding logo uploads
- Full audit log with IP tracking
- API key authentication for integrations
- OWASP Top 10 compliance -- see [Security Model](docs/SECURITY.md)

### Admin & Analytics

- Live occupancy dashboard with booking heatmaps
- Revenue analytics with 30-day trends, peak hours, top lots
- **CO₂ tracking** -- per-booking CO₂ estimates via `FuelType` enum + `/api/v1/bookings/co2-summary` (carpool detection, dashboard KPI tile, 10-locale copy)
- Rate limit monitoring dashboard
- CSV export, PDF invoices, admin reports
- Custom branding, announcements, outbound webhooks
- Multi-tenant support for enterprise deployments

### Notification Contract

- **Core notifications** -- in-app notifications plus transactional email
- **Advanced notifications** -- Web Push via VAPID where configured
- **Gated channels** -- SMS/WhatsApp preference surfaces exist, but delivery remains gated unless explicitly proven operational in the active runtime

### Guest and Pass Contract

- **Core guest flow** -- guest bookings and host-visible guest handling
- **Advanced pass flow** -- digital passes, QR generation, visitor pre-registration, and check-in surfaces
- **Runtime-sensitive surfaces** -- QR/check-in/public verification flows should be treated as advanced and runtime-sensitive, not as unconditional baseline behavior

### Legal Compliance

- **GDPR / DSGVO** -- Art. 15 data export, Art. 17 erasure, Art. 20 portability
- **German law** -- DDG SS5 Impressum, TTDSG SS25 cookie policy, SS147 AO retention
- **7 legal templates** -- Impressum, Datenschutz, AGB, Widerrufsbelehrung, AVV, VVT, Cookie Policy
- **International** -- UK GDPR, CCPA, nDSG (Switzerland), LGPD (Brazil) compatible
- See [GDPR Guide](docs/GDPR.md) | [Compliance Matrix](docs/COMPLIANCE.md)

---

## Module System

ParkHub organizes functionality into **70 modules** across **11 categories** — Core, Booking, Vehicle, Admin, Payment, Integration, Analytics, Compliance, Notification, Enterprise, Experimental — in a single declarative registry at [`app/Services/ModuleRegistry.php`](app/Services/ModuleRegistry.php).

Every module is exposed in the admin dashboard at `/admin/modules` with status pills, category grouping, search, dependency chain, and config-keys count. Shipped in **v4.13.0** (v1 + v2 + v3):

- **Runtime enable/disable** — 13 safe modules flip via `PATCH /api/v1/admin/modules/{name}` without a redeploy (widgets, themes, favorites, lobby-display, accessible, calendar-drag, ev-charging, maintenance, geofence, map, graphql, api-docs, setup-wizard). Security-sensitive modules (`auth`, `payments`, `rbac`, `webhooks`, `audit-export`, `multi-tenant`, `notifications`) stay env-flagged.
- **JSON Schema config editor** — 5 modules ship a `config_schema` (JSON Schema 2020-12) and surface a per-module config modal: `themes`, `announcements`, `notifications`, `email-templates`, `widgets`. Writes validate server-side via `opis/json-schema`; failures return `422 CONFIG_VALIDATION_FAILED` with a structured `details` array.
- **Command Palette** — `Cmd+K` / `Ctrl+K` / `/` auto-seeds "Go to…" entries from every active module with a `ui_route`.
- **Module Gate middleware** — `App\Http\Middleware\ModuleGate` returns `404 MODULE_DISABLED` for runtime-disabled routes (indistinguishable from an uninstalled feature).
- **Audit log** — every toggle and every config write emits an `AuditLog` row with actor, module slug, before/after value, timestamp, and originating IP.

Compile-in availability is still gated via `MODULE_*=true|false` environment variables (see `config/modules.php`); the runtime toggle layers on top of that.

See [docs/FEATURES.md § Modular UX Platform](docs/FEATURES.md#modular-ux-platform) for the full surface description and [API.md § Modules](docs/API.md) for the HTTP contract.

---

## Architecture

```
                    +---------------------------------+
                    |     React 19 SPA                |
                    |   TypeScript - Tailwind CSS 4   |
                    +---------------+-----------------+
                                    | httpOnly Cookie + Bearer (Sanctum)
                    +---------------v-----------------+
                    |     Laravel 13 + PHP 8.4         |
                    |   /api/v1/*  - /api/metrics      |
                    |   /health/*  - Web Push (VAPID)  |
                    +---------------------------------+
                    |  MySQL 8 - SQLite - PostgreSQL   |
                    +---------------------------------+
                        Docker - Shared Hosting - VPS
```

ParkHub PHP is designed for maximum deployment flexibility. It runs on 3 EUR/month shared hosting (Strato, IONOS, All-Inkl) with just PHP and MySQL, scales up to Docker Compose and Kubernetes, and supports PostgreSQL for cloud-native PaaS platforms like Render and Railway.

The same React 19 frontend is shared with the [Rust edition](https://github.com/nash87/parkhub-rust), and both editions are intended to stay aligned under the same ParkHub product model. Deployment tradeoffs and advanced module hardening can still differ by runtime.

For a deep dive into the directory layout, controllers, middleware, database schema, and frontend internals, see **[ARCHITECTURE.md](ARCHITECTURE.md)**.

---

## Screenshots

| | |
|---|---|
| ![Dashboard](docs/screenshots/02-dashboard.png) | ![Booking](docs/screenshots/05-book.png) |
| Dashboard with occupancy stats | Interactive booking flow |
| ![Admin Panel](docs/screenshots/08-admin.png) | ![Dark Mode](docs/screenshots/09-dark-mode.png) |
| Admin panel with lot management | Full dark mode support |
| ![Modules Dashboard](docs/screenshots/10-modules-dashboard.png) | ![Command Palette](docs/screenshots/11-command-palette.png) |
| Admin Modules Dashboard — toggle plugins + edit JSON-schema config without redeploying (v4.13.0) | Command Palette (Cmd+K) — navigate + run actions from one search bar |

---

## Deployment Options

| Method | Complexity | Cost | Best For |
|--------|------------|------|----------|
| **Shared Hosting** | Low | 3 EUR/mo | Small teams, personal use |
| **Docker** | Low | VPS cost | Standard deployment |
| **VPS / LAMP** | Medium | VPS cost | Full control |
| **PaaS** (Render, Railway) | Low | Free tier available | Quick demos, startups |
| **Kubernetes** | High | Cluster cost | Enterprise, multi-tenant |

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for step-by-step guides for each method.

---

## Testing

**1,320 feature tests + 434 unit tests** across the Laravel backend, plus Vitest frontend and 29 Playwright E2E specs. CI runs on every push via GitHub Actions. Lighthouse CI currently enforces accessibility >= 95 and performance >= 75.

```bash
composer test                       # PHPUnit backend (feature + unit)
cd parkhub-web && npx vitest run    # Frontend
npx playwright test                 # E2E
```

Supplementary safety nets (all CI-enforced):

- **`infection-php`** -- mutation testing (nightly, `.github/workflows/infection.yml`)
- **`schemathesis`** -- OpenAPI contract fuzzing against `docs/openapi/php.json` (nightly)
- **Lighthouse CI** -- a11y ≥ 95, perf ≥ 75, SEO ≥ 90 gates
- **CodeQL** -- automated JS/TS code scanning on every PR
- **Trivy** -- container image vulnerability scanning
- **Dependabot** -- automated dependency updates with auto-merge for patch/minor
- **SBOM + cosign** -- every release image attested with Syft SBOM and cosign signature

---

## Configuration

Key environment variables (full list in [docs/CONFIGURATION.md](docs/CONFIGURATION.md)):

| Variable | Purpose |
|----------|---------|
| `DB_CONNECTION` | `mysql`, `sqlite`, or `pgsql` |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database connection |
| `DATABASE_URL` | Alternative single-URL format (PaaS platforms) |
| `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` | SMTP email |
| `PARKHUB_ADMIN_EMAIL` / `PARKHUB_ADMIN_PASSWORD` | Initial admin account |
| `DEMO_MODE=true` | Enable demo overlay with 6-hour auto-reset |
| `MODULE_*=true\|false` | Toggle individual modules (see [Module System](#module-system)) |

---

## API Documentation

Full REST API documentation at `/api/v1/*` is available in [docs/API.md](docs/API.md). The API mirrors the [Rust edition](https://github.com/nash87/parkhub-rust) endpoint structure, making both backends interchangeable.

The complete OpenAPI 3.0 spec is snapshotted at [`docs/openapi/php.json`](docs/openapi/php.json) and regenerated via `composer openapi:dump` on every schema change — a CI drift gate (`make drift`) blocks any handler change that forgets to update it. Interactive API documentation is available via Scramble at `/docs/api` when enabled.

---

## Legal Compliance

ParkHub PHP is designed for legal compliance across multiple jurisdictions. Audited against **9 regulatory frameworks**:

**GDPR** (EU) | **DSGVO** (DE) | **TTDSG** (DE) | **DDG** (DE) | **BDSG** (DE) | **NIS2** (EU) | **CCPA** (US) | **UK GDPR** | **nDSG** (CH)

All legal documents are provided as **operator-customizable templates** -- not binding legal texts.

| Document | Purpose | Location |
|----------|---------|----------|
| **GDPR / DSGVO Guide** | Full DSGVO compliance documentation | [docs/GDPR.md](docs/GDPR.md) |
| **Compliance Matrix** | German, EU, and international law mapping | [docs/COMPLIANCE.md](docs/COMPLIANCE.md) |
| **Security Model** | Architecture, OWASP, encryption, disclosure | [docs/SECURITY.md](docs/SECURITY.md) |
| **Privacy Notice Template** | Ready-to-use Datenschutzerklarung (DE) | [docs/PRIVACY-TEMPLATE.md](docs/PRIVACY-TEMPLATE.md) |
| **Impressum Template** | German Impressum per DDG SS5 | [docs/IMPRESSUM-TEMPLATE.md](docs/IMPRESSUM-TEMPLATE.md) |
| **Third-Party Licenses** | All dependencies with license verification | [LICENSE-THIRD-PARTY.md](LICENSE-THIRD-PARTY.md) |
| **AGB Template** | Terms of service (DE) | [legal/agb-template.md](legal/agb-template.md) |
| **AVV Template** | Data processing agreement (DE) | [legal/avv-template.md](legal/avv-template.md) |
| **VVT Template** | Records of processing activities | [legal/vvt-template.md](legal/vvt-template.md) |
| **Cookie Policy** | TTDSG SS25 localStorage documentation | [legal/cookie-policy-template.md](legal/cookie-policy-template.md) |
| **Widerrufsbelehrung** | Consumer withdrawal notice (DE) | [legal/widerrufsbelehrung-template.md](legal/widerrufsbelehrung-template.md) |
| **BFSG Accessibility** | German Accessibility Improvement Act statement (required for most commercial deployments from 2025-06-28) | [legal/bfsg-barrierefreiheit-template.md](legal/bfsg-barrierefreiheit-template.md) |
| **EU AI Act Transparency** | Art. 50 transparency notice -- required if the operator enables AI/ML features | [legal/ai-act-transparency-template.md](legal/ai-act-transparency-template.md) |

See [`legal/`](legal/) for the full template set before deployment.

---

## Rust Edition

A feature-equivalent **Rust edition** (Axum + redb embedded DB) exists for environments that need a single binary with zero dependencies, AES-256-GCM database encryption, or a desktop client with system tray integration.

**[nash87/parkhub-rust](https://github.com/nash87/parkhub-rust)**

---

## Contributing

Contributions welcome -- see [DEVELOPMENT.md](DEVELOPMENT.md) for the local dev loop and [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, branch naming conventions, testing requirements, code style (Pint + Larastan/PHPStan level 4), and PR process.

Contributor quickstart:

```bash
pre-commit install          # install local git hooks (config in .pre-commit-config.yaml)
composer ci                 # fast backend gate — lint + feature tests
# or:
make ci                     # broader local gate: lint + static-analysis + test + frontend + drift
make act                    # optional: run the actual workflows locally via nektos/act (.actrc preconfigured)
```

Mutation testing (Infection) runs weekly via `.github/workflows/infection.yml` (`infection.json5` gates survivors). OpenAPI parity with the [Rust edition](https://github.com/nash87/parkhub-rust) is tracked via [docs/openapi-parity.md](docs/openapi-parity.md) + `scripts/dump-openapi.sh` / `scripts/diff-openapi.sh`; current CI still hard-gates only self-snapshot drift.

Bug reports and feature requests: [GitHub Issues](https://github.com/nash87/parkhub-php/issues)

Security vulnerabilities: [Security Policy](SECURITY.md) (do not open public issues)

---

## License

MIT -- see [LICENSE](LICENSE).

All third-party dependencies are MIT, Apache-2.0, or BSD licensed. See [LICENSE-THIRD-PARTY.md](LICENSE-THIRD-PARTY.md) for the full list.
