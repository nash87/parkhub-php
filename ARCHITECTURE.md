# ParkHub PHP -- Architecture

ParkHub is a self-hosted parking management system. This is the **PHP edition**,
built on Laravel 12 with a React 19 / Astro 6 frontend served as static assets
from Apache.

A feature-equivalent **Rust edition** (Axum + redb) exists in a sibling
repository. Both backends expose the same `/api/v1/*` REST surface and share the
identical `parkhub-web` frontend, making them fully interchangeable.

## Directory Structure

```
parkhub-php/
├── composer.json               # Laravel 12, Sanctum, web-push
├── Dockerfile                  # Multi-stage: frontend build -> PHP 8.4 + Apache
├── docker-compose.yml          # App + MySQL + worker + scheduler
├── render.yaml                 # Render free-tier deployment manifest
│
├── app/
│   ├── Console/Commands/       # Artisan commands
│   │   ├── AutoReleaseBookings.php    # Scheduled: release expired bookings
│   │   ├── CreateAdminUser.php        # Initial admin creation
│   │   ├── GenerateVapidKeys.php      # VAPID key generation for Web Push
│   │   └── RefillMonthlyCredits.php   # Monthly credit quota refill
│   ├── Http/
│   │   ├── Controllers/Api/    # 25 API controllers
│   │   │   ├── AuthController.php           # Login, register, forgot/reset password
│   │   │   ├── BookingController.php        # CRUD + quick-book, guest, swap (32K)
│   │   │   ├── AdminController.php          # User/booking management, audit log
│   │   │   ├── AdminSettingsController.php  # Settings, branding, privacy, email (20K)
│   │   │   ├── AdminReportController.php    # Stats, heatmap, CSV export
│   │   │   ├── AdminCreditController.php    # Credit grants, refills, transactions
│   │   │   ├── AdminAnnouncementController.php  # Announcement CRUD
│   │   │   ├── LotController.php            # Parking lot CRUD + occupancy
│   │   │   ├── SlotController.php           # Slot CRUD within lots
│   │   │   ├── ZoneController.php           # Zone management
│   │   │   ├── UserController.php           # Profile, prefs, notifications, export
│   │   │   ├── VehicleController.php        # Vehicle CRUD + photo upload (42K)
│   │   │   ├── BookingInvoiceController.php # PDF invoice generation
│   │   │   ├── AbsenceController.php        # Absence CRUD + iCal import
│   │   │   ├── RecurringBookingController.php # Recurring booking management
│   │   │   ├── WaitlistController.php       # Waitlist CRUD
│   │   │   ├── TeamController.php           # Team directory + today view
│   │   │   ├── MiscController.php           # Push, email, QR, webhooks
│   │   │   ├── DemoController.php           # Demo mode: status, vote-reset
│   │   │   ├── SetupController.php          # First-run setup wizard
│   │   │   ├── HealthController.php         # Health + readiness checks
│   │   │   ├── MetricsController.php        # Prometheus metrics
│   │   │   ├── PublicController.php         # Public occupancy display
│   │   │   └── SystemController.php         # System info
│   │   ├── Middleware/
│   │   │   ├── ApiResponseWrapper.php       # Wraps responses in { success, data } envelope
│   │   │   ├── ForceJsonResponse.php        # Forces Accept: application/json
│   │   │   ├── RequireAdmin.php             # Admin role gate
│   │   │   └── SecurityHeaders.php          # CSP, HSTS, X-Frame, etc.
│   │   └── Resources/           # API Resource transformers (10 resources)
│   ├── Jobs/                    # Queue jobs (push notifications, etc.)
│   ├── Mail/                    # Mailable classes
│   ├── Models/                  # 22 Eloquent models
│   ├── Providers/               # Service providers
│   └── Services/                # Business logic services
│
├── routes/
│   ├── api.php                 # Laravel-style API routes (108 endpoints)
│   ├── api_v1.php              # Rust-compatible /api/v1/* routes (mirrored surface)
│   ├── web.php                 # SPA fallback route
│   └── console.php             # Scheduled commands
│
├── database/
│   ├── migrations/             # 13 migration files
│   │   ├── *_create_users_table.php
│   │   ├── *_create_parkhub_tables.php    # Core: lots, slots, bookings, vehicles, etc.
│   │   ├── *_create_waitlist_entries_table.php
│   │   ├── *_add_missing_indexes.php
│   │   ├── *_add_credits_system.php
│   │   ├── *_create_swap_requests_table.php
│   │   └── *_add_slot_features_*.php      # EV, handicap, pricing
│   ├── factories/              # Model factories for testing
│   └── seeders/                # Database seeders
│
├── parkhub-web/                # Shared React frontend (identical to Rust repo)
│   ├── astro.config.mjs        # Static output, React compiler, Tailwind, chunk splitting
│   ├── package.json            # v1.4.6
│   ├── public/                 # PWA manifest, service worker, icons
│   ├── e2e/                    # 14 Playwright E2E test specs
│   └── src/                    # React app (see Frontend Architecture)
│
├── resources/js/               # Legacy Vite-based frontend (backup)
│   └── src/                    # Older React app structure
│
├── config/                     # Laravel config files
├── legal/                      # German legal document templates (GDPR)
├── docs/                       # Documentation + screenshots
├── tests/
│   ├── Feature/                # 46 PHPUnit feature test files (461 test methods)
│   └── Unit/                   # Unit tests
└── .github/workflows/          # CI, Docker publish
```

## Backend Architecture

### Request Flow

```
Client Request
  │
  ▼
┌──────────────────────────────────────────────────┐
│  Apache + mod_rewrite → public/index.php         │
│                                                   │
│  Laravel Middleware Pipeline                       │
│  ┌─────────────────────────────────────────────┐ │
│  │ ForceJsonResponse (Accept: application/json) │ │
│  │ SecurityHeaders (CSP, HSTS, X-Frame, etc.)   │ │
│  │ ApiResponseWrapper ({ success, data } env.)  │ │
│  │ ThrottleRequests (rate limiting)              │ │
│  │   ├── auth:     per-IP login/register limits │ │
│  │   ├── password-reset: 3/15min/IP             │ │
│  │   └── setup:    tight mutation limits         │ │
│  └─────────────────────────────────────────────┘ │
│                                                   │
│  Sanctum Token Authentication (Bearer)            │
│  RequireAdmin Middleware (admin routes only)       │
│                                                   │
│  Route → Controller Method                        │
│  │                                                │
│  ▼                                                │
│  Request Validation ($request->validate())         │
│  │                                                │
│  ▼                                                │
│  Business Logic (Controller / Service)             │
│  │                                                │
│  ▼                                                │
│  Eloquent ORM → Database (SQLite or MySQL)         │
│  │                                                │
│  ▼                                                │
│  API Resource Transform → JSON Response            │
└──────────────────────────────────────────────────┘
```

### Authentication

- **Laravel Sanctum** for API token authentication
- Bearer tokens in `Authorization` header
- Passwords hashed with bcrypt (12 rounds)
- Token expiry configurable per-session
- Personal access tokens stored in `personal_access_tokens` table

### Error Handling

Responses wrapped by `ApiResponseWrapper` middleware into a consistent envelope:

Success:
```json
{ "success": true, "data": { ... } }
```

Error:
```json
{ "success": false, "error": { "code": "NOT_FOUND", "message": "..." } }
```

Laravel's exception handler maps common exceptions to appropriate HTTP codes.
Validation errors return field-level detail.

### Middleware Stack

| Middleware          | Purpose                                              |
|---------------------|------------------------------------------------------|
| `ForceJsonResponse` | Ensures all API responses are JSON                   |
| `SecurityHeaders`   | CSP, HSTS, X-Content-Type, X-Frame-Options, etc.    |
| `ApiResponseWrapper`| Wraps responses in `{ success, data }` envelope      |
| `RequireAdmin`      | Gates admin endpoints to admin/superadmin roles       |
| `throttle:auth`     | Rate limits auth endpoints per IP                     |

## Frontend Architecture

The `parkhub-web/` directory contains the shared frontend -- identical code
powers both the Rust and PHP editions. Build scripts target each backend:

- `npm run build:php` -- builds and copies to `public/`
- `npm run build:rust` -- builds and copies to Rust's embedded assets

### Stack

| Layer          | Technology                                           |
|---------------|------------------------------------------------------|
| Meta-framework | Astro 6 (static output mode)                        |
| UI framework   | React 19 with React Compiler                        |
| Styling        | Tailwind CSS v4                                     |
| Animations     | Framer Motion                                       |
| State          | Zustand (stores) + React Context (auth/theme/features) |
| Routing        | React Router v7 (lazy-loaded routes)                |
| i18n           | i18next + browser language detection (10 locales)   |
| Icons          | Phosphor Icons                                      |
| HTTP client    | Typed `fetch` wrapper (`api/client.ts`)             |
| Testing        | Vitest (32 unit test files) + Playwright (14 E2E specs) |

### Routing

All pages are lazy-loaded via `React.lazy()`. Auth-required pages wrapped in
`ProtectedRoute`; admin pages additionally wrapped in `AdminRoute`:

- `/welcome` -> Welcome/language selector
- `/login`, `/register`, `/forgot-password` -> Auth pages
- `/` -> Dashboard
- `/book`, `/bookings`, `/calendar` -> Booking management
- `/vehicles`, `/absences`, `/credits` -> User resources
- `/profile`, `/team`, `/notifications` -> User pages
- `/admin/*` -> Admin panel (settings, users, lots, reports, announcements)

### Theme System

- Three modes: `light`, `dark`, `system`
- Persisted in `localStorage` as `parkhub_theme`
- OS preference tracked via `useSyncExternalStore` + `matchMedia`
- Use-case theming via `data-usecase` attribute on `<html>`

### Code Splitting

Manual chunks in Astro/Vite config:
- `vendor-react` (react, react-dom, react-router)
- `vendor-motion` (framer-motion)
- `vendor-i18n` (i18next stack)

## Database Schema

### Engine

**SQLite** for development and single-node deployments (Render free tier).
**MySQL 8** supported for production via `docker-compose.yml`.

### Core Tables

```
users
├── id (uuid, PK)
├── username (unique)
├── email (unique)
├── password (bcrypt hash)
├── role: user | premium | admin | superadmin
├── preferences (JSON)
├── is_active, department, phone, picture
├── credits_balance, credits_monthly_quota
└── last_login, created_at, updated_at

parking_lots
├── id (uuid, PK)
├── name, address
├── total_slots, available_slots
├── layout (JSON)
├── status: open | closed | maintenance
├── pricing_type, base_price_per_hour, currency
└── created_at, updated_at

parking_slots
├── id (uuid, PK)
├── lot_id (FK → parking_lots)
├── zone_id (FK → zones, nullable)
├── slot_number, status
├── reserved_for_department
├── is_ev_charging, is_handicap, is_covered
└── created_at, updated_at

bookings
├── id (uuid, PK)
├── user_id (FK → users)
├── lot_id (FK → parking_lots)
├── slot_id (FK → parking_slots)
├── booking_type, lot_name, slot_number
├── vehicle_plate
├── start_time, end_time
├── status: confirmed | cancelled | completed | no_show
├── notes, recurrence (JSON)
├── checked_in_at
├── base_amount, tax_amount, total_amount, payment_status
└── created_at, updated_at
    INDEX(slot_id, start_time, end_time)
    INDEX(user_id, status)

vehicles
├── id (uuid, PK)
├── user_id (FK → users)
├── plate, make, model, color
├── type: car | motorcycle | electric | suv
├── is_default, photo_url
└── created_at, updated_at
```

### Supporting Tables

```
zones               → lot_id, name, color, description
absences            → user_id, type (homeoffice/vacation/sick/training), dates, source
recurring_bookings  → user_id, lot_id, slot_id, days_of_week (JSON), times
guest_bookings      → created_by, guest_name, guest_code (unique), dates
swap_requests       → requester_id, target_booking_id, status
waitlist_entries    → user_id, lot_id, preferred_date, slot_type
credit_transactions → user_id, amount, type (monthly_refill/admin_grant/booking_debit)
favorites           → user_id, slot_id (unique pair)
notifications       → user_id, type, title, message, read
announcements       → title, message, severity, active, expires_at
push_subscriptions  → user_id, endpoint, p256dh, auth (VAPID)
webhooks            → url, events (JSON), secret, active
booking_notes       → booking_id, user_id, note
audit_log           → user_id, action, details (JSON), ip_address
settings            → key-value store (setup_completed, branding, etc.)
```

## API Design

### Dual Route Files

The PHP edition maintains two route files for full compatibility:

1. **`routes/api.php`** -- Laravel-idiomatic routes (e.g., `POST /api/login`)
2. **`routes/api_v1.php`** -- Rust-compatible routes prefixed with `/api/v1/*`

Both files map to the same controllers. The v1 routes ensure the shared frontend
works identically against either backend.

### Key Endpoint Groups

| Group          | Prefix                  | Auth      | Endpoints                            |
|---------------|-------------------------|-----------|--------------------------------------|
| Health         | `/api/v1/health/*`      | None      | live, ready                          |
| Auth           | `/api/v1/auth/*`        | None*     | login, register, forgot, reset       |
| Setup          | `/api/v1/setup/*`       | None      | status, complete, change-password    |
| Lots           | `/api/v1/lots/*`        | Sanctum   | CRUD + slots, zones, occupancy       |
| Bookings       | `/api/v1/bookings/*`    | Sanctum   | CRUD + quick-book, guest, swap       |
| Vehicles       | `/api/v1/vehicles/*`    | Sanctum   | CRUD + photo upload                  |
| Users          | `/api/v1/users/me`      | Sanctum   | Profile, prefs, password, export     |
| Credits        | `/api/v1/credits/*`     | Sanctum   | Balance, history                     |
| Admin          | `/api/v1/admin/*`       | Admin     | Users, bookings, reports, settings   |
| Demo           | `/api/v1/demo/*`        | None      | Status, vote-reset, heartbeat        |
| Metrics        | `/api/v1/metrics`       | None      | Prometheus format                    |
| Public         | `/api/v1/public/*`      | None      | Occupancy display                    |
| Theme          | `/api/v1/theme`         | None      | Use-case CSS theme config            |

### API Resources

Eloquent models are transformed via Laravel API Resources before serialization:
`BookingResource`, `ParkingLotResource`, `ParkingSlotResource`,
`NotificationResource`, `FavoriteResource`, `VehicleResource`, etc.

## Testing Strategy

### Backend (PHP)

| Type          | Count | Framework | Location                        |
|--------------|-------|-----------|---------------------------------|
| Feature tests | 500+  | PHPUnit   | `tests/Feature/*.php` (46 files) |
| Unit tests    | 1     | PHPUnit   | `tests/Unit/ExampleTest.php`     |

Feature tests cover the full HTTP surface: auth flows, booking CRUD, admin
operations, credit system, edge cases, GDPR compliance, webhooks, etc.

Run with: `php artisan test` or `composer test`

### Frontend (React)

| Type          | Count | Framework        | Location                     |
|--------------|-------|------------------|------------------------------|
| Unit/component| 32    | Vitest + Testing Library | `src/**/*.test.{ts,tsx}` |
| E2E           | 14    | Playwright       | `e2e/*.spec.ts`              |

### Integration Tests

10 integration test suites covering cross-module interactions:
- Auth flow end-to-end (login, refresh, session management)
- Booking lifecycle (create, modify, cancel, check-in, check-out)
- Admin CRUD operations (lots, slots, zones, announcements)
- Concurrent booking conflict detection
- Credit system integration (deduction, refill, history)
- Notification delivery pipeline
- Webhook delivery and retry logic
- GDPR data export and erasure
- Module toggle validation (enabled/disabled states)
- Security boundary enforcement (RBAC, rate limiting)

### Simulation Engine

A 1-month booking cycle simulation engine with 3 configurable profiles:

| Profile | VUs | Duration | Scenario |
|---------|-----|----------|----------|
| Small office | 10 | 30 days | Single lot, standard hours |
| Campus | 50 | 30 days | Multiple lots, recurring bookings, guest passes |
| Enterprise | 200 | 30 days | Multi-tenant, dynamic pricing, high concurrency |

The simulation creates realistic booking patterns including peak hours, cancellations,
no-shows, and waitlist activity. Run via `php artisan test --filter=Simulation` or the
E2E full-workflow spec.

### Load Testing

Performance testing scripts with [k6](https://grafana.com/docs/k6/) live in `tests/load/`:

| Script | Profile | Description |
|--------|---------|-------------|
| `smoke.js` | 1 VU, 30s | Quick sanity check |
| `load.js` | 50 VUs, 5min | Sustained load baseline |
| `stress.js` | 100 VUs, 10min | All endpoints stress test |
| `spike.js` | 1 -> 200 -> 1 VUs | Sudden surge test |

### Test Pyramid

```
           +----------+
           |  29 E2E  |  Playwright (browser + API)
           |  specs   |
          ++---------++
          |  10 Integ. |  Cross-module API tests
          |  suites    |
         ++-----------++
         |  ~500 Unit   |  PHPUnit + Vitest
         |  tests       |
        ++--------------+
        |  k6 Load Tests |  smoke / load / stress / spike
        +----------------+
```

## Deployment

### Docker (self-hosted)

Multi-stage Dockerfile:
1. **Frontend build** -- Node 22 slim, `npm ci && npm run build`
2. **PHP runtime** -- PHP 8.4 + Apache on Debian Bookworm
   - Extensions: pdo_sqlite, pdo_mysql, gd, zip, bcmath
   - Composer 2 with optimized autoloader
   - Non-root Apache user, security headers hardened

`docker-compose.yml` provides four services:

| Service    | Role                                    | Memory |
|------------|----------------------------------------|--------|
| `app`      | PHP + Apache, serves API + frontend     | 512 MB |
| `db`       | MySQL 8 (not exposed to host)           | 512 MB |
| `worker`   | `queue:work` for async jobs             | 256 MB |
| `scheduler`| `schedule:run` loop (60s interval)      | 128 MB |

### Cloud (Render)

Pre-built GHCR image deployed to Render free tier:
- SQLite database (ephemeral on free tier)
- `DEMO_MODE=true` for public demo
- Auto-configured admin credentials

### Demo Mode

Activated via `DEMO_MODE=true`:
- Collaborative vote-to-reset (3 votes triggers data reset)
- Auto-reset on schedule
- Demo overlay in frontend showing countdown + viewer count
- Pre-seeded with realistic parking data

### Scheduled Commands

Defined in `routes/console.php`:

| Command                    | Schedule     | Purpose                          |
|---------------------------|--------------|----------------------------------|
| `AutoReleaseBookings`     | Every 15 min | Release expired/no-show bookings |
| `RefillMonthlyCredits`    | Monthly      | Reset user credit quotas         |
| Demo auto-reset           | Every 6h     | Reset demo data (if enabled)     |

## PWA

### Manifest

Full PWA manifest (`manifest.json`) with standalone display, maskable icons,
shortcuts (Quick Book, My Bookings, Calendar), and screenshots.

### Service Worker

Custom `sw.js` with three caching strategies:

1. **Static assets** -- Cache-first, version-keyed (purged on deploy)
2. **API reads** -- Stale-while-revalidate (24h max age) for bookings, lots, etc.
3. **Offline mutations** -- Queued in IndexedDB, replayed via Background Sync

Precached URLs: `/`, `/favicon.svg`, `/offline.html`, icon assets.

### Offline Support

- Fallback to `/offline.html` when network unavailable
- Cached API data enables read-only browsing offline
- POST/PUT/DELETE requests queued and synced when connectivity returns

## Key Design Decisions

### Why Dual-Stack (Rust + PHP)?

PHP (Laravel) provides rapid development, a massive hosting ecosystem, and is
familiar to most web developers. Rust provides maximum performance and a single
binary deployment. Both share the same API contract so the React frontend is
backend-agnostic.

### Why SQLite as Default?

Zero-configuration setup: no external database server needed. Perfect for
single-node deployments and free-tier hosting (Render). MySQL is supported for
production multi-instance setups via `docker-compose.yml`.

### Why Astro?

Astro's static output mode generates pre-rendered HTML + JS bundles that are
served directly by Apache (PHP) or embedded in the Rust binary. No SSR server
needed. The same `parkhub-web/` directory is shared between both repos with
dedicated build scripts (`build:php`, `build:rust`).

### Why Sanctum (not Passport)?

Sanctum is lighter weight for SPA token auth. No OAuth server complexity needed
-- ParkHub is a self-hosted tool, not a platform with third-party API consumers.

### Why Two Route Files?

`routes/api.php` follows Laravel conventions (shorter paths, grouped middleware).
`routes/api_v1.php` mirrors the Rust backend's exact endpoint structure so the
shared React frontend can hit `/api/v1/*` paths without any backend detection.
Both files route to the same controller methods.
