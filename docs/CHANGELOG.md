# Changelog — ParkHub PHP

All notable changes to ParkHub PHP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Planned

- Kubernetes Helm chart
- Email notification templates (custom branding)
- Recurring booking conflict detection improvements

---

## [1.0.1] — 2026-02-27

### Fixed

- **Security**: `Setup.tsx` auto-login bypass — the setup page previously attempted an
  automatic `admin`/`admin` login for any unauthenticated visitor when `needs_password_change`
  was set. Now auto-login only occurs when no admin account exists yet (genuine first install).
  If an admin already exists, the user is redirected to the normal login page.

- **Frontend**: Bookings page showed 0 items despite the counter displaying the correct total.
  The backend creates bookings with `status: 'confirmed'` but the filter only matched
  `status: 'active'`. Both `confirmed` and `active` now display in the Active/Upcoming sections.

- **Frontend**: Admin Privacy settings tab crashed with "Failed to load privacy settings" due
  to a template literal syntax error — `(import.meta.env.VITE_API_URL || "")` (parentheses)
  was used instead of `${import.meta.env.VITE_API_URL || ""}` (template expression), producing
  a malformed URL that always returned 404.

- **Frontend**: `LicensePlateInput` component truncated plates to 3 characters when a full
  plate string (e.g. `M-AB 1234`) was typed or pasted at once before selecting a city from
  the dropdown. The component now auto-detects and formats the full plate on input.

---

## [1.0.0] — 2026-02-27

Initial public release.

### Added

#### Core Infrastructure

- Laravel 12 + Sanctum JSON API backend with PHP 8.3
- React 19 + TypeScript + Tailwind CSS SPA frontend
- SQLite support for zero-dependency development and small deployments
- MySQL 8 support for production deployments
- Multi-stage Docker image: Node 20 frontend compile + PHP 8.3 Apache backend
- Docker Compose: app + MySQL 8 with named volumes
- `docker-entrypoint.sh`: auto-migration, default admin creation, config/route caching on startup

#### Authentication

- Laravel Sanctum Bearer token authentication with 7-day token expiry
- Login by username or email
- Registration with input validation (alpha_dash username, unique email, min 8-char password)
- Token refresh: revoke all + reissue
- Forgot password endpoint (rate limited 5/15min, prevents user enumeration)
- Change password endpoint (requires current password, rotates token)
- Account deletion with password confirmation (CASCADE)
- Rate limiting: 10 requests/minute on login and register endpoints

#### Parking Lot Management

- Full CRUD for parking lots (name, address, total_slots, status, layout JSON)
- Auto-generated slot layout from slot records (rows of 10) when no layout saved
- Real-time available slot count via optimized single-query occupancy calculation
- Lot occupancy endpoint (total, occupied, available, percentage)
- QR code endpoints for lot and individual slot (links to quick booking URL)
- Zone management: full CRUD for zones within lots (name, color, description)
- Slots assignable to zones; zone deletion sets zone_id to null on slots

#### Booking System

- Create booking with optional auto-slot assignment
- Conflict detection (overlapping bookings on same slot rejected with HTTP 409)
- Quick booking (auto-assign best available slot)
- Guest booking (named guest, unique guest code, no user account required for guest)
- Booking swap request workflow (create request, accept/reject)
- Check-in endpoint
- Update booking notes
- Cancel booking
- Filter bookings by status, from_date, to_date
- Booking confirmation email on creation (queued via `BookingConfirmation` Mailable)
- HTML invoice endpoint (printer-friendly, use browser Print → PDF)
- Calendar events endpoint for calendar view
- Audit log entries for booking create/delete

#### Recurring Bookings

- Create recurring patterns (days_of_week array, date range, start/end time)
- Full CRUD for recurring patterns

#### Absence Management

- Full CRUD for absences (homeoffice, vacation, sick, training, other)
- Absence patterns (recurring weekly homeoffice days)
- Team absences view (all users' absences for planning)
- iCal import (from `.ics` file upload)
- Legacy `homeoffice` and `vacation` endpoints for Rust-frontend compatibility

#### Vehicle Management

- Full CRUD for user vehicles (plate, make, model, color, is_default flag)
- Vehicle photo upload (multipart or base64, max 5/8 MB, GD validation, resize to 800px)
- Vehicle photo serve endpoint
- 400+ German Kfz-Unterscheidungszeichen (city code lookup)
- Photo deletion on vehicle delete

#### User Features

- User preferences (theme, language, timezone, notification settings, default lot)
- User statistics (total bookings, this month, homeoffice days, favourite slot)
- Favourite slots (add, list, remove)
- In-app notifications (list last 50, mark read, mark all read)
- iCal calendar export (bookings as `.ics` feed)
- Web Push notification subscription and unsubscription
- Webhooks per user (CRUD, plus admin-wide webhook settings)

#### Team Features

- Team list endpoint (all active users)
- Team today endpoint (office / homeoffice / vacation status per user)
- Waitlist for fully booked lots (CRUD)

#### Admin Panel

- Dashboard: total users, lots, slots, bookings, active bookings, occupancy %, homeoffice today
- Booking heatmap (day of week × hour, DB-agnostic: SQLite `strftime` / MySQL `DAYOFWEEK`)
- Booking reports (by day, status, booking_type, average duration)
- Dashboard chart data (booking trend, current occupancy)
- Paginated, searchable, filterable audit log
- Announcements CRUD (info / warning / error / success, expiry support)
- User management: list, update (name, email, role, is_active, department, password), delete
- Bulk user import (up to 500 users via JSON, skips existing usernames/emails)
- CSV export of all bookings
- Application settings (company name, use case, registration mode, licence plate mode, guest bookings, auto-release, branding colors)
- Email settings (SMTP host/port/user/pass, from address)
- Auto-release settings (enabled, timeout minutes)
- Webhook settings (admin-wide webhook list)
- Branding: company name, primary color, logo upload (2 MB max, default SVG logo fallback)
- Privacy / GDPR settings (policy text, retention days, gdpr_enabled flag)
- Impressum editor: all DDG §5 fields (provider name, legal form, address, email, phone, register, VAT ID, responsible person, custom text)
- Database reset endpoint (requires `confirm: "RESET"`, deletes all data except calling admin)

#### GDPR

- Art. 20 data export: full JSON (profile, bookings, absences, vehicles, preferences)
- Art. 17 anonymization: strips all PII, keeps booking records with `[GELÖSCHT]` plate, deactivates account
- Hard account deletion with CASCADE (alternative when no §147 AO retention needed)
- Audit log entry for GDPR erasure (anonymized ID and reason, not original PII)
- Legal templates: `impressum-template.md`, `datenschutz-template.md`, `agb-template.md`, `avv-template.md`

#### Public Endpoints

- Real-time lot occupancy for lobby displays
- Active announcements (no auth)
- Public branding (company name, colors, logo)
- Public Impressum (DDG §5 — legally required to be unauthenticated)
- Health endpoints: `GET /api/v1/health/live`, `GET /api/v1/health/ready`
- System version and maintenance status

#### Database

- 18 tables: `users`, `parking_lots`, `zones`, `parking_slots`, `bookings`, `vehicles`,
  `absences`, `settings`, `audit_log`, `announcements`, `notifications_custom`, `favorites`,
  `recurring_bookings`, `guest_bookings`, `booking_notes`, `push_subscriptions`, `webhooks`,
  `waitlist_entries`
- UUID primary keys on all domain tables
- Indexes on high-query columns (slot/time overlap, user/status, action/created_at)
- Foreign key constraints with appropriate cascade/set-null behavior

#### Email Notifications

- `WelcomeEmail` Mailable: sent on registration (queued)
- `BookingConfirmation` Mailable: sent on booking creation (queued)
- Queue driver: `database` by default; falls back gracefully when no worker is running

#### Deployment

- `Dockerfile`: multi-stage build (Node 20 + PHP 8.3 Apache)
- `docker-compose.yml`: app + MySQL 8 with health check and named volumes
- `docker-entrypoint.sh`: auto-migrate, create default admin, cache config/routes
- `deploy-shared-hosting.sh`: builds a zip for shared hosting upload
- `shell.nix`: Nix development environment

#### Frontend Pages (React 19 + TypeScript + Tailwind CSS)

- Login, Register, ForgotPassword, Setup wizard
- Dashboard, Book, Bookings, Calendar
- Vehicles, Team, Absences, Homeoffice, Vacation
- Profile, Admin, AdminBranding, AdminImpress, AdminPrivacy, AuditLog
- Impressum, Privacy, Terms, Legal, Help, About, Welcome
- OccupancyDisplay (unauthenticated lobby display page)

#### API Compatibility

- `/api/v1/*` routes mirror the ParkHub Rust edition endpoint structure
- Legacy `/api/*` routes for backwards compatibility
- `GET /api/v1/admin/updates/check` stub (returns `update_available: false`)

#### Demo Data

- `ProductionSimulationSeeder`: 10 German parking lots (München, Stuttgart, Köln, Frankfurt,
  Hamburg, Nürnberg, Karlsruhe, Heidelberg, Dortmund, Leipzig), 200 German-named users
  across 10 departments, ~3500 bookings over 30 days

---

## Version History

| Version | Date | Notes |
|---------|------|-------|
| 1.0.1 | 2026-02-27 | Security fix + 3 frontend bug fixes |
| 1.0.0 | 2026-02-27 | Initial public release |

[Unreleased]: https://github.com/nash87/parkhub-php/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/nash87/parkhub-php/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/nash87/parkhub-php/releases/tag/v1.0.0
