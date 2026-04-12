# Changelog

All notable changes to ParkHub PHP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [4.5.0] - 2026-03-25

### Added
- **Mobile Booking**: 3 endpoints + tests
- **Notification Center**: 4 endpoints with 8 notification types and enriched metadata
- Integration test suite (10 suites)
- 1-month booking simulation engine (3 profiles)
- k6 load test profiles (small/campus/enterprise)
- axe-core WCAG2aa accessibility testing
- Visual regression testing with Playwright
- Lighthouse CI with LCP threshold
- 6 new E2E test suites: multi-language, offline-reconnect, concurrent-users, admin-crud-complete, booking-edge-cases, security-flows

### Changed
- PHPStan static analysis added to CI (advisory)
- CI modernized: actionlint v1.7.12, setup-qemu-action v4
- Dependabot auto-merge workflow added

### Fixed
- Landing page infinite loop (event-driven 401 handling)
- PHP nightly: build:php path corrected
- SMS/WhatsApp notification toggle gating

### Security
- Removed internal references from public repos
- 6 critical security fixes (OAuth, SAML, importBackup, GraphQL, N+1)

---

## [4.4.0] - 2026-03-25

### Added
- **Notification Center module** (4 endpoints)
- **Mobile Booking module**
- Copilot Agent CI/CD integration
- GitHub Audit Kit

### Changed
- Branch cleanup: 32 -> 8 branches
- CI hardened with auto-merge for Copilot PRs

---

## [4.3.0] - 2026-03-23

### Added
- **RBAC (Role-Based Access Control)** (`MODULE_RBAC=true`)
  - `GET /api/v1/admin/roles` -- list all roles (5 built-in + custom)
  - `POST /api/v1/admin/roles` -- create custom role with permissions
  - `PUT /api/v1/admin/roles/{id}` -- update role name/description/permissions
  - `DELETE /api/v1/admin/roles/{id}` -- delete custom role (built-in protected)
  - `GET /api/v1/admin/permissions` -- list all available permissions
  - `GET /api/v1/admin/users/{userId}/roles` -- get user's assigned roles
  - `PUT /api/v1/admin/users/{userId}/roles` -- assign roles to user
  - Built-in roles: super_admin, admin, manager, user, viewer
  - Permissions: manage_users, manage_lots, manage_bookings, view_reports, manage_settings, manage_plugins
  - 10 PHP tests
- **Advanced Audit Export** (`MODULE_AUDIT_EXPORT=true`)
  - `GET /api/v1/admin/audit-log/export/enhanced?format=csv|json|pdf` -- multi-format export
  - Supports date range filtering (`from`, `to`), action filter, user_id filter
  - CSV, JSON (with metadata), and text-based PDF export
  - 8 PHP tests
- **Parking Zones with Pricing Tiers** (`MODULE_PARKING_ZONES=true`)
  - `GET /api/v1/lots/{lotId}/zones/pricing` -- list zones with pricing tiers
  - `POST /api/v1/lots/{lotId}/zones/pricing` -- create zone with tier
  - `PUT /api/v1/admin/zones/{id}/pricing` -- update zone pricing tier
  - `DELETE /api/v1/lots/{lotId}/zones/{id}/pricing` -- reset zone to standard
  - Tier levels: economy (0.8x), standard (1.0x), premium (1.5x), vip (2.0x)
  - Custom multiplier and max capacity support
  - 8 PHP tests
- **Frontend**: AdminRoles page, AdminZones page, enhanced AdminAuditLog with export dialog
- **Admin nav**: Roles & Permissions and Zones tabs added to admin panel
- **i18n**: All 10 locales synced with parkingZones, rbac, and auditLog translations

---

## [4.2.0] - 2026-03-23

### Added
- **SAML/SSO Enterprise Authentication** (`MODULE_SSO=true`)
  - `GET /api/v1/auth/sso/providers` -- list enabled SSO identity providers
  - `GET /api/v1/auth/sso/{provider}/login` -- initiate SAML login (returns redirect URL)
  - `POST /api/v1/auth/sso/{provider}/callback` -- handle SAML assertion callback
  - `PUT /api/v1/admin/sso/{provider}` -- create or update SSO provider (admin)
  - `DELETE /api/v1/admin/sso/{provider}` -- remove SSO provider (admin)
  - 10 PHP tests
- **Webhooks v2** (`MODULE_WEBHOOKS_V2=true`)
  - `GET /api/v1/admin/webhooks-v2` -- list all v2 webhooks
  - `POST /api/v1/admin/webhooks-v2` -- create webhook with HMAC-SHA256 secret
  - `GET /api/v1/admin/webhooks-v2/{id}` -- get single webhook
  - `PUT /api/v1/admin/webhooks-v2/{id}` -- update webhook
  - `DELETE /api/v1/admin/webhooks-v2/{id}` -- delete webhook
  - `POST /api/v1/admin/webhooks-v2/{id}/test` -- send test event
  - `GET /api/v1/admin/webhooks-v2/{id}/deliveries` -- delivery history
  - 10 PHP tests
- **Enhanced PWA** (`MODULE_ENHANCED_PWA=true`)
  - `GET /api/v1/pwa/manifest` -- dynamic PWA manifest based on branding settings
  - `GET /api/v1/pwa/offline-data` -- essential data for offline mode (next booking, lot info)
  - Offline indicator, cached booking display, bottom nav bar, pull-to-refresh
  - 6 PHP tests
- **Frontend**: SSOButtons component, AdminSSO page, AdminWebhooks page, PWAEnhanced components
- **Admin nav**: SSO and Webhooks tabs added to admin panel
- **i18n**: All 10 locales synced with sso, webhooksV2, and pwa translations

---

## [4.1.0] - 2026-03-23

### Added
- **Booking Sharing & Guest Invites** (`MODULE_SHARING=true`)
  - `POST /api/v1/bookings/{id}/share` -- generate shareable link with configurable expiry
  - `GET /api/v1/shared/{code}` -- public view (no auth required)
  - `POST /api/v1/bookings/{id}/invite` -- invite guest via email
  - `DELETE /api/v1/bookings/{id}/share` -- revoke share link
  - 7 PHP tests
- **Scheduled Reports** (`MODULE_SCHEDULED_REPORTS=true`)
  - `GET /api/v1/admin/reports/schedules` -- list all report schedules
  - `POST /api/v1/admin/reports/schedules` -- create schedule (daily/weekly/monthly)
  - `GET /api/v1/admin/reports/schedules/{id}` -- show single schedule
  - `PUT /api/v1/admin/reports/schedules/{id}` -- update schedule
  - `DELETE /api/v1/admin/reports/schedules/{id}` -- delete schedule
  - `POST /api/v1/admin/reports/schedules/{id}/send-now` -- trigger immediate delivery
  - 7 PHP tests
- **API Versioning** (`MODULE_API_VERSIONING=true`)
  - `GET /api/v1/version` -- version info, deprecation notices, supported versions
  - `GET /api/v1/changelog` -- parsed changelog entries
  - `X-API-Version` response header middleware
  - `X-API-Version-Warning` header for unsupported client versions
  - 4 PHP tests
- **Frontend**: BookingSharing modal, AdminScheduledReports page, ApiVersion badge & admin panel
- **i18n**: All 10 locales synced with sharing, scheduledReports, and apiVersion translations

---

## [4.0.0] - 2026-03-23

### Added
- **Plugin/Extension System** (`MODULE_PLUGINS=true`)
  - `GET /api/v1/admin/plugins` -- list all plugins with status
  - `PUT /api/v1/admin/plugins/{id}/toggle` -- enable/disable plugin
  - `GET /api/v1/admin/plugins/{id}/config` -- get plugin configuration
  - `PUT /api/v1/admin/plugins/{id}/config` -- update plugin configuration
  - Plugin registry with event hooks: `booking_created`, `booking_cancelled`, `user_registered`, `lot_full`
  - 2 built-in plugins: "Slack Notifier", "Auto-Assign Preferred Spot"
  - 9 PHP tests
- **GraphQL API** (`MODULE_GRAPHQL=true`)
  - `POST /api/v1/graphql` -- basic GraphQL query parser mapped to REST handlers
  - `GET /api/v1/graphql/playground` -- GraphiQL interactive playground
  - Queries: `me`, `lots`, `lot(id)`, `bookings`, `booking(id)`, `myVehicles`
  - Mutations: `createBooking`, `cancelBooking`, `addVehicle`
  - 7 PHP tests
- **Compliance Reports** (`MODULE_COMPLIANCE=true`)
  - `GET /api/v1/admin/compliance/report` -- GDPR/DSGVO compliance status with 10 checks
  - `GET /api/v1/admin/compliance/data-map` -- Art. 30 data processing inventory
  - `GET /api/v1/admin/compliance/audit-export` -- audit trail export (JSON/CSV)
  - TOM summary, legal basis, retention periods, sub-processor tracking
  - 8 PHP tests
- **Frontend**: AdminCompliance view, AdminGraphQL test, compliance admin nav tab
- **i18n**: All 10 locales synced with compliance and plugin translations

---

## [3.9.0] - 2026-03-23

### Added
- **Kubernetes Helm Chart**: Production-ready Helm chart for deploying ParkHub PHP to Kubernetes
  - Full `helm/parkhub/` chart with deployment, service, ingress, HPA, PVC, configmap, secret templates
  - Laravel-specific configuration: APP_KEY, DB_CONNECTION, Redis, cache/queue/session drivers
  - Apache port 80 (default), `www-data` security context, 512Mi memory limit
  - All 52 module feature flags exposed via `values.yaml`
  - MySQL and Redis service dependency configuration
  - Health check probes at `/health` and `/health/ready`
  - `helm/README.md` with Laravel-specific installation and migration instructions
- **k6 Load Testing Scripts**: Performance testing suite at `tests/load/`
  - `smoke.js` -- 1 VU, 30s sanity check (health, login, bookings)
  - `load.js` -- 50 VUs, 5min sustained load with full booking lifecycle
  - `stress.js` -- 100 VUs, 10min stress test hitting all major endpoints
  - `spike.js` -- 200 VUs spike test for traffic surge resilience
  - `config.js` -- shared configuration with environment variable overrides
  - Default base URL `http://localhost:8082` (PHP edition)
- **Postman Collection**: Complete API collection at `docs/postman/`
  - `ParkHub.postman_collection.json` -- full API surface with auth, bookings, lots, admin endpoints
  - `ParkHub.postman_environment.json` -- local environment preset (port 8082)
  - Auto-extracts Bearer token from login response
  - Interactive API docs also available via Scramble at `/docs/api`

---

## [3.8.0] - 2026-03-23

### Added
- **Absence Approval Workflows**: Submit, review, approve/reject absence requests
  - `POST /api/v1/absences/requests` -- submit request (status=pending)
  - `GET /api/v1/absences/my` -- user's request history with status
  - `GET /api/v1/admin/absences/pending` -- list pending requests (admin)
  - `PUT /api/v1/admin/absences/{id}/approve` -- approve with comment (admin)
  - `PUT /api/v1/admin/absences/{id}/reject` -- reject with reason (admin)
  - AbsenceApproval.tsx frontend with submit form, my requests, admin pending queue
  - `MODULE_ABSENCE_APPROVAL=true` toggle (50th module)
  - 9 PHP tests + 7 vitest tests
- **Calendar Drag-to-Reschedule**: Drag bookings to new dates with conflict check
  - `PUT /api/v1/bookings/{id}/reschedule` -- reschedule with conflict detection
  - Updated Calendar.tsx with drag-and-drop, confirmation dialog, help tooltip
  - `MODULE_CALENDAR_DRAG=true` toggle (51st module)
  - 6 PHP tests
- **Customizable Admin Dashboard Widgets**: Configurable widget grid for admins
  - `GET /api/v1/admin/widgets` -- get widget layout
  - `PUT /api/v1/admin/widgets` -- save widget layout
  - `GET /api/v1/admin/widgets/data/{widget_id}` -- widget data (8 types)
  - Widget types: occupancy_chart, revenue_summary, recent_bookings, user_growth, booking_heatmap, active_alerts, maintenance_status, ev_charging_status
  - AdminDashboard.tsx frontend with catalog, toggle, grid display
  - `MODULE_WIDGETS=true` toggle (52nd module)
  - 6 PHP tests + 8 vitest tests
- **Frontend sync from parkhub-rust v3.8.0**: AbsenceApproval.tsx, AdminDashboard.tsx, updated Calendar.tsx (drag support), App.tsx (routes), Layout.tsx (nav), Admin.tsx (widgets tab), api/client.ts (absence approval + reschedule + widget endpoints + types), all 10 i18n locale files with absenceApproval/calendarDrag/widgets translations

### Changed
- Module count: 52 modules (added `absence_approval`, `calendar_drag`, `widgets`)
- Migration adds `reviewer_comment` column to absences table

---

## [3.7.0] - 2026-03-23

### Added
- **Enhanced Waitlist with Notifications**: Priority-based waitlist with offer/accept/decline workflow
  - `POST /api/v1/lots/{id}/waitlist/subscribe` -- join with priority (1-5)
  - `GET /api/v1/lots/{id}/waitlist` -- view position + estimated wait time
  - `DELETE /api/v1/lots/{id}/waitlist` -- leave waitlist
  - `POST /api/v1/lots/{id}/waitlist/{entry}/accept` -- accept offered slot (auto-creates booking)
  - `POST /api/v1/lots/{id}/waitlist/{entry}/decline` -- decline, auto-promotes next in queue
  - Waitlist.tsx frontend with status badges, position tracking, accept/decline actions
  - `MODULE_WAITLIST_EXT=true` toggle (47th module)
  - 10 PHP tests + 7 vitest tests
- **Digital Parking Pass / QR Badge**: Generate and verify digital parking passes
  - `GET /api/v1/bookings/{id}/pass` -- generate digital pass with QR data
  - `GET /api/v1/pass/verify/{code}` -- public verification endpoint
  - `GET /api/v1/me/passes` -- list all active passes
  - ParkingPassView.tsx frontend with full-screen pass display and QR code
  - `MODULE_PARKING_PASS=true` toggle (48th module)
  - 8 PHP tests + 7 vitest tests
- **Interactive API Documentation**: Scramble-powered /docs/api endpoint
  - Admin sidebar link to API docs
  - `MODULE_API_DOCS=true` toggle (49th module)
  - 3 PHP tests + 3 vitest tests
- **Frontend sync from parkhub-rust v3.7.0**: Waitlist.tsx, ParkingPassView.tsx, ApiDocs.test.tsx, updated App.tsx (waitlist/passes routes), Layout.tsx (waitlist/passes nav), Admin.tsx (API docs tab), api/client.ts (waitlist + pass endpoints + types), all 10 i18n locale files with waitlist/pass/apiDocs translations

### Changed
- Module count: 49 modules (added `waitlist_ext`, `parking_pass`, `api_docs`)

---

## [3.6.0] - 2026-03-22

### Added
- **Parking History**: Paginated booking history with statistics dashboard
  - `GET /api/v1/bookings/history` -- paginated past bookings with lot/date filters
  - `GET /api/v1/bookings/stats` -- personal stats: total bookings, favorite lot, avg duration, monthly trend, busiest day, credits spent
  - ParkingHistory.tsx frontend with stats cards, monthly trend chart, filters, timeline, pagination
  - `MODULE_HISTORY=true` toggle (45th module)
  - 10 PHP tests + 6 vitest tests
- **Geofencing**: GPS-based auto check-in with haversine distance calculation
  - `POST /api/v1/geofence/check-in` -- auto check-in when within lot geofence radius
  - `GET /api/v1/lots/{id}/geofence` -- get geofence config (center coords, radius, enabled)
  - `PUT /api/v1/admin/lots/{id}/geofence` -- admin set geofence center and radius
  - Profile.tsx updated with geofence auto check-in toggle
  - `MODULE_GEOFENCE=true` toggle (46th module)
  - 10 PHP tests + 4 vitest tests
- **Frontend sync from parkhub-rust**: ParkingHistory.tsx, Geofence.test.tsx, updated Profile.tsx (geofence toggle), App.tsx (history route), Layout.tsx (history nav item), Admin.tsx (unchanged), api/client.ts (history + geofence endpoints), all 10 i18n locale files with history/geofence translations
- Migration adds `center_lat`, `center_lng`, `geofence_radius_m` to parking_lots table

### Changed
- Module count: 46 modules (added `history`, `geofence`)
- README badges updated to v3.6.0, test count 1670+

---

## [3.5.0] - 2026-03-22

### Added
- **Visitor Pre-Registration**: Register visitors with QR code pass for easy check-in
  - `POST /api/v1/visitors/register` -- register a visitor with name, email, vehicle plate, visit date
  - `GET /api/v1/visitors` -- list current user's visitors
  - `GET /api/v1/admin/visitors` -- admin view all visitors with search and status filter
  - `PUT /api/v1/visitors/{id}/check-in` -- check in a pending visitor
  - `DELETE /api/v1/visitors/{id}` -- cancel a visitor registration
  - Visitors.tsx + AdminVisitorsPage frontend with search, QR modal, admin stats
  - `MODULE_VISITORS=true` toggle (42nd module)
  - 10 PHP tests + 6 vitest tests
- **EV Charging Management**: Start/stop EV charging sessions with charger management
  - `GET /api/v1/lots/{id}/chargers` -- list chargers for a lot
  - `POST /api/v1/chargers/{id}/start` -- start a charging session
  - `POST /api/v1/chargers/{id}/stop` -- stop a charging session with kWh calculation
  - `GET /api/v1/chargers/sessions` -- list user's charging session history
  - `GET /api/v1/admin/chargers` -- admin charger utilization stats
  - `POST /api/v1/admin/chargers` -- admin create new charger
  - EVCharging.tsx + AdminChargersPage frontend with connector types, session history
  - `MODULE_EV_CHARGING=true` toggle (43rd module)
  - 10 PHP tests + 5 vitest tests
- **Smart Recommendations (Enhanced)**: Weighted scoring algorithm for parking slot suggestions
  - Scoring: frequency 40%, availability 30%, price 20%, distance 10%
  - `GET /api/v1/recommendations/stats` -- admin acceptance rate with algorithm weights
  - Book.tsx updated with RecommendationsSection showing badge-based suggestions
  - `MODULE_RECOMMENDATIONS=true` toggle (44th module)
  - 8 PHP tests (extended)
- **Frontend sync from parkhub-rust**: Visitors.tsx, EVCharging.tsx, updated Book.tsx (recommendations section), Admin.tsx (2 new tabs), App.tsx (4 new routes), all 10 i18n locale files with visitors/evCharging/recommendations translations
- Migration creates `visitors`, `ev_chargers`, `charging_sessions` tables

### Changed
- Module count: 44 modules (added `visitors`, `ev_charging`, `recommendations`)
- RecommendationController rewritten with weighted scoring algorithm and reason_badges
- README badges updated to v3.5.0, test count 1580+

---

## [3.4.0] - 2026-03-22

### Added
- **Accessible Parking**: Manage accessible slots and priority booking for users with disabilities
  - `GET /api/v1/lots/{id}/slots/accessible` -- list accessible slots for a lot
  - `PUT /api/v1/admin/lots/{id}/slots/{slot}/accessible` -- toggle slot accessibility
  - `GET /api/v1/bookings/accessible-stats` -- accessible parking statistics
  - `PUT /api/v1/users/me/accessibility-needs` -- update user accessibility needs
  - AdminAccessible.tsx frontend with stats cards, lot selector, slot toggles
  - Profile.tsx updated with accessibility needs selector
  - `MODULE_ACCESSIBLE=true` toggle (39th module)
  - 9 PHP tests + 6 vitest tests
- **Maintenance Scheduling**: Schedule and manage maintenance windows for parking lots
  - `POST/GET/PUT/DELETE /api/v1/admin/maintenance` -- full CRUD for maintenance windows
  - `GET /api/v1/maintenance/active` -- public active maintenance list
  - Booking overlap validation prevents conflicts
  - AdminMaintenance.tsx frontend with form, calendar view, active banner
  - `MODULE_MAINTENANCE=true` toggle (40th module)
  - 9 PHP tests + 6 vitest tests
- **Cost Center Billing**: Billing analytics by cost center and department
  - `GET /api/v1/admin/billing/by-cost-center` -- breakdown by cost center
  - `GET /api/v1/admin/billing/by-department` -- breakdown by department
  - `GET /api/v1/admin/billing/export` -- CSV export
  - `POST /api/v1/admin/billing/allocate` -- assign cost centers to users
  - AdminBilling.tsx frontend with tab switcher, summary cards, table, CSV export
  - `MODULE_COST_CENTER=true` toggle (41st module)
  - 8 PHP tests + 6 vitest tests
- **Frontend sync from parkhub-rust**: AdminAccessible, AdminMaintenance, AdminBilling views + tests, updated Book.tsx (wheelchair icons), Profile.tsx (accessibility needs), Admin.tsx (3 new tabs), App.tsx (3 new routes), Layout.tsx, api/client.ts, all 10 i18n locale files
- Migration adds `is_accessible` to parking_slots, `accessibility_needs` + `cost_center` to users, creates `maintenance_windows` table

### Changed
- Module count: 41 modules (added `accessible`, `maintenance`, `cost_center`)
- README badges updated to v3.4.0, test count 1553+

---

## [3.3.0] - 2026-03-22

### Added
- **Audit Log**: Full paginated audit trail with filters and CSV export
  - `GET /api/v1/admin/audit-log` -- paginated, filterable by action/user/date
  - `GET /api/v1/admin/audit-log/export` -- CSV export with same filters
  - AdminAuditLog.tsx frontend with color-coded action badges, pagination
  - `MODULE_AUDIT_LOG=true` toggle (36th module)
  - 8 PHP tests + 6 vitest tests
- **Data Import/Export**: Bulk data management for users, lots, and bookings
  - `POST /api/v1/admin/import/users` -- import users from CSV/JSON
  - `POST /api/v1/admin/import/lots` -- import parking lots from CSV/JSON
  - `GET /api/v1/admin/data/export/users` -- CSV export of all users
  - `GET /api/v1/admin/data/export/lots` -- CSV export of all lots
  - `GET /api/v1/admin/data/export/bookings` -- CSV export with date range filter
  - AdminDataManagement.tsx frontend with drag-drop import, preview, export cards
  - `MODULE_DATA_IMPORT=true` toggle (37th module)
  - 9 PHP tests + 6 vitest tests
- **Fleet Management**: Cross-user vehicle overview with stats and flagging
  - `GET /api/v1/admin/fleet` -- list all vehicles with search/type filter
  - `GET /api/v1/admin/fleet/stats` -- fleet statistics (types, electric ratio, flagged)
  - `PUT /api/v1/admin/fleet/{id}/flag` -- flag/unflag vehicles
  - AdminFleet.tsx frontend with stats cards, type distribution, flag controls
  - `MODULE_FLEET=true` toggle (38th module)
  - 9 PHP tests + 6 vitest tests
- **Frontend sync from parkhub-rust**: AdminAuditLog, AdminDataManagement, AdminFleet views + tests, Admin.tsx (3 new tabs), App.tsx (3 new routes), api client (audit/import/export/fleet methods), all 10 locale files updated
- Migration adds `event_type`, `target_type`, `target_id` to audit_log; `vehicle_type`, `license_plate`, `flagged`, `flag_reason` to vehicles

### Changed
- Module count: 38 modules (added `audit_log`, `data_import`, `fleet`)
- README badges updated to v3.3.0

---

## [3.2.0] - 2026-03-22

### Added
- **iCal Calendar Subscriptions**: Subscribe to parking calendar from any calendar app
  - `GET /api/v1/calendar/ical` — authenticated iCal feed
  - `GET /api/v1/calendar/ical/{token}` — public feed via token (no auth)
  - `POST /api/v1/calendar/token` — generate/regenerate subscription token
  - Subscribe button and modal in Calendar.tsx with copy-to-clipboard
  - `MODULE_ICAL=true` toggle (33rd module)
  - 8 PHP tests
- **Rate Limit Dashboard**: Real-time rate limit monitoring for admins
  - `GET /api/v1/admin/rate-limits` — per-group stats (auth/api/public/webhook)
  - `GET /api/v1/admin/rate-limits/history` — 24h hourly blocked request bins
  - AdminRateLimits.tsx frontend with group cards and blocked chart
  - `MODULE_RATE_DASHBOARD=true` toggle (34th module)
  - 9 PHP tests + 5 vitest tests
- **Multi-Tenant**: Tenant isolation with scoping middleware
  - `GET /api/v1/admin/tenants` — list tenants with user/lot counts
  - `POST /api/v1/admin/tenants` — create tenant with branding
  - `PUT /api/v1/admin/tenants/{id}` — update tenant
  - Tenant model with relationships, `tenant_id` on users/lots/bookings
  - AdminTenants.tsx frontend with create/edit modal
  - `MODULE_MULTI_TENANT=true` toggle (35th module)
  - 10 PHP tests + 5 vitest tests
- **Frontend sync from parkhub-rust**: AdminRateLimits, AdminTenants, Calendar (subscribe), Admin (new tabs), App.tsx (new routes), Layout, api client (rate-limits/tenants/calendar-token), all 10 locale files updated
- 508 vitest + 998 PHPUnit = **1506 tests** total

### Changed
- Module count: 35 modules (added `ical`, `rate_dashboard`, `multi_tenant`)
- README badges updated to v3.2.0
- Migration adds `tenants` table, `ical_token` and `tenant_id` to users, `tenant_id` to parking_lots and bookings

---

## [3.1.0] - 2026-03-22

### Added
- **Map View**: Interactive Leaflet parking lot map with color-coded availability markers
  - `GET /api/v1/lots/map` — public endpoint returning lots with lat/lng and availability
  - `PUT /api/v1/admin/lots/{id}/location` — admin endpoint to set lot coordinates
  - `MODULE_MAP=true` toggle (31st module)
  - MapView.tsx frontend with legend, auto-fit bounds, and styled markers
  - 9 PHP tests + 6 vitest tests
- **Web Push Notifications**: Full VAPID-based browser push subscription management
  - `POST /api/v1/push/subscribe` — store push subscription (auth required)
  - `DELETE /api/v1/push/unsubscribe` — remove subscriptions (auth required)
  - `GET /api/v1/push/vapid-key` — return VAPID public key (public)
  - PushController with upsert logic for subscriptions
  - useNotifications hook with permission handling and subscribe/unsubscribe flow
  - Enhanced service worker (sw.js) with push event handling and notification actions
  - `MODULE_WEB_PUSH=true` toggle (32nd module)
  - 8 PHP tests + 4 vitest tests
- **Stripe Payments**: Checkout sessions, webhook handling, and payment history
  - `POST /api/v1/payments/create-checkout` — create Stripe Checkout session (auth required)
  - `POST /api/v1/payments/webhook` — handle Stripe events with signature verification
  - `GET /api/v1/payments/history` — user payment history (auth required)
  - `GET /api/v1/payments/config/status` — check if Stripe is configured (public)
  - Stub mode when Stripe SDK not available, real Stripe when configured
  - `MODULE_STRIPE=true` toggle (default: false, requires API keys)
  - 11 PHP tests
- **Frontend sync from parkhub-rust**: MapView, useNotifications, api client (map/push/stripe methods), App.tsx routes, Layout map nav link, sw.js, all 10 locale files updated
- 498 vitest + 970 PHPUnit = **1468 tests** total

### Changed
- Module count: 32 modules (added `map`, `web_push`)
- README badges updated to v3.1.0
- Stripe module routes now override legacy payment webhook route
- PushController replaces MiscController/PublicController for push endpoints

---

## [3.0.0] - 2026-03-22

### Added
- **10-Language i18n**: Full internationalization with EN, DE, FR, ES, IT, PT, TR, PL, JA, ZH locale files
  - Language selector dropdown in sidebar Layout component
  - i18n tests validate all 10 locales for missing keys and empty string values
- **Admin Analytics module**: `GET /api/v1/admin/analytics/overview` endpoint
  - Daily bookings (30d), revenue by day, peak hours (24 bins), top lots, user growth (12mo), avg duration
  - DB-agnostic: SQLite, PostgreSQL, MySQL support
  - `MODULE_ANALYTICS=true` toggle (30th module)
  - 9 PHP tests for auth, data structure, accuracy, and module toggle
- **AdminAnalytics frontend view**: Analytics dashboard synced from parkhub-rust with route at `/admin/analytics`
- 488 vitest + 942 PHPUnit = **1430 tests** total

### Changed
- Module count: 30 modules (added `analytics`)
- README badges updated to v3.0.0
- Frontend fully synced with parkhub-rust (all locale files, i18n index, Layout, App.tsx, Admin views)

---

## [2.9.0] - 2026-03-22

### Added
- **Lobby Display / Kiosk Mode**: Public `GET /api/v1/lots/{id}/display` endpoint (no auth) for full-screen parking monitors
  - Real-time occupancy with color status (green/yellow/red), floor breakdown, 10 req/min throttle
  - `MODULE_LOBBY_DISPLAY` toggle, `LobbyDisplayController`, route at `/lobby/:lotId`
  - Frontend `LobbyDisplayPage` synced from Rust edition with full-screen dark UI
  - 8 PHP tests, 6 frontend tests, i18n (en/de)
- **Onboarding Wizard**: 4-step guided setup flow via `SetupWizardController`
  - Step 1: Company info + timezone + logo
  - Step 2: Lot creation with auto-generated floors (zones) and slots
  - Step 3: User invitations via email
  - Step 4: Theme selection from 12 themes, marks wizard complete
  - `GET /api/v1/setup/wizard/status` + `POST /api/v1/setup/wizard`
  - Frontend `SetupWizardPage` synced from Rust with progress bar and validation
  - 9 PHP tests, 6 frontend tests, i18n (en/de)

### Changed
- Module count: 29 modules (added `lobby_display`)
- Rate limiter `lobby-display`: 10 requests/min per IP
- App.tsx: Added `/lobby/:lotId` and `/setup` public routes

---

## [2.8.0] - 2026-03-22

### Added
- **SSE Real-Time module**: Server-Sent Events endpoint (`GET /api/v1/sse`) for streaming booking and occupancy events
- **SSE status endpoint**: `GET /api/v1/sse/status` with module info and pending event count
- **PushSseBookingEvent listener**: Automatic cache-queue push on BookingCreated/BookingCancelled events
- **OccupancyChanged event**: Broadcast event for lot availability changes
- **MODULE_REALTIME**: New module toggle (28 modules total)
- **WebSocket hook sync from Rust**: Occupancy map, token auth, maxReconnectDelay, 5 event types
- **Live indicator**: Green dot + "Live" text on Dashboard when WebSocket connected
- **Bookings real-time toasts**: Toast notifications for booking_created/booking_cancelled events
- **Dynamic pricing API**: Client methods for lot pricing endpoints
- **Operating hours API**: Client methods for lot hours endpoints
- 13 new PHP tests (SseRealtimeTest), 14 frontend WebSocket tests, 2 WS indicator tests

### Changed
- Frontend fully synced with parkhub-rust v28 (9 files)
- Dashboard.tsx: occupancy destructuring, live connection indicator
- Bookings.tsx: WebSocket event handler integration
- client.ts: DynamicPricing + OperatingHours types and methods
- AdminLots.tsx, Book.tsx, Profile.tsx, NotificationPreferences.tsx synced

---

## [2.2.0] - 2026-03-22

### Added
- **Glass morphism UI**: Bento grid dashboard with frosted-glass cards, animated counters, and modern gradients
- **2FA/TOTP authentication**: QR code enrollment, backup codes, per-account enable/disable
- **Security improvements**: Login history, session management, API key management, notification preferences
- **CI badges and GitOps polish**: README overhaul, SECURITY.md, issue/PR templates

### Changed
- Bumped version to 2.2.0
- README badges switched to flat-square style with CI status badge
- Added Security link to README navigation

---

## [2.1.0] - 2026-03-22

### Added
- **22 controller modules**: Full module system documentation with controller mapping
- **Bulk admin operations**: Bulk user actions, advanced reports, booking policies
- **Health monitoring**: Enhanced health checks and system status endpoints

### Changed
- Frontend synced with parkhub-rust v2.2.0 (glass morphism, bento grid)

---

## [2.0.0] - 2026-03-22

### Added
- **Full module system**: 22 controller modules documented and organized
- **Smart slot recommendations**: Heuristic scoring engine — top 5 returned
- **Community translation management**: Proposal submission, up/down voting, admin review
- **Runtime translation overrides**: Approved translations hot-loaded at startup
- **Favorites UI**: Pinned parking slots with live availability
- **Dashboard analytics**: 7-day booking activity bar chart
- **DataTable CSV export**: CSV download with proper escaping
- **Demo reset tracking**: Cache-based status tracking with 9 tests

### Changed
- Major version bump to align with Rust edition versioning

### Tests
- **484 PHP (1094 assertions) + 401 Frontend** = 885 total tests

---

## [1.9.0] - 2026-03-21

### Added
- **Community translation management**: Proposal submission, up/down voting, admin review (approve/reject with comments)
- **Runtime translation overrides**: Approved translations hot-loaded into i18n at app startup
- **Smart slot recommendations**: Heuristic scoring engine (slot frequency, lot frequency, features, proximity, base) — top 5 returned
- **Favorites UI**: Full view for managing pinned parking slots with live availability status
- **Dashboard analytics**: 7-day booking activity bar chart with real booking data
- **DataTable CSV export**: Download any data table as CSV with proper cell escaping
- **A11y audit fixes**: ARIA labels, contrast fixes, confirm dialogs replacing window.confirm
- **Demo reset tracking**: Cache-based `last_reset_at`, `next_scheduled_reset`, `reset_in_progress` with 9 tests
- **i18n**: 10 languages with favorites section (150+ keys per locale)

### Changed
- Removed unused `ParkingSlot` import from RecommendationController
- API response format standardized across translation endpoints
- Version bumped to 1.9.0

### Tests
- **484 PHP (1094 assertions) + 401 Frontend** = 885 total tests

---

## [1.6.0] - 2026-03-20

### Added
- **Typed error handling**: Consistent structured error responses across all endpoints
- **Demo reset with DB wipe**: Full database truncate and re-seed on demo reset
- **Auto-reset scheduler (6h)**: Demo mode auto-resets every 6 hours via Laravel scheduler
- **React 19 useActionState**: Form handling migrated to `useActionState` pattern
- **Tailwind CSS 4 @utility**: Custom utilities via `@utility` directives
- **Admin user search**: Search/filter users by name, email, or role in admin panel
- **Rate-limited demo endpoints**: Demo reset and status endpoints are rate-limited

### Tests
- **965 tests total**: 326 PHP + 213 Vitest + 426 Rust (up from 434 in v1.4.8)

---

## [1.4.8] - 2026-03-19

### Design
- **Full UI overhaul**: Eliminated AI slop patterns across all views
- Welcome, Login, Dashboard, Bookings, Profile, Admin — all redesigned
- System font, tight tracking, 12px cards, 8px buttons, solid backgrounds
- Left-aligned layouts, no floating shapes, no icons-in-circles

### Added
- **434 tests**: 150 PHP (376 assertions) + 137 frontend vitest + 147 Rust
- **Maestro E2E**: 5 browser flows
- **Skeleton loading, micro-interactions, animated stats**
- **i18n**: 50+ keys (EN + DE) including nav.team, nav.calendar, nav.notifications
- **Dynamic version from package.json**

### Fixed
- Setup wizard admin role ($fillable missing 'role')
- DemoController wrong config key (test_mode → demo_mode)
- Docker entrypoint .env override for env vars
- GDPR anonymize audit_log table name
- FeaturesContext crash (missing API method)
- 2 Dependabot vulnerabilities (flatted, league/commonmark)

---

## [1.3.7] - 2026-03-19

### Added
- **Vehicle tests**: 9 feature tests covering CRUD, ownership isolation, validation, auth guard
- **Setup tests**: 7 feature tests covering wizard status, admin detection, init flow, validation
- **Health tests**: 5 feature tests covering liveness, readiness, health check, auth-free access
- **Notification tests**: 6 feature tests covering list, ownership, mark-as-read, ordering
- **Frontend Vitest tests**: 33 tests across 3 files (API client, DemoOverlay, Login)
- **i18n keys**: Added `useCase.*` and `features.*` translation keys in English and German
- **Use-case context providers**: `UseCaseProvider` and `FeaturesProvider` wired into App.tsx

### Fixed
- **Setup wizard admin role bug**: `role` was missing from User model `$fillable` — setup wizard created admin with `role=user` instead of `role=admin`. All new installs affected.
- **AdminSettings use-case dropdown**: Options now match backend presets (company, residential, shared, rental, personal)

### Improved
- **Test coverage**: 67 PHP tests (160 assertions), 33 frontend vitest tests, all passing
- **Frontend synced** with Rust edition (identical `parkhub-web/` source)

---

## [1.3.0] - 2026-03-18

### Added
- **Demo auto-reset**: Scheduled auto-reset every 6 hours via Laravel scheduler when `DEMO_MODE=true`
- **Demo status tracking**: `GET /api/v1/demo/status` now returns `last_reset_at`, `next_scheduled_reset`, `reset_in_progress`
- **DemoOverlay countdown**: Frontend shows time since last reset, countdown to next auto-reset, and reset-in-progress indicator

### Fixed
- **GDPR export route**: Fixed broken `/users/me/export` route pointing to `exportData` instead of `export`
- **Swap race condition**: Wrapped slot swap in `DB::transaction()` with `lockForUpdate()` to prevent double-booking
- **Admin pagination**: Added pagination to admin bookings endpoint (prevent DOS via unbounded query)
- **Demo reset error handling**: Returns HTTP 500 on failed reset instead of silently swallowing exception
- **iCal import**: Added date validation and title truncation (prevents 500 on malformed iCal input)
- **Duplicate scheduling**: Removed duplicate `bookings:auto-release` (ran via both command and job every 5 min)

### Improved
- **Reset tracking**: `performReset()` now tracks `last_reset_at`, `next_scheduled_reset`, and `reset_in_progress` via Cache

---

## [1.2.0] - 2026-02-28

### Added
- **Missing admin routes wired**: `/admin/bookings`, `/admin/reports`, `/admin/dashboard-charts`, `/admin/branding`, `/admin/privacy`, `/admin/impressum`, `/admin/database/reset`, `/admin/auto-release`, `/admin/email-settings`, `/admin/users/export-csv`, `/admin/bookings/export-csv`
- **Waitlist routes**: `GET/POST /waitlist`, `DELETE /waitlist/{id}` now wired to WaitlistController
- **Single booking endpoint**: `GET /bookings/{id}` now accessible (was in controller but not in routes)
- **User endpoints**: `GET /user/export`, `GET /user/calendar-export`, `GET /absences/import-ical`, `GET /team/today`
- **Vehicle photo endpoints**: `GET/POST /vehicles/{id}/photo` now accessible
- **Announcements endpoint**: `GET /announcements/active` with proper `expires_at` null-safe filter
- **Queue worker**: New `worker` service in docker-compose.yml for async email/webhook processing
- **Scheduler service**: New `scheduler` service in docker-compose.yml for scheduled jobs
- **SendWebhookJob**: Webhooks are now actually delivered via a queued HTTP job with HMAC-SHA256 signing and 3 retries
- **AutoReleaseBookingsJob**: Scheduled every 5 minutes — auto-cancels bookings with no check-in after timeout
- **ExpandRecurringBookingsJob**: Daily at 01:00 — pre-creates bookings from recurring patterns for the next 7 days
- **Sanctum token pruning**: Expired tokens pruned on container start and daily via scheduler
- **Koyeb deployment**: Added `koyeb.yaml` for one-command Koyeb deployment

### Fixed
- **LIKE injection**: `auditLog()` search now escapes `%`, `_`, `\` characters before interpolation
- **N+1 query**: `PublicController::occupancy()` and `display()` now use single aggregation queries (was N+1 per lot)
- **QUEUE_CONNECTION**: Changed from `sync` to `database` so queued jobs actually queue

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

---

## [1.1.1] — 2026-02-28

### Critical Bug Fixes

- **`/api/v1/health/ready` HTTP 500**: Readiness probe now gracefully falls back to `1.0.0-php` when `VERSION` file is absent in container
- **`/api/v1/announcements/active` HTTP 500**: Removed filter on non-existent `expires_at` column; returns all active announcements
- **`password_confirmation` ignored on registration**: Added `confirmed` validation rule — password and password_confirmation must now match
- **Past booking accepted**: `POST /api/v1/bookings` now rejects `start_time` in the past with HTTP 422 `INVALID_BOOKING_TIME`
- **Profile save lost on refresh**: `Profile.tsx handleSave()` now calls `PUT /api/v1/users/me` — profile changes persist
- **Vehicle creation always failed**: `api/client.ts` was sending `license_plate` but backend requires `plate` — corrected
- **New lots had 0 slots**: `LotController::store()` now auto-generates `total_slots` slot records (001..N) on lot creation

### Security

- **Apache security headers**: `X-Content-Type-Options`, `X-Frame-Options`, `Content-Security-Policy`, `HSTS`, `Referrer-Policy`, `Permissions-Policy` added via `.htaccess`
- **Server/framework version hidden**: `X-Powered-By: PHP` and `Server: Apache` response headers suppressed

### Other Improvements

- Added `GET /api/v1/bookings/{id}` single-booking detail endpoint
- GDPR account deletion response now returns `{"success":true}` instead of `Unauthenticated` error
- CORS restricted from wildcard `*` to specific allowed origins
- Admin users list now paginated (default 20, max 100 per page)
- Login page: removed duplicate theme toggle button
- Router: `/datenschutz` redirects to `/privacy`, `/agb` redirects to `/terms`
- Removed stale `.backup` development files from production image
