# Changelog

All notable changes to ParkHub PHP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---
## [5.0.4] - 2026-05-03

### Added

- Port PNG icons + manifest entries from parkhub-rust (parity) (#421) ([#421](https://github.com/nash87/parkhub-php/pull/421))
- Add CO2 summary endpoint to /api/v1/bookings (#412) ([#412](https://github.com/nash87/parkhub-php/pull/412))


### Build

- Bump the npm-deps group across 1 directory with 16 updates (#404) ([#404](https://github.com/nash87/parkhub-php/pull/404))
- Bump the npm-tooling-deps group across 1 directory with 4 updates (#403) ([#403](https://github.com/nash87/parkhub-php/pull/403))


### CI

- Local make targets for Lighthouse + Infection (audit gaps #2 + #3) (#436) ([#436](https://github.com/nash87/parkhub-php/pull/436))
- Keep visual regression soft green (#432) ([#432](https://github.com/nash87/parkhub-php/pull/432))
- Keep changelog workflow green without bot token (#429) ([#429](https://github.com/nash87/parkhub-php/pull/429))
- Open changelog regeneration PRs (#428) ([#428](https://github.com/nash87/parkhub-php/pull/428))
- Repin git-cliff changelog action (#427) ([#427](https://github.com/nash87/parkhub-php/pull/427))
- Bound external workflow steps (#425) ([#425](https://github.com/nash87/parkhub-php/pull/425))
- Require CD preflight before publish (#420) ([#420](https://github.com/nash87/parkhub-php/pull/420))
- Add cosign-verify workflow for PR/push parity with parkhub-rust (#407) ([#407](https://github.com/nash87/parkhub-php/pull/407))
- Add OSV-Scanner SCA gate (T-2268, Apache-2.0 Bearer alternative) (#409) ([#409](https://github.com/nash87/parkhub-php/pull/409))
- Dormant deploy.yml (render + fly + koyeb, T-2272 Phase B mirror) (#402) ([#402](https://github.com/nash87/parkhub-php/pull/402))


### Chore

- SOTA-2026 local dev kit (mise + just + dprint + typos) (#418) ([#418](https://github.com/nash87/parkhub-php/pull/418))
- Remove tracked Astro build artifacts (#411) ([#411](https://github.com/nash87/parkhub-php/pull/411))
- Drop COSIGN_EXPERIMENTAL=true (no-op since cosign 3.x) (#408) ([#408](https://github.com/nash87/parkhub-php/pull/408))


### Documentation

- Mark README drift no-sync (#424) ([#424](https://github.com/nash87/parkhub-php/pull/424))
- Parkhub-web divergence audit + 8-slice closure plan (#422) ([#422](https://github.com/nash87/parkhub-php/pull/422))


### Fixed

- Release waits for docker-publish before publishing GitHub Release (#435) ([#435](https://github.com/nash87/parkhub-php/pull/435))
- Align local-ci with GHA — phpstan memory + e2e seeder (#434) ([#434](https://github.com/nash87/parkhub-php/pull/434))
- Make release preflight self-contained — install Composer + Node + Playwright (#433) ([#433](https://github.com/nash87/parkhub-php/pull/433))
- Restore PHP modules info alias (#431) ([#431](https://github.com/nash87/parkhub-php/pull/431))
- Allow OpenStreetMap tiles in CSP (#430) ([#430](https://github.com/nash87/parkhub-php/pull/430))
- Align theme_color + remove broken screenshots (parity slices 2+3) (#423) ([#423](https://github.com/nash87/parkhub-php/pull/423))
- Scope SC2016 disable directive correctly (#417) ([#417](https://github.com/nash87/parkhub-php/pull/417))
- Shellcheck SC2016+SC2018+SC2019+SC2034 cleanup on attestation (#416) ([#416](https://github.com/nash87/parkhub-php/pull/416))
- Attestation gate — bump statuses to write + GraphQL fallback (#414) ([#414](https://github.com/nash87/parkhub-php/pull/414))
- Skip helm/ in trivy-fs scan — chart appKey required at deploy time (#413) ([#413](https://github.com/nash87/parkhub-php/pull/413))
- Bump waitFor timeout for deferred theme fetch (post-#406) — green main (#410) ([#410](https://github.com/nash87/parkhub-php/pull/410))
- Drop framer-motion from /welcome, focus-ring utility, defer theme on public routes (#406) ([#406](https://github.com/nash87/parkhub-php/pull/406))
- Wire aria-invalid + aria-describedby on 2FA failure (#405) ([#405](https://github.com/nash87/parkhub-php/pull/405))


### Tests

- Cover admin route load regressions (#426) ([#426](https://github.com/nash87/parkhub-php/pull/426))


## [5.0.3] - 2026-04-29

### Fixed

- Define skip_step in fop-local-ci.sh (was undefined → exit 127 on GHA) (#401) ([#401](https://github.com/nash87/parkhub-php/pull/401))
- Fop-local-ci.sh auto-fallback to direct mode when fop missing (#399) ([#399](https://github.com/nash87/parkhub-php/pull/399))


## [5.0.2] - 2026-04-29

### CI

- SOTA-2026 pipeline + Phase-4c tsc cleanup + zizmor ERROR fixes (#396) ([#396](https://github.com/nash87/parkhub-php/pull/396))


### Fixed

- Hourly_rate.toFixed is not a function on user-created lots (#393) ([#393](https://github.com/nash87/parkhub-php/pull/393))


## [5.0.1] - 2026-04-26

### Added

- Lokal badge + Vorschläge eyebrow + privacy footer (#354) ([#354](https://github.com/nash87/parkhub-php/pull/354))
- Full customization framework — settings + sidebar variants + density + fonts + feature toggles (#353) ([#353](https://github.com/nash87/parkhub-php/pull/353))
- Tier-2 polish — conflict check, iCal, PDF export, undo, filter persist (#348) ([#348](https://github.com/nash87/parkhub-php/pull/348))
- Local-first CI workflow (Lefthook + drift gates + Biome) (#356) ([#356](https://github.com/nash87/parkhub-php/pull/356))
- Tier-1 2026 UX quick-wins (T-1977) (#347) ([#347](https://github.com/nash87/parkhub-php/pull/347))
- Upgrade Analytics bar chart to uPlot canvas (#339) ([#339](https://github.com/nash87/parkhub-php/pull/339))
- Wave 4+5 — final 11 admin screens (26/26 parity) (#337) ([#337](https://github.com/nash87/parkhub-php/pull/337))
- Wave 3 — port 7 Fleet screens (#333) ([#333](https://github.com/nash87/parkhub-php/pull/333))
- Wave 2 — port Buchen/Kalender/Karte/Profil main screens (#331) ([#331](https://github.com/nash87/parkhub-php/pull/331))
- V5 user-core screens (#329) ([#329](https://github.com/nash87/parkhub-php/pull/329))
- V5 follow-up (#328) ([#328](https://github.com/nash87/parkhub-php/pull/328))


### Build

- Bump @tanstack/react-query from 5.100.1 to 5.100.3 in the npm-tooling-deps group (#368) ([#368](https://github.com/nash87/parkhub-php/pull/368))
- Bump the actions group with 5 updates (#363) ([#363](https://github.com/nash87/parkhub-php/pull/363))
- Bump @types/node from 24.10.13 to 25.6.0 (#359) ([#359](https://github.com/nash87/parkhub-php/pull/359))
- Bump tailwindcss from 3.4.19 to 4.2.4 (#361) ([#361](https://github.com/nash87/parkhub-php/pull/361))
- Bump the npm-tooling-deps group with 6 updates (#357) ([#357](https://github.com/nash87/parkhub-php/pull/357))
- Bump postcss from 8.5.9 to 8.5.10 in /parkhub-web (#350) ([#350](https://github.com/nash87/parkhub-php/pull/350))
- Bump postcss from 8.5.8 to 8.5.10 in /resources/js in the npm_and_yarn group across 1 directory (#349) ([#349](https://github.com/nash87/parkhub-php/pull/349))
- Bump library/composer from `b148074` to `dc292c5` (#334) ([#334](https://github.com/nash87/parkhub-php/pull/334))


### CI

- Add typos + zizmor as advisory CI checks (Wave 5c) (#387) ([#387](https://github.com/nash87/parkhub-php/pull/387))
- Pilot fop local-first PR attestation (#385) ([#385](https://github.com/nash87/parkhub-php/pull/385))
- Close silent-pass holes (lint-ts, typecheck-ts, vitest) (#378) ([#378](https://github.com/nash87/parkhub-php/pull/378))
- Dependabot cooldown + tailwind 4.2.3 ignore + align github-actions group with rust (#371) ([#371](https://github.com/nash87/parkhub-php/pull/371))
- Swap trufflehog (AGPL) for gitleaks (MIT) (#365) ([#365](https://github.com/nash87/parkhub-php/pull/365))
- Pin past tailwind 4.2.4 vite regression (#366) ([#366](https://github.com/nash87/parkhub-php/pull/366))
- Add actions language analysis (#364) ([#364](https://github.com/nash87/parkhub-php/pull/364))
- Unblock Render demo deploy (#327) ([#327](https://github.com/nash87/parkhub-php/pull/327))


### Changed

- Mirror useDraftFromActive hook (parkhub-rust parity) (#386) ([#386](https://github.com/nash87/parkhub-php/pull/386))


### Chore

- Bump to 4.15.0 — parkhub-rust 4.15.0 parity catch-up (#384) ([#384](https://github.com/nash87/parkhub-php/pull/384))
- Bump parkhub-web/package.json to 4.14.0 (parity with VERSION) (#383) ([#383](https://github.com/nash87/parkhub-php/pull/383))
- Bump to 4.14.0 — parkhub-rust parity baseline (#381) ([#381](https://github.com/nash87/parkhub-php/pull/381))
- Install @astrojs/check, exclude stories from tsc (Phase 1) (#374) ([#374](https://github.com/nash87/parkhub-php/pull/374))
- Catalog v5 primitives with Storybook 10 + a11y + test-runner (#345) ([#345](https://github.com/nash87/parkhub-php/pull/345))
- Retire PlaceholderV5 + add Playwright mobile-chrome project (#344) ([#344](https://github.com/nash87/parkhub-php/pull/344))


### Documentation

- Replace parkhub-web/README boilerplate with real overview (#373) ([#373](https://github.com/nash87/parkhub-php/pull/373))
- Post-merge-train drift cleanup (#372) ([#372](https://github.com/nash87/parkhub-php/pull/372))
- V5 design showcase in README (#351) ([#351](https://github.com/nash87/parkhub-php/pull/351))


### Fixed

- Wait for lazy UPlotChart canvases (mirrors parkhub-rust) (#376) ([#376](https://github.com/nash87/parkhub-php/pull/376))
- Only re-init draft on activeId change (#370) ([#370](https://github.com/nash87/parkhub-php/pull/370))
- Override uuid to ^14.0.0 (close GHSA-w5hq-g745-h8pq) (#367) ([#367](https://github.com/nash87/parkhub-php/pull/367))
- Remove KI/AI from v5 user-facing strings (#355) ([#355](https://github.com/nash87/parkhub-php/pull/355))
- Lazy-load UPlotChart (mirror #379 fix) (#341) ([#341](https://github.com/nash87/parkhub-php/pull/341))
- Admin-nav screens use admin APIs (mirror rust #376) (#338) ([#338](https://github.com/nash87/parkhub-php/pull/338))
- Mirror rust #374 fleet-screen fixes to PHP Wave 3 (#336) ([#336](https://github.com/nash87/parkhub-php/pull/336))
- Calendar uses from/to params + Buchen guards invalid datetime (#335) ([#335](https://github.com/nash87/parkhub-php/pull/335))
- Check ApiResponse.success across all mutations + queries (#332) ([#332](https://github.com/nash87/parkhub-php/pull/332))


### Performance

- Lazy-load UPlotChart on Analytics to protect LCP budget (#340) ([#340](https://github.com/nash87/parkhub-php/pull/340))


### Tests

- Phase 4c — kill 8 file-level tsc errors with mixed patterns (#382) ([#382](https://github.com/nash87/parkhub-php/pull/382))
- -41 tsc errors in admin/EV test suites (Phase 4b) (#380) ([#380](https://github.com/nash87/parkhub-php/pull/380))
- Kill 37 tsc errors in Visitors+AdminUpdates (Phase 4a) (#379) ([#379](https://github.com/nash87/parkhub-php/pull/379))
- Kill 42 tsc errors via wsAt() helper (Phase 3) (#377) ([#377](https://github.com/nash87/parkhub-php/pull/377))
- Kill 87 tsc errors via firstCall/nthCall helpers (Phase 2) (#375) ([#375](https://github.com/nash87/parkhub-php/pull/375))
- Dashboard/Profil regression guards + PWA OfflineIndicator wire-up (#352) ([#352](https://github.com/nash87/parkhub-php/pull/352))
- Axe-core audit + WCAG 2.1 AA fixes for v5 (T-1974) (#346) ([#346](https://github.com/nash87/parkhub-php/pull/346))
- 100% happy-path + visual coverage for 26 screens (T-1948) (#343) ([#343](https://github.com/nash87/parkhub-php/pull/343))
- Regression guard for uPlot data-ref stability (#342) ([#342](https://github.com/nash87/parkhub-php/pull/342))


### Release

- Cut v5.0.1 (#388) ([#388](https://github.com/nash87/parkhub-php/pull/388))


### Sync

- Cherry-pick Gitea fixes for parity with parkhub-rust (#330) ([#330](https://github.com/nash87/parkhub-php/pull/330))


## [5.0.0] - 2026-04-23

### Added

- ParkHub v5 design system + global ⌘K + 3-step onboarding tour (#326) ([#326](https://github.com/nash87/parkhub-php/pull/326))
- Sync PHP v4 design surfaces (#324) ([#324](https://github.com/nash87/parkhub-php/pull/324))
- Claude.ai/design v3+v4 integration + React 19 refactor (#306) ([#306](https://github.com/nash87/parkhub-php/pull/306))
- Multi-country VAT profiles + EU B2B reverse-charge
- Add Laravel Policies for primary domain models
- Per-module JSON Schema config editor modal
- Per-module JSON Schema config editor
- Runtime enable/disable toggle in ModulesDashboard
- Runtime enable/disable for safe modules + PATCH admin/modules/{name}
- Command Palette (Cmd+K) + Modules Dashboard
- Enrich api/v1/modules endpoint with ModuleInfo metadata
- HTTP timeouts + per-host circuit breaker for outbound calls
- Enforce absolute session lifetime + regenerate on privilege change
- Add ServiceMonitor + PrometheusRule templates
- Add Reporting-Endpoints + NEL + COEP headers
- Scheduled PurgeAuditLogsJob enforces 90-day audit retention
- PSS-restricted hardening default-on
- Dashboard CO₂ KPI tile + Co2Summary API typing
- Feat(multi-tenancy): tenant-filter the EV charging widget aggregate phase 3 continued. `WidgetController::evChargingStatus` runs a
raw `DB::table('parking_slots')` aggregate over every electric slot
in the database. `parking_slots` has no tenant_id column of its own
(tenant ownership lives on the parent `parking_lots` row), so when
the MODULE_MULTI_TENANT flag is on the widget would otherwise leak
cross-tenant EV station counts into an operator's dashboard.

Fix: only when a tenant is currently bound (`TenantScope::currentId`
returns non-null), join the slots query through `parking_lots` and
apply the tenant filter to the parent table via the same
`TenantScope::applyTo($q, 'parking_lots')` helper shipped in f47d17e.
Single-tenant path is unchanged — the helper short-circuits.

Verified: pint --test ok, phpstan ok, php artisan test
--testsuite=Feature → 1258 1258 pass.

Remaining tenant-guard audit before flipping the flag:
MetricsController personal_access_tokens aggregate
(one-Sanctum-token-per-user is 1:1 to a tenanted user, but the raw
DB::table count doesn't enforce that) and StripeController webhook
crediting (tenant-neutral by design — webhook comes from Stripe, not
a user session, so tenant inference requires the stripe_payments
row's user_id → user.tenant_id lookup).
- Feat(multi-tenancy): TenantScope helper for raw DB::table callsites phase 3: Eloquent's global scope covers every model query, but
a handful of controllers reach into `DB::table(...)` directly for
analytics-shaped queries that join + aggregate across tables. Those
paths bypass the `BelongsToTenant` scope by design, so they need to
apply the tenant filter themselves when the feature flag is on.

New helper: `App\Support\TenantScope::applyTo($builder, $qualifier)`.
Takes any query builder + an optional table qualifier, reads the
current tenant id from the container (null when the flag is off or no
tenant is bound), and applies `->where("$qualifier.tenant_id",...)`
conditionally. Callers chain unconditionally — the helper is a no-op
in single-tenant mode.

First callsite: `LobbyDisplayController::buildFloorBreakdown` — counts
occupied slots per zone via a `DB::table('bookings')->join(...)`
aggregate. Wrapped in `TenantScope::applyTo($q, 'bookings')` so
multi-tenant deployments don't leak cross-tenant occupancy counts
into a kiosk display.

The `currentId` accessor is also useful for any future filter-on-
write or filter-in-cache-key paths; the match against getKey property `id` mirrors the shape of the global scope.

Verified: pint --test ok, phpstan analyse ok, php artisan test
--testsuite=Feature → 1258 1258 pass.

fop task phase 3. Remaining DB::table callsites that need the
same treatment before MODULE_MULTI_TENANT is safe to flip: grep
surface shows `UserController::userCalendar` (guest bookings cleanup),
`StripeController::webhook` (users credit grant), `MetricsController`
(personal access tokens count), `WidgetController` (EV slots) — each
needs a look to decide whether a global-by-design query stays global
or grows a tenant scope.
- Feat(multi-tenancy): extend BelongsToTenant scope to User model phase 2 after the admin-controller audit: AdminReportController
+ AdminAnalyticsController both call `User::count` and
`User::where(...)` for system-wide metrics. Under `MODULE_MULTI_TENANT=true`
those would leak cross-tenant user counts back to an operator who is
only supposed to see their own slice.

User already has a `tenant_id` column in `$fillable` (just never gated
at query time). Applying the `BelongsToTenant` trait extends the same
config-flag-gated global scope to User, so admin analytics reports
automatically filter by tenant the moment the flag flips.

No Bookings ParkingLot User raw `DB::table` queries exist in the
Admin* controllers — all access goes through Eloquent, which means the
scope catches everything without a per-controller patch.

Verified: php artisan test --testsuite=Feature → 1258 1258 (no
regression since the flag is still off by default).

fop task phase 2. Remaining work before flipping the flag:
audit `DB::table('users'|'bookings'|'parking_lots')` direct callsites
anywhere outside Admin* (grep shows a handful in
DataImportExportController, StripeController, MetricsController —
those iterate globally on purpose but need a tenant_id WHERE when the
flag is on).
- Feat(multi-tenancy): BelongsToTenant global scope wiring (flag-gated) phase 1 — plumbing only. Adds a Laravel Eloquent global scope
that limits a model's queries to the authenticated user's tenant
when `config('modules.multi_tenant')` is true. In single-tenant mode
(the default build) the scope returns immediately, so this lands as
a pure no-op until the operator flips `MODULE_MULTI_TENANT=true`
intentionally.

Shape:

 * `app/Models/Scopes/BelongsToTenantScope.php` — the Scope class.
 Reads `config('modules.multi_tenant')` and the `current_tenant`
 container binding at query time (not boot time) so toggling the
 flag doesn't need a reboot. Pulls the id via `getKey` when the
 binding is an Eloquent Tenant (the shape set by the existing
 `App\Http\Middleware\TenantScope`), falling back to a public
 `->id` property for stdClass stubs.

 * `app/Models/Concerns/BelongsToTenant.php` — the trait a model
 opts into with `use BelongsToTenant;`. Laravel's
 `bootBelongsToTenant` boot hook registers the scope once per
 model class.

 * Applied to `Booking` and `ParkingLot` — the two models that
 already carry a `tenant_id` column in `$fillable`. User also has
 `tenant_id` but it doesn't need the scope (users are the thing
 that owns a tenant, so scoping them to themselves is a tautology;
 authorisation is handled elsewhere).

 * `tests/Feature/TenantScopeTest.php` — four Pest-style cases that
 exercise both sides of the gate: no-op when the flag is off,
 no-op when no tenant is bound in the container, filter-by-id
 when both conditions are met, and cross-model consistency on
 `Booking`.

Verified: pint --test ok, phpstan analyse ok, php artisan test
--testsuite=Feature → 1258 1258 (the four new tenant tests land
alongside the existing 1254, all green).

fop task phase 1. Phase 2 (audit admin controllers for
queries that bypass Eloquent and hit DB::table directly, making them
escape the scope) is the follow-up before any operator should
consider flipping MODULE_MULTI_TENANT.
- Sync AdminModules + commandRegistry from parkhub-rust


### Build

- Bump astro from 6.0.6 to 6.1.6 in /resources/js in the npm_and_yarn group across 1 directory (#323) ([#323](https://github.com/nash87/parkhub-php/pull/323))
- Bump react-i18next from 16.6.6 to 17.0.4 (#317) ([#317](https://github.com/nash87/parkhub-php/pull/317))
- Bump i18next from 25.10.9 to 26.0.6 (#319) ([#319](https://github.com/nash87/parkhub-php/pull/319))
- Bump react-i18next from 16.6.6 to 17.0.4 in /parkhub-web (#314) ([#314](https://github.com/nash87/parkhub-php/pull/314))
- Bump eslint from 9.39.2 to 10.2.1 (#320) ([#320](https://github.com/nash87/parkhub-php/pull/320))
- Bump i18next from 25.10.10 to 26.0.6 in /parkhub-web (#315) ([#315](https://github.com/nash87/parkhub-php/pull/315))
- Bump typescript from 5.9.3 to 6.0.3 (#318) ([#318](https://github.com/nash87/parkhub-php/pull/318))
- Bump the actions group with 2 updates (#321) ([#321](https://github.com/nash87/parkhub-php/pull/321))
- Bump the npm-tooling-minor-patch group with 8 updates (#316) ([#316](https://github.com/nash87/parkhub-php/pull/316))
- Bump the npm-minor-patch group in /parkhub-web with 2 updates (#313) ([#313](https://github.com/nash87/parkhub-php/pull/313))
- Shrink baseline via model @property + @mixin resources
- Dump-openapi.sh uses.env.example, not local.env


### CI

- Tier-1 workflow cleanup (#304) ([#304](https://github.com/nash87/parkhub-php/pull/304))
- Add helm lint + template validation gate on chart changes
- Promote advisory checks to required now that CI is reliably green
- Loosen gates to achievable-today + keep CWV as aspirational floor
- Create sqlite + migrate before scramble export
- Don't swallow scramble:export stdout
- Add openapi-drift workflow to keep docs/openapi/php.json in sync


### Changed

- Extract AuditLogQueryService
- Extract AdminUserManagementService + WebhookDispatchService
- Extract ModuleConfigurationService + UserAccountService
- Extract AdminSettingsService + ComplianceService
- Extract StripeWebhookService + VehicleService
- Extract 3 services from heaviest controllers
- Split BookingController (1035 → 640 LOC) into focused controllers
- Close — migrate trivial controllers + iCal conditional
- Migrate final inline validate calls to FormRequest
- Migrate 15 more inline validate to FormRequest
- Migrate 15 more inline validate to FormRequest
- Migrate 15 more inline validate to FormRequest
- Migrate 10 more inline validate to FormRequest
- Refactor(validation): migrate UserController inline validate to FormRequest

Extract the 3 remaining inline $request->validate([...]) blocks from
UserController into dedicated App\Http\Requests\*Request classes.
Identical rules; validation still runs on request resolution.

Controller methods migrated:
- updatePreferences -> UpdatePreferencesRequest
- addFavorite -> AddFavoriteRequest
- anonymizeAccount -> AnonymizeAccountRequest (GDPR Art. 17 erasure;
 Hash::check + AuditLog::log + token revocation
 stays in controller body — state mutation is not
 the FormRequest's job)

All three endpoints are the authenticated user acting on their own record
(routes sit behind auth:sanctum; no admin-only variants). authorize is
\$this->user !== null accordingly.

Verification:
- vendor/bin/pint --test: pass
- vendor/bin/phpstan analyse --memory-limit=2G (level 5, baseline-gated): 0 errors
- php artisan test --testsuite=Feature --filter 'User|Profile|Account': 189 passed
- Full Feature suite: 1283 passed (unchanged from baseline)

UserController shrinks 349 -> 335 lines. With this slice now
covers UserController on top of the 10 controllers in 26b15e8 and
BookingController in 9cfa0c6. VehicleController remains as a follow-up.

Refs:
- Migrate VehicleController uploadPhoto to FormRequest
- Refactor(validation): migrate BookingController inline validate to FormRequest

Extract all 7 remaining inline $request->validate([...]) blocks from
BookingController into dedicated App\Http\Requests\*Request classes.
Identical rules; validation still runs on request resolution.

Controller methods migrated:
- index -> IndexBookingsRequest
- guestBooking -> StoreGuestBookingRequest (pre-check for
 allow_guest_bookings setting left in controller body
 to preserve 403 GUEST_BOOKINGS_DISABLED semantic)
- swap -> SwapBookingRequest
- updateNotes -> UpdateBookingNotesRequest (policy authorize stays
 in controller — needs $booking context)
- createSwapRequest -> CreateSwapRequestRequest
- respondSwapRequest -> RespondSwapRequestRequest
- extend -> ExtendBookingRequest

All authorize default to `$this->user !== null` — every route sits
behind auth:sanctum, no admin-only endpoints in this batch.

Verification:
- vendor/bin/pint --test: pass
- vendor/bin/phpstan (level 4, baseline-gated): 0 errors
- php artisan test --filter Booking: 176 passed
- Full Feature suite: 1283 passed (unchanged from baseline)

BookingController shrinks 1062 -> 1035 lines. now covers all
inline validate migrations for the 5 controllers shipped in 26b15e8
plus BookingController here. VehicleController + UserController remain
as separate follow-up slices.

Refs:
- Refactor(validation): migrate 10 inline validate calls to FormRequest classes

Extract inline $request->validate([...]) blocks from 5 mutating controllers
into dedicated App\Http\Requests\*Request classes. No behaviour change —
rules are identical; validation still runs on request resolution.

Controllers migrated:
- AdminCreditController: updateUserQuota, grantCredits, refillAllCredits
- GeofenceController: checkIn, update
- DynamicPricingController: adminUpdate (authorize replaces requireAdmin)
- MaintenanceController: store, update
- TwoFactorController: verify, disable

New FormRequest classes (10):
- UpdateUserQuotaRequest, GrantCreditsRequest, RefillAllCreditsRequest
- GeofenceCheckInRequest, UpdateGeofenceRequest
- UpdateDynamicPricingRequest (admin-only authorize)
- StoreMaintenanceWindowRequest, UpdateMaintenanceWindowRequest
- VerifyTwoFactorRequest, DisableTwoFactorRequest

Feature tests: +8 targeted validation-failure cases in
FormRequestValidationTest. Full Feature suite: 1268 -> 1283 passing.
Pint + PHPStan clean.

Skipped (tracked as separate follow-ups per scope): VehicleController,
BookingController, UserController — too large for a single codemod commit.

Refs:


### Chore

- Update visual baselines + local runner for v4 design system ([#325](https://github.com/nash87/parkhub-php/pull/325))
- Pin primary-language pill to PHP (#303) ([#303](https://github.com/nash87/parkhub-php/pull/303))
- Cosign sign + PDB + topology-spread + Lighthouse CWV gates
- Bump level 4 -> 5 on top of strict_types
- State-of-the-art 2025 local CI mirror + workflow cleanup
- Add lint + ci pre-push script shortcuts
- Raise phpstan level 3 → 4 on top of strict_types
- Raise phpstan level 2 → 3 on top of strict_types
- Raise phpstan level 1 → 2 on top of strict_types
- Raise phpstan level 0 → 1 on top of strict_types
- Add openapi:dump + openapi:drift script shortcuts
- Trufflehog secret scan + document K8s hardening + supply chain
- Pin every GitHub Action to a SHA (v-tag as comment)


### Dependencies

- Phpunit 11 → 13 major upgrade (#310) ([#310](https://github.com/nash87/parkhub-php/pull/310))
- Composer patch bumps (scramble/larastan/sail/tinker) (#309) ([#309](https://github.com/nash87/parkhub-php/pull/309))
- Upgrade Laravel 12.56 -> 13.5 + Symfony 7.4 -> 8.0


### Documentation

- Fix stale steps + missing secrets + broken links across install paths
- Refresh commit-SHA references after history rewrite
- Scrub internal task IDs from external-facing docs
- Fresh v4.13.0 screenshots + modular UX gallery
- Refresh README + ARCHITECTURE for v4.13.0 Modular UX service layer (12 services)
- Document Modular UX platform
- Note PHPStan baseline shrink in v4.13.0
- Cut v4.13.0 for the Modular UX + security/testing cycle
- Regenerate php.json for 14 new FormRequest schemas
- Regenerate php.json snapshot for security/{csp,nel}-report
- Sync README + AGENTS with parkhub-rust sprint shipments
- Regenerate php.json from.env.example state (matches CI dump)
- Mirror diff-openapi.sh prefix-normalisation fix
- Commit PHP OpenAPI spec + dump script for PR-visible drift
- OpenAPI parity methodology + diff script (mirror)
- Add BFSG Accessibility Statement + EU AI Act transparency templates


### Fixed

- Key mapped React fragments in heatmap views (#322) ([#322](https://github.com/nash87/parkhub-php/pull/322))
- Bump parkhub-web package version 4.12.0 → 4.13.0 (#312) ([#312](https://github.com/nash87/parkhub-php/pull/312))
- Clear CodeQL warnings in Settings + AdminModules (mirror) (#307) ([#307](https://github.com/nash87/parkhub-php/pull/307))
- Add top-level event_id to Stripe double-credit replay test
- Regenerate openapi snapshot for AlreadyProcessed webhook response
- Fortlaufende invoice numbers + webhook idempotency
- Drop SVG from branding logo uploads
- Deterministic integration-admin/user usernames
- Enforce tenant scope on admin analytics + CSV exports + rate-limit keys
- Openapi drift regen with.env.example baseline
- Webhook job test DI + openapi drift regen
- Drop openapi:dump from `composer ci` — env-dependent output
- Mirror diff-openapi.sh four-way prefix normalisation
- Graceful shutdown hook for Helm deployment
- Webhook fails closed when STRIPE_WEBHOOK_SECRET missing


### Performance

- Eager-load relations to eliminate N+1 queries


### Tests

- Add Playwright visual regression suite
- Add schemathesis OpenAPI contract fuzzing nightly
- Add infection-php for app/Rules + app/Http/Middleware
- Wait for auth cookie after login to unblock mobile-safari
- Align Dashboard.test with KpiCard migration
- Wire getCo2Summary mock (mirror of parkhub-rust)
- Infection-php config + weekly CI sweep (mirror of parkhub-rust)


### Marathon

- Demo seed routing + a11y labels + visual expand (#302) ([#302](https://github.com/nash87/parkhub-php/pull/302))


### Ops

- Add Fly.io + Railway templates + nightly install smoke test
- Ship default Grafana dashboard (opt-in via values)


### Security

- Close — 171 171 app files on strict_types
- Strict_types on 6 more controllers + haversine float fix
- Declare(strict_types=1) on helpers + ApiKey + ICal controllers
- Declare(strict_types=1) on 32 remaining controllers + Rule + Service
- Declare(strict_types=1) on 31 Eloquent Models
- Declare(strict_types=1) on 10 mid-size controllers + str_pad(int) fix
- Declare(strict_types=1) on all 13 API Resources
- Declare(strict_types=1) on all 5 Mail classes
- Declare(strict_types=1) on Form Requests
- Declare(strict_types=1) on 25 small stateless controllers
- Declare(strict_types=1) on Listeners + Policies
- Declare(strict_types=1) on 19 bootstrap-tier files


## [4.12.0] - 2026-04-16

### Added

- Add locale-coverage script as a drift guard
- Wire sentry-laravel for error tracking (opts in via SENTRY_LARAVEL_DSN)
- Propagate x-request-id into Laravel log context + unlock metrics


### Dependencies

- Sync composer.lock with sentry-laravel addition


### Fixed

- Seed admin bookings + credits so dashboard isn't empty on login


### Performance

- Lazy-load non-English locales to shave ~450KB
- Lazy-load Layout to shrink pre-auth critical path
- Enable Brotli via mod_brotli.htaccess


### Tests

- Cover webhook HMAC tamper + replay rejection paths
- Replace deprecated networkidle waits with domcontentloaded


### Release

- V4.12.0


### Sec

- Add Cross-Origin-{Opener,Resource}-Policy: same-origin


## [4.11.0] - 2026-04-16

### CI

- Ping Render demo every 10 min to prevent spin-down


### Chore

- Chore(web): remove orphaned Vite-era assets from public These files were tracked from the legacy Vite/React build but are no
longer referenced anywhere in the Astro source, PHP controllers, or
Laravel blade templates. Active PWA icons live in public/icons (served
by PWAController), active favicon is public/icon.svg.

Removed:
- vite.svg (Vite default logo)
- apple-touch-icon.png, favicon.png (replaced by favicon.svg)
- icon-192.png, icon-256.png, icon-512.png (replaced by icons/icon-*.png)
- pwa-192x192.svg, pwa-512x512.svg (replaced by icons set)
- Chore(web): untrack Astro build output in public Dockerfile builds parkhub-web and copies dist -> public at container
build time. Tracking build artifacts caused dirty working trees after
local npm run build and staleness between Vite (legacy) and Astro output.

Gitignore now excludes the full Astro output set (_astro/, index.html,
manifest.json, sw.js, offline.html, og-image.svg, favicons). Server
files (.htaccess, index.php, robots.txt, logos/) stay tracked.


### Dependencies

- Bump astro 6.1.5 -> 6.1.7


### Documentation

- Add Astro 6 badge + mention in stack tagline


### Fixed

- Digest-pin all FROM directives for supply-chain hardening
- Route composer dev + root npm dev to Astro, not legacy Vite
- Bump VERSION file to 4.10.0
- Derive cookie Secure flag from request scheme, not APP_ENV


### Performance

- Perf(cache): immutable cache for Astro chunks, short cache for SW/manifest _astro/*.js,css,woff2,... carry a content hash in the filename, so
the URL is effectively immutable — 'public, max-age=31536000,
immutable' lets both browsers and the Cloudflare CDN in front of the
Render demo keep them cached for a year with zero revalidation round
trips on repeat navigations. Previously these chunks came back with
no Cache-Control header at all, which surfaced as 'cf-cache-status:
DYNAMIC' on every request — each page load fetched the same chunks
fresh from origin through Cloudflare.

favicon/manifest.json/sw.js/offline.html are unhashed and therefore
need to update when the source does — 1-hour max-age with must-
revalidate is the state-of-the-art balance for PWA shell assets.
- Instant navigation via prefetch + View Transitions API


### Tests

- Widen cross-env tolerance to 10%


### Release

- V4.11.0


### Sec

- Enable HSTS on the PHP Render demo (parity with rust)


## [4.10.0] - 2026-04-15

### Added

- P0 sidebar regrouping + empty-state onboarding (sync from rust)
- React 19 useOptimistic on cancel (sync from parkhub-rust)
- Transparent token refresh interceptor
- Wire 2FA login + consistent envelopes + refresh endpoint
- Kinetic Observatory dashboard — synced from parkhub-rust


### CI

- Shard E2E across chromium + mobile-chrome + mobile-safari
- Remove continue-on-error masks on integration + PHPStan jobs
- Wire up the env the local suite proved out
- Drop dead multi-arch scaffolding + hardcoded --retries=2
- Build amd64 only (drop arm64 multi-arch)


### Documentation

- Append v4.10.0 session fixes
- Bump to v4.10.0 + changelog for Kinetic Observatory + Render fixes
- Add CODE_OF_CONDUCT and NOTICE for public release
- Accurate third-party license inventory for v4.9.0


### Fixed

- Clean up the full nightly suite
- Rescue sidebar routes that broke on the Render deploys
- Delimiter-less regex pattern crashed middleware on every Origin
- Kill the per-render DDoS on api/v1/demo/status
- Add missing api.getBookingRecommendations + setAccessibilityNeeds
- Dashboard skeleton matches Kinetic layout (sync from rust)
- 3 high-severity bugs + pagination envelope
- Move disable flag to config/app.php for PHPStan
- Add PARKHUB_DISABLE_RATE_LIMITS bypass for E2E
- Run Apache as root to fix var/log/apache2 permission denied
- Bridge --dt-* tokens into --theme-* (synced from parkhub-rust)
- Sync frontend fixes from Rust repo
- PWA icons, notification guard, CSP header, footer landmark
- Disable unsafe SAML callback, encrypt auth cookie
- Admin middleware, metrics auth, CSP nonce, SSRF guard, import roles, session encrypt
- Increase login redirect timeout to 30s for slow CI runners
- Guard optional-chain array ops to prevent crash on null API response
- Drop root privileges via gosu + fix picomatch CVEs
- Add explicit port to render.yaml


### Performance

- Hash demo password once + prefetch slot numbers


### Tests

- Sync health-JSON probe from parkhub-rust
- Final nightly + CI smoke fixes
- Sync remaining spec fixes from parkhub-rust 1cb009e
- Remaining feature tests that asserted old shapes
- Align fixtures with the demo-route fixes
- Set METRICS_TOKEN env var for all test runs
- Sync frontend from parkhub-rust — 2019 tests, 97.56% coverage
- Sync frontend tests from Rust repo — 1683 tests, 91% coverage
- Update tests for security fixes (metrics auth, SSO 501, webhook SSRF)


## [4.9.0] - 2026-04-13

### Added

- Self-update system with version history + rollback
- 4 new premium themes + enhanced animations
- Add Team Leaderboard + Smart Predictions views


### Fixed

- Pint formatting + robust loginViaUi (click demo button)
- Make team/leaderboard E2E test tolerant of feature flags


### Release

- V4.9.0 — API resilience, React 19 useOptimistic, security hardening


## [4.8.0] - 2026-04-13

### Added

- Add QR Check-In, Swap Requests, Guest Pass, Occupancy Heatmap
- Sync translated locale files from parkhub-rust
- CODEOWNERS + SEO meta tags (Open Graph, Twitter Card, JSON-LD)
- Add cutting-edge CSS patterns + DESIGN.md + visual baselines


### Chore

- Bump version badges to v4.8.0


### Dependencies

- Bump the npm-tooling-minor-patch group with 8 updates (#294)


### Documentation

- Add What's New in v4.8.0 + Lighthouse CI on push/PR


### Fixed

- Use getByPlaceholder for password field in loginViaUi
- Fix E2E smoke — modules format, password selector
- Add getInMemoryToken mock + QR ok:true in tests (CI fix)
- Add missing btn base class on GuestPass buttons
- Address all pre-release review findings (7 agents)
- Address pre-release review findings
- Add missing nav.favorites key to all 10 locales
- Use ProductionSimulationSeeder for E2E smoke tests


### Release

- V4.8.0 — QR Check-In, Swap Requests, Guest Pass, Heatmap


## [4.7.0] - 2026-04-12

### CI

- Add security workflow (parity with Rust)
- Add docker-compose.test.yml + integration tests in CI
- Add release workflow + dependabot auto-merge
- Add PHPStan static analysis (advisory, matches Rust clippy pattern)
- Update actionlint v1.7.11→v1.7.12


### Chore

- Bump vite from 7.3.1 to 7.3.2 (#283)
- Bump lodash from 4.17.23 to 4.18.1 (#285)
- Fix dependabot auto-merge (skip major), remove skills-lock.json
- Align VERSION and parkhub-web to 4.5.0


### Dependencies

- Bump the composer-minor-patch group across 1 directory with 6 updates (#289)
- Bump vite from 7.3.1 to 7.3.2 in parkhub-web (#282)
- Bump the actions group across 1 directory with 4 updates (#290)
- Bump the npm-minor-patch group across 1 directory with 10 updates (#288)
- Bump the npm_and_yarn group across 1 directory with 2 updates (#286)
- Bump defu from 6.1.4 to 6.1.7 in parkhub-web (#284)
- Bump smol-toml from 1.6.0 to 1.6.1 in parkhub-web


### Documentation

- Sync GDPR/COMPLIANCE to v3.3.0, align CHANGELOG v4.3.0 date
- Update all MD files to v4.5.0


### Fixed

- Update serialize-javascript 7.0.4→7.0.5 (CVE DoS fix)
- Fix api.spec.ts login payload (email → username field)
- Fix login API payload (email → username field)
- ParkingPassController QR code v6 API migration
- Pint formatting + router.test.tsx require → direct mock
- Fix integration test API contract mismatches
- Fix nightly E2E build path + add retries
- Replace hard 401 redirect with event-based auth clearing to stop landing page loop


### Security

- V4.7.0 — full test pyramid, installer, security fixes


### Tests

- Add frontend Vitest expansion (hooks, validation, router, error boundary)
- Add 6 missing parkhub-web E2E specs (parity with Rust)
- Add 6 E2E Playwright suites (multi-lang, offline, concurrent, admin CRUD, edge cases, security)
- Add k6 load test profiles (small/campus/enterprise)
- Add 1-month booking simulation engine (3 profiles)
- Add 10 integration test suites (110 tests)
- Add a11y, visual regression, and Lighthouse LCP threshold


### Merge

- Fix landing page infinite loop (PR #265)


### Security

- Remove internal references from public repo


## [4.6.0] - 2026-03-27

### CI

- Fix composer validation and helm lint


### Chore

- Add.forge-operator/config.toml for fop integration


### Documentation

- Align readme and contributing with current counts
- Update CONTRIBUTING.md with Sail setup, 67 modules, accurate counts, and good first issues (#244)
- Overhaul README for public audience with badges, screenshots, quick-start (#243)
- Add GitHub issue templates for bug reports and feature requests


### Fixed

- Replace QR placeholder SVG with real chillerlan/php-qrcode
- Gate SMS/WhatsApp notification toggles (synced from Rust)
- Guard theme fetch against non-200 response to prevent retry loop


### Performance

- Add Vite manual chunk splitting for vendor libraries (#259)
- Add compound indexes for booking conflict hot-path queries (#258)
- Add Setting::preload to bulk-fetch settings per request (#256)
- Fix N+1 query in MobileBookingController (#251)


### Security

- Add Laravel Policies for Booking, ParkingLot, and Absence models (#260)
- GraphQL createBooking mutation bypasses all booking policy enforcement (#252)
- Apply allowlist to importBackup to prevent arbitrary setting key injection (#249)


### Tests

- Add Vitest coverage for 5 previously untested view/component files (#240)


### Merge

- Product-truth-cleanup (forge-operator init)


### Quality

- Fix phpstan and frontend typechecks


### Security

- Document missing SAML signature verification and default SSO to off (#250)


### Sync

- Align parkhub-web npm deps with Rust repo + full frontend parity
- Align parkhub-web frontend with Rust repo (100% parity)


## [4.5.0] - 2026-03-25

### Added

- Add admin analytics with occupancy, revenue, popular-lots


### CI

- Add auto-merge workflow for low-risk Copilot PRs
- Stop uploading Trivy SARIF to code-scanning tab
- Exclude locale files from CodeQL analysis (false positive duplicate keys)
- Trigger fresh CI run
- Align php codeql and release workflow
- Fix php workflows and frontend build path
- Make workflow-hygiene, quality, frontend, e2e advisory gates
- Harden and simplify GitHub Actions for deterministic CI and secure releases


### Chore

- Fix MissingAppKeyException in test environment
- Bump laravel/sail from 1.54.0 to 1.55.0 in the composer-minor-patch group (#219)


### Dependencies

- Bump the npm-tooling-minor-patch group with 9 updates (#225)
- Bump the npm-minor-patch group in parkhub-web with 5 updates (#223)
- Bump the actions group with 2 updates (#224)


### Fixed

- Update Docker base images + make Trivy non-blocking
- Resolve picomatch ReDoS and method injection vulnerabilities
- Resolve Lot model table mapping + MobileBooking import consistency


### Tests

- Add feature tests for WebhookV2 delivery history and retry


### Release

- V4.5.0


## [4.4.0] - 2026-03-25

### Added

- Mobile booking module + Copilot test PR #215 merge + module count 66
- Add mobile booking module + docs commit
- Smart notification center with enriched metadata


### Chore

- Add GitHub Audit Kit (Copilot agents, instructions, dependency review)


### Documentation

- Add superpowers design spec for github audit kit


### Fixed

- Address code review feedback - remove unused imports and use portable path
- Sync php v4.3 module routing


### Tests

- Add middleware, jobs, mail, listener tests + 5 model factories
- Add 128 tests for models, events, rules, helpers, and providers (#214)
- Add comprehensive unit tests for 22 untested models, events, rules, helpers, and providers


## [4.3.0] - 2026-03-24

### Added

- Add RBAC, advanced audit export, parking zones with pricing tiers (v4.3.0)


### Chore

- Add aoe repo defaults


### Fixed

- Remove unused fireEvent import from PWAEnhanced test
- Restore waitFor import in PWAEnhanced test
- Resolve CodeQL alerts — unused import + property injection
- Resolve all CI failures from v4.2 feature merge


## [4.2.0] - 2026-03-23

### Added

- Add SAML/SSO, Webhooks v2, Enhanced PWA (v4.2.0)


### Documentation

- Update README for v4.1.0 — 58 modules, expanded features, NIS2 compliance, CI scanning


### Fixed

- Resolve remaining CodeQL alerts (property injection, unused vars)
- Sync CodeQL fixes from Rust — prototype pollution, unused imports


## [4.1.0] - 2026-03-23

### Added

- Add booking sharing, scheduled reports, API versioning (v4.1.0)


### Fixed

- Resolve all CodeQL security scanning alerts
- Bump frontend version to 4.0.0
- Add missing plugins section to EN locale
- Sync icon test mocks from Rust


## [4.0.0] - 2026-03-23

### Added

- Add plugin system, GraphQL API, compliance reports (v4.0.0)


### CI

- Best-in-class security tooling for 2026


### Dependencies

- Bump the actions group with 4 updates (#209)


### Fixed

- Add workflow_dispatch to CodeQL, disable default setup
- Add workflow_dispatch to CodeQL, disable default setup
- Sync PuzzlePiece + AdminPlugins from Rust
- Prevent theme FOUC with inline pre-hydration script
- Skip Trivy SARIF upload when file missing


## [3.9.0] - 2026-03-23

### Added

- Add Helm chart, k6 load tests, Postman collection (v3.9.0)


### Chore

- Bump laravel/sail from 1.53.0 to 1.54.0 in the composer-minor-patch group (#198)


### Dependencies

- Bump the npm-minor-patch group in parkhub-web with 5 updates (#206)
- Bump library/node from 22-slim to 25-slim (#205)
- Bump library/php from 8.4-apache-bookworm to 8.5-apache-bookworm (#204)
- Bump github/codeql-action from 3 to 4 (#203)
- Bump actions/upload-artifact from 4 to 7 (#202)
- Bump actions/cache from 4 to 5 (#201)
- Bump docker/setup-qemu-action from 3 to 4 (#200)
- Bump laravel/tinker from 2.11.1 to 3.0.0 (#199)


## [3.8.0] - 2026-03-22

### Added

- Add absence approval, calendar drag-to-reschedule, admin widgets (v3.8.0)


## [3.7.0] - 2026-03-22

### Added

- Add enhanced waitlist, digital parking pass, API docs (v3.7.0)


## [3.6.0] - 2026-03-22

### Added

- Add parking history, geofencing, frontend sync (v3.6.0)


### Fixed

- BatteryCharging test mock (#194)
- Sync build fixes from Rust (#193)


## [3.5.0] - 2026-03-22

### Added

- Add visitor pre-registration, EV charging, smart recommendations (v3.5.0)


### Fixed

- Update module tests for all-enabled test env (#191)
- Enable all modules in test environment (#190)


## [3.4.0] - 2026-03-22

### Added

- Add accessible parking, maintenance scheduling, and cost center billing (v3.4.0)


## [3.3.0] - 2026-03-22

### Added

- Add v3.3.0 — audit log, data import/export, fleet management


### Changed

- Categorize modules into core/admin/integration/enterprise defaults


### Documentation

- Legal compliance suite + README overhaul for v3.2.0 (#187)


## [3.2.0] - 2026-03-22

### Added

- Add iCal subscriptions, rate limit dashboard, multi-tenant — v3.2.0


## [3.1.0] - 2026-03-22

### Added

- Add map view, web push, and stripe payments — v3.1.0


## [3.0.0] - 2026-03-22

### Added

- Add admin analytics module with overview endpoint
- Sync frontend from parkhub-rust with 10-language i18n and admin analytics


### Fixed

- Update module count tests from 28 to 29 for lobby_display


### Release

- V3.0.0 — 10-language i18n, admin analytics, 1430 tests


## [2.9.0] - 2026-03-22

### Added

- Add onboarding wizard with 4-step setup flow
- Add lobby display kiosk mode endpoint and frontend


### Fixed

- Add permissions block to lighthouse.yml (CodeQL alert #1384)
- Pint fully_qualified_strict_types in SseController


### Release

- V2.9.0 — lobby display + onboarding wizard


## [2.8.0] - 2026-03-22

### Added

- Sync all frontend files from parkhub-rust v28
- Add SSE real-time module with WebSocket hook sync from Rust
- Sync 12 themes from parkhub-rust (was 6)


### Documentation

- Update README badges for v2.8.0 (1365+ tests)
- Update README badges for v2.7.0 (1400+ tests)


### Fixed

- ThemeSwitcher test 6→12 themes
- Sync test mocks from Rust (Palette, getDynamicPrice)
- Remove unused import in OAuthTest


## [2.7.0] - 2026-03-22

### Added

- Port operating hours from parkhub-rust
- Port dynamic pricing from parkhub-rust


### Fixed

- Module count 25→27, frontend test mocks for theme/2FA/notifications


### Tests

- Add full user+admin workflow E2E with 12-theme cycle and booking simulation


## [2.6.0] - 2026-03-22

### Added

- Port PDF invoice improvements from parkhub-rust
- Port OAuth/social login from parkhub-rust


### Documentation

- Update README to v2.5.0 — themes, httpOnly cookies, 23 modules, test counts


### Fixed

- Update module count 22 -> 23 (themes module added)


### Tests

- Add comprehensive Playwright E2E test suite


## [2.5.0] - 2026-03-22

### Fixed

- Replace localStorage JWT with httpOnly cookie auth (#153)


## [2.4.0] - 2026-03-22

### Added

- Port theme switcher system from parkhub-rust


### Fixed

- Pint auto-fix AbsenceController


## [2.3.0] - 2026-03-22

### CI

- Downgrade Lighthouse performance to warn (static SPA shell)


### Chore

- GitOps polish — README, CHANGELOG, SECURITY.md, templates


### Fixed

- Sync accessibility fixes from parkhub-rust + add Lighthouse CI


### Tests

- Add 157 tests for security, modules, admin, form requests, edge cases


## [2.2.0] - 2026-03-22

### Sync

- Frontend from parkhub-rust v2.2.0


## [2.1.0] - 2026-03-22

### Added

- Add frontend security, UX, and admin improvements
- Add QoS, admin features, booking policies, and health improvements
- Add security features — 2FA, password policy, login history, sessions, API keys, notification preferences


### Fixed

- Add missing icon and API mocks in frontend tests


## [2.0.0] - 2026-03-22

### Added

- Discovery endpoint, backup/restore, theme route, CONTRIBUTING.md
- Add ARIA labels, keyboard nav to FAB, loading skeletons to admin views
- Full module system — 22 independently toggleable modules
- Add comprehensive API error handling with consistent response format
- Add OpenAPI documentation via Scramble (closes #53)
- Add Larastan (PHPStan) static analysis with baseline
- Add Stripe payment intent stubs (closes #29)
- Add Laravel Broadcasting infrastructure for real-time events (#27)
- Enhance Prometheus metrics endpoint to full spec (#32)
- Add POST bookings/{id}/extend endpoint (#28)
- Add PostgreSQL support + harden docker-entrypoint


### CI

- Stop spam — remove PostgreSQL/coverage/PHPStan from PRs, lean CI
- Add QEMU setup for arm64 cross-compilation
- Add arm64 platform to Docker build (closes #56)
- Add PostgreSQL 16 service container and test job
- Add PostgreSQL test job to CI pipeline (closes #51)


### Changed

- Extract route closures into controller methods (closes #50)
- Consolidate duplicated SSRF validation into shared trait (closes #81)
- Extract Form Request classes from controllers (closes #57)


### Chore

- Remove accidentally staged files from other branches


### Dependencies

- Bump h3
- Bump h3 from 1.15.6 to 1.15.9 in parkhub-web (#22)


### Documentation

- Add deep dive audit report (2026-03-21)
- Redesign README for professional presentation
- Update README + CHANGELOG for v1.9.0 features


### Fixed

- Use.skeleton class selector (works in jsdom)
- Update spinner tests to match skeleton loading components
- Nonce-based CSP, session cookie hardening, QR local generation
- Validate booking date filters, remove redundant requireAdmin methods
- P2 security batch — token expiry, SMTP encryption, zone auth, branding validation
- Rate-limit payments, verify Stripe webhook signature, fix stale metrics
- Use trivy-action@master (0.28.0 does not exist)
- Remove invalid XML comment from phpunit.xml
- Remove coverage config from phpunit.xml
- Security and config fixes for metrics, VAPID, QR auth, health version
- Align test assertions with ApiResponseWrapper envelope structure
- Update delete tests to use assertSoftDeleted after adding soft deletes to User model
- Soft deletes for User+Booking, batch import N+1
- Rename setup to createTestFixtures — conflicts with TestCase::setUp visibility
- Update PHP requirement to ^8.4, drop 8.2/8.3 from CI matrix, fix Pint style
- Security validation gaps + CI/CD hardening


### Performance

- Chunked credit refill, batched metrics queries, cached webhooks
- Use DB aggregates and cursor streaming for reports and stats
- Add pagination to BookingController + test coverage improvements


### Tests

- Add 18 unit tests for Setting, Zone, Webhook, Absence models
- Add model unit tests, booking conflict tests, auth and validation tests


### Quality

- Security hardening, code review fixes, frontend sync


## [1.9.0] - 2026-03-21

### Added

- Favorites UI — view, nav, i18n for 10 locales
- Demo reset tracking tests, cleanup unused imports
- Recommendations endpoint, a11y audit, analytics charts
- Smart recommendations, typed API, CSV export, runtime i18n overrides
- Translation management system + UI/UX 2026 overhaul


### Chore

- Bump version to v1.9.0, update README badge


### Fixed

- Use fully qualified Docker image names for Podman
- Include devDependencies in Docker web build stage
- Skip Astro font fetch in CI/Docker builds


## [1.8.0] - 2026-03-21

### Added

- Sync frontend — QR pass, CSV export, enhanced payments
- Add lightweight system monitoring dashboard (Pulse)


### Chore

- Bump version to v1.8.0, update README badge


### Fixed

- Pint style fixes + sync frontend from Rust repo
- Add password confirmation to registration form (#21)


## [1.7.1] - 2026-03-20

### Added

- Sync theme polish + PWA offline support from Rust repo


## [1.7.0] - 2026-03-20

### Added

- Sync i18n completeness + OpenAPI docs from Rust repo


## [1.6.1] - 2026-03-20

### Added

- Sync accessibility improvements (17 views, ARIA labels, semantic HTML)


### CI

- Add gate job matching required "CI" status check


### Documentation

- Update README and CHANGELOG for v1.6.0


### Performance

- Sync frontend bundle optimization (627K → 129K main chunk)


### Tests

- Sync 9 Playwright E2E specs from Rust repo
- Sync 101 new Vitest tests (13 views/components, 314 total)
- Expand test suite from 326 to 424 tests


### Sec

- Harden security headers, CORS, and rate limiting


## [1.6.0] - 2026-03-20

### Added

- Sync frontend — React 19 patterns, TW4 @utility, admin search


### Fixed

- Add missing Log facade import in console.php scheduler


### Security

- Rate-limit demo vote/reset endpoints (3/min per IP)


## [1.5.5] - 2026-03-20

### Added

- Sync frontend v1.5.5 — donut chart, admin search, build hash, Calendar/Team perf


### Tests

- 326 PHP tests passing — edge cases, Cache::flush setUp, missing routes (PUT absences/recurring)


## [1.5.4] - 2026-03-20

### Changed

- Shared constants, i18n fixes from code review


### Fixed

- Make Lighthouse CI non-blocking
- Lighthouse CI server startup config


## [1.5.3] - 2026-03-20

### Added

- Command palette, admin charts, Lighthouse CI, 727 total tests


## [1.5.2] - 2026-03-20

### Tests

- 631 total (203 PHP + 190 vitest + 238 Rust)
- 539 total tests (180 PHP + 163 vitest + 196 Rust)


### Design

- Clean up Register + ErrorBoundary


## [1.5.1] - 2026-03-20

### Added

- Add Book a Spot page — fixes #20


## [1.5.0] - 2026-03-20

### Added

- Add 404 Not Found page
- Add Forgot Password page + API methods


### Fixed

- Sync package-lock.json for CI npm ci


## [1.4.9] - 2026-03-19

### Chore

- Fix Dependabot vulns, update README + CHANGELOG to v1.4.8


### Fixed

- Dark mode + mobile touch targets


### Tests

- Add Playwright E2E tests


## [1.4.8] - 2026-03-19

### Fixed

- Add missing nav.team/calendar/notifications i18n keys


## [1.4.7] - 2026-03-19

### Fixed

- Dynamic version from package.json, bump to v1.4.7


## [1.4.6] - 2026-03-19

### Tests

- 434 total tests — full coverage across all layers


### Design

- Apply UI/UX Pro Max design system — system font, tight tracking


## [1.4.5] - 2026-03-19

### Copy

- Replace generic AI marketing copy with specific product description


## [1.4.4] - 2026-03-19

### Design

- Clean up Admin views + refine global CSS


## [1.4.3] - 2026-03-19

### Design

- Full AI slop removal across all views


## [1.4.2] - 2026-03-19

### Added

- 103 PHP tests, 106 vitest, 5 Maestro E2E flows


### Fixed

- Version badge test uses regex instead of hardcoded version


### Design

- Eliminate AI slop from Welcome + Login pages


## [1.4.1] - 2026-03-19

### Chore

- Bump version to v1.4.0, add Maestro E2E tests


### Fixed

- Add id to login submit button for E2E testing


## [1.4.0] - 2026-03-19

### Added

- Micro-interactions, animated stats, empty state polish
- Skeleton loading, i18n coverage, Layout test, UI polish


## [1.3.17] - 2026-03-19

### Fixed

- Remove api.getFeatures/updateFeatures calls (no backend endpoint)


## [1.3.16] - 2026-03-19

### Fixed

- Redirect first-time visitors to welcome language screen


## [1.3.15] - 2026-03-19

### Fixed

- Clear config/route cache before rebuilding in entrypoint


## [1.3.14] - 2026-03-19

### Fixed

- DemoController used wrong config key (test_mode → demo_mode)


## [1.3.13] - 2026-03-19

### Fixed

- Use per-key PUT for Render env vars API


## [1.3.12] - 2026-03-19

### Fixed

- Use Render API GET+merge+PUT for env vars


## [1.3.11] - 2026-03-19

### Fixed

- Normalize DemoStatus API + set Render env vars in deploy


## [1.3.10] - 2026-03-19

### Fixed

- Fix: override.env with Docker env vars in entrypoint.env.example defaults were shadowing Docker env vars like
PARKHUB_ADMIN_PASSWORD and DEMO_MODE because Laravel env
reads.env first. Now the entrypoint patches.env with actual
Docker environment variables before running migrations/seeding.


## [1.3.9] - 2026-03-19

### Fixed

- Use env PARKHUB_ADMIN_PASSWORD in seeder (was hardcoded ParkHub2026!)


## [1.3.8] - 2026-03-19

### CI

- Fix frontend job directory, add vitest, fix Docker npm ci


### Dependencies

- Bump npm minor/patch — astro 6.0.6, framer-motion 12.38, zustand 5.0.12


## [1.3.7] - 2026-03-19

### V1.3.7

- Fix admin role bug, add 27 tests, Vitest, i18n


## [1.3.6] - 2026-03-19

### Added

- Wire UseCaseSelector route + full PWA support


## [1.3.5] - 2026-03-19

### Added

- Wire use-case CSS theme via ThemeLoader component
- Add SEED_DEMO_DATA mode + deployment modes docs


## [1.3.4] - 2026-03-19

### Added

- Use-case CSS theme overrides + fix.test TLD


## [1.3.3] - 2026-03-19

### Fixed

- Change demo credentials to admin@parkhub.demo demo


## [1.3.2] - 2026-03-19

### Added

- Use-case theming system with 5 presets


## [1.3.1] - 2026-03-19

### Added

- Redesign login page — split-screen layout with hero panel


## [1.3.0] - 2026-03-18

### Added

- Complete webhook parity — add PUT/DELETE routes + test endpoint
- DemoOverlay shows reset status, countdown, and resetting indicator
- Demo reset — status tracking + 6h auto-reset scheduler
- Add pricing system, slot features, premium role, Prometheus metrics, full frontend parity
- Real swap requests + web push notifications
- Add webhooks admin UI with CRUD, event filtering, SSRF protection
- Check-in, extend, waitlist, i18n fixes, gitignore assets
- Admin reports, credits, user import/export, password change, health endpoint
- Complete admin panel — settings, slots, announcements, guests, notifications


### CI

- Auto-deploy to Render after Docker image push


### Changed

- Split AdminController into 5 focused controllers


### Chore

- Update build output hashes


### Dependencies

- Bump docker/setup-buildx-action from 3 to 4 (#14)
- Bump docker/login-action from 3 to 4 (#13)


### Documentation

- Add v1.3.0 changelog and live demo section to README


### Fixed

- Update composer.lock after tinker move to require
- Design audit P0 fixes — disabled states, tabular nums, reduced motion
- Resolve GitHub issues #16, #17, #18
- DemoOverlay accessibility and UX improvements
- Remove duplicate auto-release scheduling (ran twice every 5min)
- Code review fixes — routing, safety, pagination
- Rename load to loadData in swap request handlers
- Security hardening, CI lint fixes, WCAG accessibility
- Admin lot creation + credit quota system (closes #15)
- Enforce all admin settings in business logic


### Performance

- Cache Settings, fix N+1 queries, security headers, CSV injection, tests


### Tests

- Update register test password for complexity requirement


### A11y

- Add reduced motion support + improve input focus indicators


### Legal

- Add Art. 28(3)(h) audit rights clause to AVV template


### Release

- V1.3.0 — version bump across all route files


### Security

- Prevent open redirect in install.php post-setup redirect


## [1.2.6] - 2026-03-14

### Fixed

- Demo crash on missing audit_log table


## [1.2.5] - 2026-03-14

### Fixed

- Security hardening — install.php, render.yaml,.env defaults


## [1.2.4] - 2026-03-14

### Added

- Add install.php wizard + fix docs and Docker port


## [1.2.3] - 2026-03-14

### Fixed

- Pint style, booking test time, demo reset seeder
- Move all use imports to top of api_v1.php for Pint compliance


## [1.2.2] - 2026-03-14

### Fixed

- Add missing me and features v1 routes for frontend compatibility
- Remove duplicate DemoController import in api_v1.php
- Add missing DemoController import in v1 API routes
- Audit cleanup — i18n, credential consistency, dead code removal


## [1.2.1] - 2026-03-14

### Added

- Toggleable UX experience modules + PWA + i18n for all 10 locales
- Solo reset mode with countdown + cancel
- Use-case selector with adaptive theming


### CI

- Add Dependabot grouped updates for minor/patch versions
- Add workflow_dispatch trigger to all workflows
- Add Dependabot version update config


### Dependencies

- Bump docker/build-push-action from 6 to 7 (#9)
- Bump docker/metadata-action from 5 to 6 (#8)
- Bump actions/cache from 4 to 5 (#7)


### Documentation

- Add compliance badges and regulatory coverage table to README
- Add compliance report from legal audit
- Legal compliance audit — GDPR, TTDSG, BFSG, international


### Fixed

- Resolve TypeScript errors in Framer Motion ease types and FeaturesContext
- Sync composer.lock hash with composer.json metadata changes
- Make Docker port configurable via PORT env var
- Update composer.json project metadata
- Pass credits variables into transaction closure
- Resolve all CI failures and clean up workflows
- Bump PHP version to 8.4 for Symfony 8.0 compatibility
- Accessibility, reduced-motion, i18n completeness, and service worker versioning
- Sync frontend with Rust — missing CSS, Welcome refinements
- Update login placeholder to parkhub.test
- Stop silencing migration and seeding errors
- Replace parkhub-demo.de with parkhub.test domain
- Add missing auth.passwordConfirmation to all locales
- Align DemoOverlay with actual API response shape
- Resolve npm security vulnerabilities
- Add Vite client types for import.meta.env


### Design

- Industrial-luxury aesthetic rework


## [1.2.0] - 2026-03-13

### Added

- Astro 6 frontend, credits system, production admin command
- Add demo overlay with 30-min countdown, collaborative vote reset, viewer count
- V1.2.0 — routes, queue jobs, webhook delivery, auto-release, recurring bookings, Koyeb
- Add Render.com demo deployment config


### Chore

- Remove deprecated clawdemos.duckdns.org demo URL
- Comprehensive audit — improve docs, fix configs, harden security


### Documentation

- Docs: add screenshot gallery to docs/screenshots 9 screenshots of the ParkHub UI captured via Playwright at 1280x800-860px,
all within the 1990px API limit. Covers login, dashboard, registration,
booking creation, bookings list, vehicles, admin panel, and dark mode.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
- Add v1.1.1 changelog entry


### Fixed

- Security audit + industrial precision UI redesign
- Bake Apache port 10000 into Dockerfile for Render
- Dockerfile PHP 8.4, composer install, CMD for Render deployment
- Admin.tsx broken API URLs and UUID token migration (#1, #2)
- Resolve test failures in auth, admin role update, and booking overlap
- Legal template — add supervisory authority examples and withdrawal right
- Audit-found bugs — export method, importIcal route, waitlist notification
- CORS, GHCR build pipeline, render.yaml switch to pre-built image
- Auto-generate slots when creating a parking lot


### Devops

- Dockerfile hardening, compose secrets isolation, CI improvements


### Security

- Comprehensive audit — fix critical vulns, update deps to 2026
- Restrict GitHub Actions CI to minimal permissions


## [1.1.1] - 2026-02-28

### Documentation

- Add v1.1.0 changelog entry — security hardening and GDPR fixes


### Fixed

- Profile save API call, vehicle field name, security headers, duplicate theme toggle, router aliases, cleanup backup files
- P0 bug fixes — health/ready, announcements, password validation, past booking, single booking endpoint, GDPR deletion response, CORS, admin pagination


## [1.1.0] - 2026-02-28

### Added

- Security headers middleware, legal templates, transparency page
- Password reset email, admin booking API, VERSION file, health probe fix


### CI

- Add GitHub Actions CI pipeline


### Documentation

- Comprehensive documentation suite — installation, API, GDPR, security, changelog
- World-class README, issue templates, PR template


### Fixed

- P0+P1 security hardening — admin middleware, rate limiting, double-booking lock, GDPR erasure, IDOR fix, token expiry, recurring validation
- Health checks, named volumes, restart policy,.env.example, override example
- Deep audit fixes — password reset pages, missing flows, UX polish, email templates
- Replace placeholder clone URL with actual repo URL


## [1.0.1] - 2026-02-27

### Documentation

- Add v1.0.1 changelog entry with E2E bug fixes


### Fixed

- E2E-identified bugs — auth bypass, bookings status, privacy template


## [1.0.0] - 2026-02-27

### Added

- V1.0.0 release preparation — security, accessibility, docs
- Feature parity batch 2 — 40+ new endpoints (system, auth, bookings, absences, admin, branding, qr, swap-requests)
- Feature parity with parkhub-docker
- Add PHPUnit feature tests (Auth, Booking, Lot, Admin, Absence) + build assets
- Default admin in entrypoint, setup/change-password + setup/complete routes, onboarding URL fixes
- Configurable VITE_BASE_PATH build arg, default root deployment


### Documentation

- Add root CHANGELOG.md for GitHub release notes
- Rewrite README with full feature comparison table, quick start options


### Fixed

- LicensePlateInput accepts full plate strings typed/pasted at once
- Portable shebang for deploy script
- Use VITE_API_URL prefix for all API calls and router basename
- Default admin password matches Rust version (admin/admin for auto-login)
- Auto-generate layout from slots, ParkingSlot number accessor, all API compat fixes
- Add missing routes (announcements/active, updates/check), fix homeoffice API format
- Safe optional chaining for hoSettings in Dashboard
- Broken fetch call in importAbsenceIcal, QR code URL prefix
- CSRF excluded, BrowserRouter basename, hardcoded API URLs
- Disable CSRF for API routes (419 error)


### Merge

- Develop into main (full feature parity, new README)
- Feature/backend-parity into develop



