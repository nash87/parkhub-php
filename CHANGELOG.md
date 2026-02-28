# Changelog

All notable changes to ParkHub PHP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.1.0] — 2026-02-28

### Security

- **Admin middleware**: Created `RequireAdmin` middleware — all 10 admin routes now protected at route
  level via `Route::middleware(['admin'])`. Previously only enforced via in-method checks.
- **Rate limiting on auth routes**: `POST /auth/forgot-password` and `POST /auth/reset-password` now
  limited to 5 requests per 15 minutes per IP (was unprotected).
- **Double-booking race condition**: `BookingController::store()` now wraps slot conflict check and
  `Booking::create()` in `DB::transaction()` with `ParkingSlot::lockForUpdate()`. Prevents
  concurrent requests from double-booking the same slot.
- **Booking status IDOR**: `BookingController::update()` no longer accepts `status` from user input —
  only `notes` and `vehicle_plate` are updatable by users.
- **Sanctum token expiry**: Changed `expiration` from `null` (never) to `10080` minutes (7 days).

### Fixed

- **GDPR Art. 17 erasure**: `UserController::anonymizeAccount()` now fully anonymizes all data:
  vehicle photos deleted from storage, audit log entries anonymized (IP → `0.0.0.0`),
  guest bookings anonymized, user preferences cleared.
- **Recurring booking validation**: `RecurringBookingController` now validates `start_date ≥ today`,
  `end_date > start_date`, and `end_time > start_time`.

### Added

- `POST /auth/change-password` — change password (requires current password, rotates token)
- `POST /auth/refresh` — refresh Sanctum token (revoke all + reissue)
- `GET /legal/privacy` and `GET /legal/impressum` — public legal/transparency pages

---

## [1.0.1] — 2026-02-27

### Fixed

- **Security**: `Setup.tsx` auto-login bypass — the setup page previously attempted an
  automatic `admin`/`admin` login for any unauthenticated visitor when `needs_password_change`
  was set. Now auto-login only occurs when no admin account exists yet (genuine first install).
  If an admin already exists, the user is redirected to the normal login page.
- **Frontend**: Bookings page showed 0 items despite the counter displaying the correct total.
  Backend creates bookings with `status: 'confirmed'` but the filter only matched
  `status: 'active'`. Both `confirmed` and `active` now display in the Active/Upcoming sections.
- **Frontend**: Admin Privacy settings tab crashed with "Failed to load privacy settings" due
  to a template literal syntax error — `(import.meta.env.VITE_API_URL || "")` (parentheses)
  was used instead of `${import.meta.env.VITE_API_URL || ""}` (template expression), producing
  a malformed URL that always returned 404.
- **Frontend**: LicensePlateInput component truncated plates to 3 characters when a full plate
  string (e.g. `M-AB 1234`) was typed or pasted at once before selecting a city from the
  dropdown. The component now auto-detects and formats the full plate on input.

---

## [1.0.0] — 2026-02-27

### Added

**Core infrastructure**
- Laravel 12 + Sanctum API backend with PHP 8.3
- React 19 + TypeScript + Tailwind CSS frontend (SPA)
- SQLite support for zero-dependency development and small deployments
- MySQL 8 support for production deployments
- Docker image: PHP 8.3 + Apache, multi-stage build with Node 20 frontend compile
- Docker Compose configuration with MySQL 8 and named volumes
- `docker-entrypoint.sh`: auto-migration, default admin creation, config caching on startup

**Authentication**
- Laravel Sanctum Bearer token authentication with 7-day token expiry
- Login by username or email
- Registration with input validation (alpha_dash username, unique email, min 8-char password)
- Token refresh (revoke all + reissue)
- Forgot password endpoint (rate limited 5/15min, prevents user enumeration)
- Change password endpoint (requires current password, rotates token)
- Account deletion with password confirmation (CASCADE)
- Rate limiting: 10 requests/minute on login and register endpoints

**Parking lots**
- Full CRUD for parking lots (name, address, total_slots, status, layout JSON)
- Auto-generated slot layout from slot records (rows of 10) when no layout is stored
- Real-time available slot count via optimized single-query occupancy calculation
- Lot occupancy endpoint (total, occupied, available, percentage)
- QR code endpoint for lot and individual slot (links to quick booking URL)

**Zones**
- Full CRUD for zones within lots (name, color, description)
- Slots assignable to zones; zone deletion sets zone_id to null on slots

**Parking slots**
- Full CRUD for slots (slot_number, status, zone_id, reserved_for_department)
- Current booking status included in slot list response

**Bookings**
- Create booking with optional auto-slot assignment
- Conflict detection (overlapping bookings on same slot rejected with 409)
- Quick booking (auto-assign best available slot)
- Guest booking (named guest, unique guest code, no user account needed)
- Booking swap request workflow (create request, accept/reject)
- Check-in endpoint
- Update booking notes
- Cancel booking (delete)
- Filter bookings by status, from_date, to_date
- Booking confirmation email on creation (queued via `BookingConfirmation` Mailable)
- HTML invoice endpoint (printer-friendly, browser Print → PDF)
- Calendar events endpoint for calendar view
- Audit log entries for booking create/delete

**Recurring bookings**
- Create recurring patterns (days_of_week array, date range, start/end time)
- Full CRUD for recurring patterns

**Absences**
- Full CRUD for absences (homeoffice, vacation, sick, training, other)
- Absence patterns (recurring weekly homeoffice)
- Team absences view (all users' absences for planning)
- iCal import (from .ics file upload)
- Legacy `homeoffice` and `vacation` endpoints for Rust-frontend compatibility

**Vehicles**
- Full CRUD for user vehicles (plate, make, model, color, is_default)
- Vehicle photo upload (multipart or base64, max 5/8 MB, GD validation and resize to 800px)
- Vehicle photo serve endpoint
- 400+ German Kfz-Unterscheidungszeichen (city code lookup)
- Photo deletion on vehicle delete

**User features**
- User preferences (theme, language, timezone, notifications, default lot, locale)
- User statistics (total bookings, this month, homeoffice days, favourite slot)
- Favourite slots (add, list, remove)
- In-app notifications (list last 50, mark read, mark all read)
- iCal calendar export (bookings as .ics feed)
- Web Push notification subscription / unsubscription
- Webhooks (CRUD per user, plus admin-wide webhook settings)
- QR code endpoint per booking (`/api/qr/{bookingId}`)

**Team**
- Team list endpoint (all active users)
- Team today endpoint (office / homeoffice / vacation status)

**Waitlist**
- Full CRUD for waitlist entries

**Admin**
- Admin dashboard: total users, lots, slots, bookings, active bookings, occupancy %, homeoffice today, bookings today
- Booking heatmap (day of week × hour, DB-agnostic: SQLite `strftime` / MySQL `DAYOFWEEK`)
- Booking reports (by day, status, booking_type, average duration)
- Dashboard chart data (booking trend, current occupancy)
- Paginated, searchable, filterable audit log
- Announcements CRUD (info / warning / error / success, expiry support)
- User management: list, update (name, email, role, is_active, department, password), delete
- Bulk user import (up to 500 users via JSON, skips existing)
- CSV export of all bookings
- Application settings (company name, use case, registration mode, licence plate mode, guest bookings, auto-release, branding colors)
- Email settings (SMTP, from address — stored in settings table, used to update `.env` equivalent)
- Auto-release settings (enabled/disabled, timeout minutes)
- Webhook settings (admin-wide webhook list)
- Branding: company name, primary color, logo upload (2 MB max), default SVG logo fallback
- Privacy / GDPR settings (policy text, retention days, gdpr_enabled flag)
- Impressum editor (all DDG §5 fields: provider name, legal form, address, email, phone, register, VAT ID, responsible person, custom text)
- Database reset endpoint (requires `confirm: "RESET"`, deletes all data except calling admin)
- Slot update and lot delete admin variants

**GDPR**
- Art. 20 data export: full JSON of user's profile, bookings, absences, vehicles, preferences
- Art. 17 anonymization: strips all PII, keeps booking records with `[GELÖSCHT]` plate, sets account inactive
- Audit log entry for GDPR erasure (stores anonymized ID and reason, not original PII)
- Legal templates: `legal/impressum-template.md`, `legal/datenschutz-template.md`, `legal/agb-template.md`, `legal/avv-template.md`

**Public / unauthenticated endpoints**
- Real-time lot occupancy (for lobby displays)
- Active announcements
- Public branding (company name, colors, logo)
- Public Impressum (DDG §5 — legally required to be unauthenticated)
- Health endpoints: `/health/live`, `/health/ready`
- System version and maintenance status

**Database**
- 18 tables: users, parking_lots, zones, parking_slots, bookings, vehicles, absences, settings, audit_log, announcements, notifications_custom, favorites, recurring_bookings, guest_bookings, booking_notes, push_subscriptions, webhooks, waitlist_entries
- UUID primary keys on all domain tables
- Indexes on high-query columns (slot/time overlap, user/status, action/created_at)
- Foreign key constraints with appropriate cascade / set-null behaviour

**Email notifications**
- `WelcomeEmail` Mailable: sent on registration (queued)
- `BookingConfirmation` Mailable: sent on booking creation (queued)
- Queue driver: `database` by default; falls back gracefully if no worker is running

**Deployment**
- `Dockerfile`: multi-stage build (Node 20 frontend + PHP 8.3 Apache backend)
- `docker-compose.yml`: app + MySQL 8 with health check and named volume
- `docker-entrypoint.sh`: auto-migrate, create default admin, cache config/routes
- `deploy-shared-hosting.sh`: builds a zip for shared hosting upload
- `docs/DOCKER.md`, `docs/VPS.md`, `docs/SHARED-HOSTING.md`, `docs/PAAS.md`
- `shell.nix`: Nix development environment

**Frontend pages (React 19 + TypeScript + Tailwind)**
- Login, Register, ForgotPassword, Setup
- Dashboard, Book, Bookings, Calendar
- Vehicles, Team, Absences, Homeoffice, Vacation
- Profile, Admin, AdminBranding, AdminImpress, AdminPrivacy, AuditLog
- Impressum, Privacy, Terms, Legal, Help, About, Welcome
- OccupancyDisplay (unauthenticated lobby display)

**API compatibility**
- `/api/v1/*` routes mirror the ParkHub Docker (Rust edition) endpoint structure
- Legacy `/api/*` routes for backwards compatibility
- `GET /api/v1/admin/updates/check` stub (returns `update_available: false`)

**Demo data**
- `ProductionSimulationSeeder`: 10 German parking lots (München, Stuttgart, Köln, Frankfurt, Hamburg, Nürnberg, Karlsruhe, Heidelberg, Dortmund, Leipzig), 200 German-named users across 10 departments, ~3500 bookings over 30 days
