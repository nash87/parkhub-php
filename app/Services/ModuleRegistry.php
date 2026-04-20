<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\Api\SystemController;
use App\Models\Setting;
use App\Providers\ModuleServiceProvider;

/**
 * Declarative metadata registry for every module exposed by the PHP
 * backend. Mirrors `parkhub-server/src/api/modules_meta.rs` in the Rust
 * edition: both backends ship a `{modules, module_info}` envelope on
 * `/api/v1/modules` so the shared frontend (parkhub-web) can render a
 * category-grouped, deep-linkable Modules Dashboard regardless of which
 * backend is answering.
 *
 * Design principle: a single PHP const array is the source of truth.
 * Adding a module means editing ONE table; no scattered registration
 * macros, no cross-file drift. Everything a user or admin sees about
 * modules ships in the binary — nothing calls out to the network.
 *
 * The registry stores its historic PHP/internal slugs (mostly
 * snake_case) because `config/modules.php` and `settings.key` still
 * use those names. Public API payloads, however, speak the canonical
 * product slugs from the Rust edition (mostly kebab-case). The alias
 * layer in this class converts between the two so both backends expose
 * the same customer-visible module names without forcing a risky
 * config/settings migration in the Laravel app.
 *
 * v2 flips `runtime_toggleable = true` on the subset of modules that
 * are safe to hot-toggle at runtime without data-loss or auth bypass
 * risk. Toggleable modules read an override from the `settings` table
 * (keyed `module.{name}.runtime_enabled`) on every materialization pass
 * and fall back to the env-derived `enabled` bit when the setting is
 * absent. Security / payment / tenancy modules stay `false` and are
 * driven solely by env config — hot-toggling those would be a footgun.
 *
 * `config_keys` is the flat list of scalar keys a module persists in
 * the `settings` table (under `module.{name}.config.{key}`). v3 adds a
 * `config_schema` sibling: an optional JSON Schema draft 2020-12
 * document that typed-validates the config values an admin submits via
 * the PATCH /api/v1/modules/{name}/config endpoint. Modules without a
 * schema return a 400 from GET / 409-equivalent from PATCH — they're
 * not editable through the generic settings UI.
 *
 * Every successful runtime toggle OR config mutation writes an AuditLog
 * entry; both /api/v1/admin/modules/{name} and
 * /api/v1/modules/{name}/config are admin-gated.
 */
final class ModuleRegistry
{
    /**
     * Canonical product/API slugs → historic PHP/internal slugs.
     *
     * Keep this narrowly scoped to proven equivalences. Missing entries
     * are intentional feature-parity gaps, not "best effort" guesses.
     *
     * @var array<string, string>
     */
    private const CANONICAL_TO_INTERNAL = [
        'absence-approval' => 'absence_approval',
        'recurring' => 'recurring_bookings',
        'swap' => 'swap_requests',
        'waitlist-ext' => 'waitlist_ext',
        'calendar-drag' => 'calendar_drag',
        'qr' => 'qr_codes',
        'parking-pass' => 'parking_pass',
        'dynamic-pricing' => 'dynamic_pricing',
        'audit-export' => 'audit_export',
        'data-import' => 'data_import',
        'export' => 'data_export',
        'setup-wizard' => 'setup_wizard',
        'admin-reports' => 'admin_reports',
        'rate-dashboard' => 'rate_dashboard',
        'admin-analytics' => 'admin_analytics',
        'scheduled-reports' => 'scheduled_reports',
        'webhooks-v2' => 'webhooks_v2',
        'api-docs' => 'api_docs',
        'api-versioning' => 'api_versioning',
        'realtime' => 'realtime',
        'websocket' => 'realtime',
        'notification-center' => 'notification_center',
        'push' => 'push_notifications',
        'web_push' => 'push_notifications',
        'email-templates' => 'email_templates',
        'multi-tenant' => 'multi_tenant',
        'cost-center' => 'cost_center',
        'parking-zones' => 'parking_zones',
        'ev-charging' => 'ev_charging',
        'operating-hours' => 'operating_hours',
        'lobby-display' => 'lobby_display',
        'enhanced-pwa' => 'enhanced_pwa',
    ];

    /**
     * Historic PHP/internal slugs → canonical product/API slugs.
     *
     * @var array<string, string>
     */
    private const INTERNAL_TO_CANONICAL = [
        'absence_approval' => 'absence-approval',
        'recurring_bookings' => 'recurring',
        'swap_requests' => 'swap',
        'waitlist_ext' => 'waitlist-ext',
        'calendar_drag' => 'calendar-drag',
        'qr_codes' => 'qr',
        'parking_pass' => 'parking-pass',
        'dynamic_pricing' => 'dynamic-pricing',
        'audit_export' => 'audit-export',
        'data_import' => 'data-import',
        'data_export' => 'export',
        'setup_wizard' => 'setup-wizard',
        'admin_reports' => 'admin-reports',
        'rate_dashboard' => 'rate-dashboard',
        'admin_analytics' => 'admin-analytics',
        'scheduled_reports' => 'scheduled-reports',
        'webhooks_v2' => 'webhooks-v2',
        'api_docs' => 'api-docs',
        'api_versioning' => 'api-versioning',
        'broadcasting' => 'realtime',
        'notification_center' => 'notification-center',
        'push_notifications' => 'push',
        'email_templates' => 'email-templates',
        'multi_tenant' => 'multi-tenant',
        'cost_center' => 'cost-center',
        'parking_zones' => 'parking-zones',
        'ev_charging' => 'ev-charging',
        'operating_hours' => 'operating-hours',
        'lobby_display' => 'lobby-display',
        'enhanced_pwa' => 'enhanced-pwa',
    ];

    /**
     * Valid ModuleCategory identifiers — kept as a const array so
     * phpstan + tests can assert category coverage cheaply.
     */
    public const CATEGORIES = [
        'Core',
        'Booking',
        'Vehicle',
        'Admin',
        'Payment',
        'Integration',
        'Analytics',
        'Compliance',
        'Notification',
        'Enterprise',
        'Experimental',
    ];

    /**
     * Slugs that are safe to hot-toggle at runtime. Mirrors the
     * `RUNTIME_TOGGLEABLE` set in `parkhub-server/src/api/modules_meta.rs`
     * so a module that flips in Rust also flips in PHP — no backend
     * drift for the shared admin dashboard.
     *
     * Explicitly excluded (env-only): bookings, vehicles, rbac, sso,
     * oauth, multi_tenant, payments, stripe, invoices, webhooks,
     * webhooks_v2, compliance, notifications, push_notifications,
     * web_push, notification_center, audit_export. Toggling any of
     * those at runtime would be a security, billing, or data-loss
     * footgun — stay env-flagged.
     *
     * Defined as an associative array keyed by slug so membership
     * checks are O(1) and the declaration reads as a set, not a list.
     *
     * @var array<string, true>
     */
    private const RUNTIME_TOGGLEABLE = [
        'widgets' => true,
        'themes' => true,
        'favorites' => true,
        'lobby_display' => true,
        'accessible' => true,
        'calendar_drag' => true,
        'ev_charging' => true,
        'maintenance' => true,
        'geofence' => true,
        'map' => true,
        'graphql' => true,
        'api_docs' => true,
        'setup_wizard' => true,
        'social' => true,
    ];

    /**
     * Internal rows that are transport/plumbing details and should stay
     * out of the public module contract.
     *
     * @var array<string, true>
     */
    private const HIDDEN_PUBLIC_ROWS = [
        'broadcasting' => true,
        'web_push' => true,
    ];

    /**
     * Declarative module table. Each entry omits `enabled`,
     * `runtime_enabled`, and `version` — those are resolved at
     * materialization time from config / VERSION, so the const stays
     * pure metadata and can be reasoned about statically.
     *
     * `config_schema` is an optional JSON Schema 2020-12 document that
     * describes the module's editable settings. When present, the PATCH
     * /api/v1/modules/{name}/config endpoint validates the admin's
     * payload against it via opis/json-schema before persisting each
     * property to the settings table. Modules without a schema stay
     * env-only — they don't surface in the generic config editor UI.
     *
     * @var list<array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     enabled_via?: string,
     *     config_schema?: array<string, mixed>,
     * }>
     */
    private const MODULES = [
        // ── Core ────────────────────────────────────────────────────
        [
            'name' => 'bookings',
            'category' => 'Core',
            'description' => 'Create, edit, cancel, and query reservations.',
            'config_keys' => [],
            'ui_route' => '/bookings',
            'depends_on' => [],
        ],
        [
            'name' => 'vehicles',
            'category' => 'Core',
            'description' => 'User-owned vehicle records with plates and default selection.',
            'config_keys' => [],
            'ui_route' => '/vehicles',
            'depends_on' => [],
        ],
        [
            'name' => 'zones',
            'category' => 'Core',
            'description' => 'Organizational groupings of slots (Level A/B, EV row, visitor row).',
            'config_keys' => [],
            'ui_route' => '/admin/zones',
            'depends_on' => [],
        ],

        // ── Booking-side ───────────────────────────────────────────
        [
            'name' => 'absences',
            'category' => 'Booking',
            'description' => 'Home-office, vacation, sick, training — drives auto-release of booked slots.',
            'config_keys' => [],
            'ui_route' => '/absences',
            'depends_on' => [],
        ],
        [
            'name' => 'absence_approval',
            'category' => 'Booking',
            'description' => 'Manager approval workflow for absence requests.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['absences'],
        ],
        [
            'name' => 'recurring_bookings',
            'category' => 'Booking',
            'description' => 'Weekly / monthly recurring bookings with rule engine.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['bookings'],
        ],
        [
            'name' => 'guest',
            'category' => 'Booking',
            'description' => 'Guest passes — share a time-limited booking with an external visitor.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['bookings'],
            'enabled_via' => 'bookings',
        ],
        [
            'name' => 'swap_requests',
            'category' => 'Booking',
            'description' => 'Peer-to-peer booking swaps between users.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['bookings'],
        ],
        [
            'name' => 'waitlist',
            'category' => 'Booking',
            'description' => 'Waitlist notify-on-availability.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'waitlist_ext',
            'category' => 'Booking',
            'description' => 'Advanced waitlist — priority, expiry, multi-slot.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['waitlist'],
        ],
        [
            'name' => 'sharing',
            'category' => 'Booking',
            'description' => 'Shareable booking links with QR + guest registration.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['bookings'],
        ],
        [
            'name' => 'calendar',
            'category' => 'Booking',
            'description' => 'Weekly/monthly calendar view of bookings.',
            'config_keys' => [],
            'ui_route' => '/calendar',
            'depends_on' => [],
            'enabled_via' => 'bookings',
        ],
        [
            'name' => 'calendar_drag',
            'category' => 'Booking',
            'description' => 'Drag-and-drop reschedule in the calendar view.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['calendar'],
        ],
        [
            'name' => 'favorites',
            'category' => 'Booking',
            'description' => 'Pin a lot/slot as a favorite for one-tap booking.',
            'config_keys' => [],
            'ui_route' => '/favorites',
            'depends_on' => [],
        ],

        // ── Vehicle / Fleet ────────────────────────────────────────
        [
            'name' => 'fleet',
            'category' => 'Vehicle',
            'description' => 'Fleet admin — shared pool vehicles, utilization reports.',
            'config_keys' => [],
            'ui_route' => '/admin/fleet',
            'depends_on' => [],
        ],
        [
            'name' => 'qr_codes',
            'category' => 'Vehicle',
            'description' => 'QR codes for booking confirmations + slot check-in.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'parking_pass',
            'category' => 'Vehicle',
            'description' => 'Printable / digital parking pass with QR barcode.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Payment ───────────────────────────────────────────────
        [
            'name' => 'payments',
            'category' => 'Payment',
            'description' => 'Generic payment provider abstraction.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'stripe',
            'category' => 'Payment',
            'description' => 'Stripe checkout + webhook integration.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['payments'],
        ],
        [
            'name' => 'credits',
            'category' => 'Payment',
            'description' => 'Virtual credits balance — per-user monthly quota.',
            'config_keys' => [],
            'ui_route' => '/credits',
            'depends_on' => [],
        ],
        [
            'name' => 'invoices',
            'category' => 'Payment',
            'description' => 'Per-booking PDF invoice generation.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'dynamic_pricing',
            'category' => 'Payment',
            'description' => 'Time-of-day and occupancy-based price curves.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Admin ─────────────────────────────────────────────────
        [
            'name' => 'rbac',
            'category' => 'Admin',
            'description' => 'Role-based access control — admin, manager, user, guest.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'sso',
            'category' => 'Admin',
            'description' => 'SAML / OIDC single sign-on for external IdPs.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'audit_log',
            'category' => 'Admin',
            'description' => 'Persistent audit trail of admin-relevant actions.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'audit_export',
            'category' => 'Admin',
            'description' => 'Download the audit log as CSV/JSON.',
            'config_keys' => [],
            'ui_route' => '/admin/audit',
            'depends_on' => ['audit_log'],
        ],
        [
            'name' => 'data_import',
            'category' => 'Admin',
            'description' => 'Bulk import users/vehicles/lots from CSV.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'import',
            'category' => 'Admin',
            'description' => 'Legacy import wrapper retained for API compatibility.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'data_export',
            'category' => 'Admin',
            'description' => 'GDPR + user-initiated data export.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'setup_wizard',
            'category' => 'Admin',
            'description' => 'First-run setup wizard for admins.',
            'config_keys' => [],
            'ui_route' => '/setup',
            'depends_on' => [],
        ],
        [
            'name' => 'rate_dashboard',
            'category' => 'Admin',
            'description' => 'Rate-limit + throttle observability dashboard.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'settings',
            'category' => 'Admin',
            'description' => 'Key-value runtime settings store for other modules.',
            'config_keys' => [],
            'ui_route' => '/admin/settings',
            'depends_on' => [],
        ],
        [
            'name' => 'announcements',
            'category' => 'Notification',
            'description' => 'Admin-published banner announcements.',
            'config_keys' => ['max_announcements', 'default_ttl_days', 'show_on_login'],
            'ui_route' => '/admin/announcements',
            'depends_on' => [],
            'config_schema' => [
                'type' => 'object',
                'properties' => [
                    'max_announcements' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 50,
                    ],
                    'default_ttl_days' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 365,
                    ],
                    'show_on_login' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'email_templates',
            'category' => 'Notification',
            'description' => 'Handlebars templates editable from the admin UI.',
            'config_keys' => ['from_address', 'from_name', 'reply_to'],
            'ui_route' => '/admin/email-templates',
            'depends_on' => ['email'],
            'config_schema' => [
                'type' => 'object',
                'properties' => [
                    'from_address' => [
                        'type' => 'string',
                        'format' => 'email',
                    ],
                    'from_name' => [
                        'type' => 'string',
                        'maxLength' => 100,
                    ],
                    'reply_to' => [
                        'type' => 'string',
                        'format' => 'email',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],

        // ── Analytics ────────────────────────────────────────────
        [
            'name' => 'analytics',
            'category' => 'Analytics',
            'description' => 'User-facing analytics — parking history trends.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'admin_analytics',
            'category' => 'Analytics',
            'description' => 'Admin dashboards — bookings, occupancy, revenue.',
            'config_keys' => [],
            'ui_route' => '/admin/analytics',
            'depends_on' => [],
        ],
        [
            'name' => 'admin_reports',
            'category' => 'Analytics',
            'description' => 'Canned admin reports — monthly usage, top users.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'scheduled_reports',
            'category' => 'Analytics',
            'description' => 'Email cron for recurring admin reports.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['admin_reports'],
        ],
        [
            'name' => 'metrics',
            'category' => 'Analytics',
            'description' => 'Prometheus metrics exporter for scrape-based monitoring.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Integration ─────────────────────────────────────────
        [
            'name' => 'webhooks',
            'category' => 'Integration',
            'description' => 'Outbound webhooks (v1 — fire and forget).',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'webhooks_v2',
            'category' => 'Integration',
            'description' => 'Outbound webhooks with retry + signed payloads.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'graphql',
            'category' => 'Integration',
            'description' => 'GraphQL read/write API alongside REST.',
            'config_keys' => [],
            'ui_route' => '/admin/graphql',
            'depends_on' => [],
        ],
        [
            'name' => 'api_docs',
            'category' => 'Integration',
            'description' => 'Swagger UI + OpenAPI 3.1 spec export.',
            'config_keys' => [],
            'ui_route' => '/api/docs',
            'depends_on' => [],
        ],
        [
            'name' => 'api_versioning',
            'category' => 'Integration',
            'description' => 'v1/v2 API surface with deprecation headers.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'oauth',
            'category' => 'Integration',
            'description' => 'OAuth2 callback handling (Google, GitHub, Microsoft).',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'ical',
            'category' => 'Integration',
            'description' => 'Read-only iCal feed of bookings for calendar clients.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'broadcasting',
            'category' => 'Integration',
            'description' => 'Legacy broadcast/auth transport alias for the canonical realtime capability.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
            'enabled_via' => 'realtime',
        ],
        [
            'name' => 'realtime',
            'category' => 'Integration',
            'description' => 'Server-Sent Events + presence channel for collaborative UI.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'widgets',
            'category' => 'Integration',
            'description' => 'Embeddable widgets for external dashboards.',
            'config_keys' => ['max_widgets_per_dashboard'],
            'ui_route' => null,
            'depends_on' => [],
            'config_schema' => [
                'type' => 'object',
                'properties' => [
                    'max_widgets_per_dashboard' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 20,
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],

        // ── Notification ────────────────────────────────────────
        [
            'name' => 'notifications',
            'category' => 'Notification',
            'description' => 'In-app notification bell with per-event preferences.',
            'config_keys' => ['push_enabled', 'email_enabled', 'quiet_hours_start', 'quiet_hours_end'],
            'ui_route' => '/notifications',
            'depends_on' => [],
            'config_schema' => [
                'type' => 'object',
                'properties' => [
                    'push_enabled' => ['type' => 'boolean'],
                    'email_enabled' => ['type' => 'boolean'],
                    'quiet_hours_start' => [
                        'type' => 'string',
                        'format' => 'time',
                    ],
                    'quiet_hours_end' => [
                        'type' => 'string',
                        'format' => 'time',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'notification_center',
            'category' => 'Notification',
            'description' => 'Grouped notification feed with mark-read.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['notifications'],
        ],
        [
            'name' => 'email',
            'category' => 'Notification',
            'description' => 'SMTP delivery of transactional emails.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'push_notifications',
            'category' => 'Notification',
            'description' => 'Web Push subscription + VAPID delivery.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'web_push',
            'category' => 'Notification',
            'description' => 'Lower-level Web Push plumbing shared by notification channels.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Compliance ──────────────────────────────────────────
        [
            'name' => 'compliance',
            'category' => 'Compliance',
            'description' => 'Compliance tooling — export, evidence, attestations.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'accessible',
            'category' => 'Compliance',
            'description' => 'Accessible-slots policy + user accessibility needs.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'gdpr',
            'category' => 'Compliance',
            'description' => 'GDPR consent ledger, data-subject requests, right-to-be-forgotten.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Enterprise ──────────────────────────────────────────
        [
            'name' => 'multi_tenant',
            'category' => 'Enterprise',
            'description' => 'Multi-tenant isolation — per-tenant users, lots, branding.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'cost_center',
            'category' => 'Enterprise',
            'description' => 'Per-cost-center reporting + allocation.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'themes',
            'category' => 'Enterprise',
            'description' => 'Per-tenant theme / branding customization.',
            'config_keys' => ['default_theme', 'allow_user_override'],
            'ui_route' => null,
            'depends_on' => [],
            'config_schema' => [
                'type' => 'object',
                'properties' => [
                    'default_theme' => [
                        'type' => 'string',
                        'enum' => ['light', 'dark', 'classic'],
                    ],
                    'allow_user_override' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'plugins',
            'category' => 'Enterprise',
            'description' => 'Runtime plugin loader for customer-side extensions.',
            'config_keys' => [],
            'ui_route' => '/admin/plugins',
            'depends_on' => [],
        ],
        [
            'name' => 'branding',
            'category' => 'Enterprise',
            'description' => 'App name + logo customization.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'translations',
            'category' => 'Enterprise',
            'description' => 'Per-tenant string overrides for i18n keys.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'parking_zones',
            'category' => 'Enterprise',
            'description' => 'Advanced zone management + rules.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['zones'],
        ],

        // ── Experimental / Hardware ─────────────────────────────
        [
            'name' => 'map',
            'category' => 'Experimental',
            'description' => 'Map view of lots with live occupancy overlay.',
            'config_keys' => [],
            'ui_route' => '/map',
            'depends_on' => [],
        ],
        [
            'name' => 'geofence',
            'category' => 'Experimental',
            'description' => 'GPS-geofenced auto check-in.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'visitors',
            'category' => 'Experimental',
            'description' => 'Visitor badge + pre-registration.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'maintenance',
            'category' => 'Experimental',
            'description' => 'Slot maintenance windows + technician scheduling.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'history',
            'category' => 'Experimental',
            'description' => 'Extended parking history view for users.',
            'config_keys' => [],
            'ui_route' => '/history',
            'depends_on' => [],
        ],
        [
            'name' => 'social',
            'category' => 'Experimental',
            'description' => 'Leaderboards + social sharing.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'ev_charging',
            'category' => 'Experimental',
            'description' => 'EV-charging slots + charging-session metadata.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'recommendations',
            'category' => 'Experimental',
            'description' => 'Slot recommendations based on user history.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'operating_hours',
            'category' => 'Experimental',
            'description' => 'Lot operating-hours enforcement.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'lobby_display',
            'category' => 'Experimental',
            'description' => 'Kiosk / lobby-display mode for public screens.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'team',
            'category' => 'Experimental',
            'description' => 'Team-level booking + fair-share policies.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'jobs',
            'category' => 'Experimental',
            'description' => 'Background job scheduling (cron, intervals).',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'pwa',
            'category' => 'Experimental',
            'description' => 'Progressive Web App manifest + basic offline shell.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
            'enabled_via' => 'enhanced_pwa',
        ],
        [
            'name' => 'enhanced_pwa',
            'category' => 'Experimental',
            'description' => 'Enhanced PWA with booking prefetch and offline data.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['pwa'],
        ],
        [
            'name' => 'mobile',
            'category' => 'Experimental',
            'description' => 'Mobile-specific surfaces (install prompt, haptics).',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
    ];

    /**
     * Return every module as a materialized ModuleInfo shape. The order
     * matches the const declaration above so the admin dashboard stays
     * stable across deploys.
     *
     * @return list<array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     enabled: bool,
     *     runtime_toggleable: bool,
     *     runtime_enabled: bool,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     version: string,
     *     config_schema: array<string, mixed>|null,
     * }>
     */
    public static function all(): array
    {
        $version = SystemController::appVersion();

        return array_map(
            static fn (array $meta): array => self::materialize($meta, $version),
            self::publicModuleRows(),
        );
    }

    /**
     * Fetch a single module by slug. Returns null for unknown modules,
     * matching the 404 semantics the controller needs.
     *
     * @return array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     enabled: bool,
     *     runtime_toggleable: bool,
     *     runtime_enabled: bool,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     version: string,
     *     config_schema: array<string, mixed>|null,
     * }|null
     */
    public static function get(string $name): ?array
    {
        $internalName = self::internalName($name);

        foreach (self::MODULES as $meta) {
            if ($meta['name'] === $internalName) {
                return self::materialize($meta, SystemController::appVersion());
            }
        }

        return null;
    }

    /**
     * Return the raw JSON Schema document a module declares, or null if
     * the module has no schema (or doesn't exist). Used by the config
     * endpoints before they touch the settings table — keeping the
     * schema lookup out of the controller body.
     *
     * @return array<string, mixed>|null
     */
    public static function configSchema(string $name): ?array
    {
        $internalName = self::internalName($name);

        foreach (self::MODULES as $meta) {
            if ($meta['name'] !== $internalName) {
                continue;
            }

            return $meta['config_schema'] ?? null;
        }

        return null;
    }

    /**
     * Canonical settings key for a single config property of a module.
     * Centralised so every layer agrees on the shape
     * (`module.{name}.config.{key}`) without a magic string scattered
     * across controller + registry + tests.
     */
    public static function configSettingKey(string $name, string $key): string
    {
        $internalName = self::internalName($name);

        return "module.{$internalName}.config.{$key}";
    }

    /**
     * Project the `{module => bool}` map expected by the existing
     * `/api/v1/modules` consumers. Combines the registry with any
     * extra slugs declared only in config/modules.php so no module
     * disappears from the backwards-compat envelope.
     *
     * @return array<string, bool>
     */
    public static function enabledMap(): array
    {
        $map = [];

        foreach (self::publicModuleRows() as $meta) {
            $canonicalName = self::canonicalName($meta['name']);
            $map[$canonicalName] = self::resolveEnabled($meta['enabled_via'] ?? $meta['name']);
        }

        foreach (ModuleServiceProvider::all() as $name => $enabled) {
            if (isset(self::HIDDEN_PUBLIC_ROWS[(string) $name])) {
                continue;
            }
            $canonicalName = self::canonicalName((string) $name);
            if (! array_key_exists($canonicalName, $map)) {
                $map[$canonicalName] = (bool) $enabled;
            }
        }

        return $map;
    }

    /**
     * @return list<array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     enabled_via?: string,
     *     config_schema?: array<string, mixed>,
     * }>
     */
    private static function publicModuleRows(): array
    {
        $rows = [];

        foreach (self::MODULES as $meta) {
            if (isset(self::HIDDEN_PUBLIC_ROWS[$meta['name']])) {
                continue;
            }

            $rows[] = $meta;
        }

        return $rows;
    }

    /**
     * @param  array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     enabled_via?: string,
     *     config_schema?: array<string, mixed>,
     * }  $meta
     * @return array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     enabled: bool,
     *     runtime_toggleable: bool,
     *     runtime_enabled: bool,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     version: string,
     *     config_schema: array<string, mixed>|null,
     * }
     */
    private static function materialize(array $meta, string $version): array
    {
        $enabled = self::resolveEnabled($meta['enabled_via'] ?? $meta['name']);
        $toggleable = isset(self::RUNTIME_TOGGLEABLE[$meta['name']]);
        $runtimeEnabled = $toggleable
            ? self::resolveRuntimeOverride($meta['name'], $enabled)
            : $enabled;

        return [
            'name' => self::canonicalName($meta['name']),
            'category' => $meta['category'],
            'description' => $meta['description'],
            'enabled' => $enabled,
            'runtime_toggleable' => $toggleable,
            'runtime_enabled' => $runtimeEnabled,
            'config_keys' => $meta['config_keys'],
            'ui_route' => $meta['ui_route'],
            'depends_on' => array_map(
                static fn (string $dependsOn): string => self::canonicalName($dependsOn),
                $meta['depends_on'],
            ),
            'version' => $version,
            'config_schema' => $meta['config_schema'] ?? null,
        ];
    }

    /**
     * Resolve the admin-set runtime override for a toggleable module.
     *
     * Reads `module.{name}.runtime_enabled` from the settings table.
     * `Setting::get` returns whatever was stored (string '1'/'0' when
     * written by our PATCH endpoint, but tolerant of bool/int too).
     * Falls back to the env-driven `enabled` bit when no override is
     * set so a fresh install still reflects the shipped defaults.
     */
    private static function resolveRuntimeOverride(string $name, bool $fallback): bool
    {
        try {
            /** @var string|int|bool|null $stored */
            $stored = Setting::get(self::runtimeSettingKey($name));
        } catch (\Throwable) {
            return $fallback;
        }

        if ($stored === null) {
            return $fallback;
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    /**
     * Canonical settings key for a module's runtime override. Centralised
     * so the controller, registry, and middleware all agree on the key
     * shape without a magic string scattered across three files.
     */
    public static function runtimeSettingKey(string $name): string
    {
        $internalName = self::internalName($name);

        return "module.{$internalName}.runtime_enabled";
    }

    /**
     * Is this module allowed to be hot-toggled via the admin API? Used
     * by the PATCH endpoint to reject non-toggleable modules with a
     * 409 before hitting the settings table.
     */
    public static function isToggleable(string $name): bool
    {
        return isset(self::RUNTIME_TOGGLEABLE[self::internalName($name)]);
    }

    /**
     * Persist a runtime override for a toggleable module. No-ops for
     * modules that aren't on the allowlist so a bug in the controller
     * layer can't silently bypass the policy.
     */
    public static function setRuntimeEnabled(string $name, bool $enabled): void
    {
        $internalName = self::internalName($name);

        if (! self::isToggleable($internalName)) {
            return;
        }

        Setting::set(self::runtimeSettingKey($internalName), $enabled ? '1' : '0');
    }

    /**
     * Resolve enabled state from the canonical config source.
     *
     * `config('modules.'.$name)` is what the rest of the runtime checks
     * (route guards, middleware, feature flags). Sticking to that single
     * source of truth keeps the registry honest when config is cached
     * and sidesteps Larastan's `env()`-outside-of-config rule.
     *
     * Defaults to true so newly registered slugs that haven't yet been
     * promoted into config/modules.php appear enabled, matching how
     * compile-time features default on in the Rust edition.
     */
    private static function resolveEnabled(string $name): bool
    {
        $internalName = self::internalName($name);

        /** @var bool|int|string|null $configured */
        $configured = config('modules.'.$internalName);
        if ($configured === null) {
            return true;
        }

        return (bool) $configured;
    }

    private static function internalName(string $name): string
    {
        return self::CANONICAL_TO_INTERNAL[$name] ?? $name;
    }

    private static function canonicalName(string $name): string
    {
        return self::INTERNAL_TO_CANONICAL[$name] ?? $name;
    }
}
