<?php

/**
 * Module Configuration
 *
 * Toggle individual modules on/off via environment variables.
 * When a module is disabled its routes are not registered (404),
 * and the GET /api/v1/modules endpoint reflects the current state.
 *
 * Core modules default to enabled (opt-out). Integration modules that require
 * external credentials or are enterprise-only default to disabled (opt-in).
 */

return [
    // ── Core (enabled by default) ──────────────────────────────────
    'bookings' => env('MODULE_BOOKINGS', true),
    'vehicles' => env('MODULE_VEHICLES', true),
    'absences' => env('MODULE_ABSENCES', true),
    'payments' => env('MODULE_PAYMENTS', true),
    'notifications' => env('MODULE_NOTIFICATIONS', true),
    'branding' => env('MODULE_BRANDING', true),
    'qr_codes' => env('MODULE_QR_CODES', true),
    'favorites' => env('MODULE_FAVORITES', true),
    'swap_requests' => env('MODULE_SWAP_REQUESTS', true),
    'recurring_bookings' => env('MODULE_RECURRING_BOOKINGS', true),
    'zones' => env('MODULE_ZONES', true),
    'credits' => env('MODULE_CREDITS', true),
    'themes' => env('MODULE_THEMES', true),
    'invoices' => env('MODULE_INVOICES', true),
    'operating_hours' => env('MODULE_OPERATING_HOURS', true),
    'setup_wizard' => env('MODULE_SETUP_WIZARD', true),
    'gdpr' => env('MODULE_GDPR', true),
    'ical' => env('MODULE_ICAL', true),
    'map' => env('MODULE_MAP', true),
    'lobby_display' => env('MODULE_LOBBY_DISPLAY', true),

    // ── Admin (enabled by default) ─────────────────────────────────
    'admin_reports' => env('MODULE_ADMIN_REPORTS', true),
    'analytics' => env('MODULE_ANALYTICS', true),
    'admin_analytics' => env('MODULE_ADMIN_ANALYTICS', true),
    'data_export' => env('MODULE_DATA_EXPORT', true),
    'import' => env('MODULE_IMPORT', true),
    'metrics' => env('MODULE_METRICS', true),
    'rate_dashboard' => env('MODULE_RATE_DASHBOARD', true),
    'audit_log' => env('MODULE_AUDIT_LOG', true),
    'data_import' => env('MODULE_DATA_IMPORT', true),
    'fleet' => env('MODULE_FLEET', true),
    'accessible' => env('MODULE_ACCESSIBLE', true),
    'maintenance' => env('MODULE_MAINTENANCE', true),
    'cost_center' => env('MODULE_COST_CENTER', true),
    'visitors' => env('MODULE_VISITORS', true),
    'ev_charging' => env('MODULE_EV_CHARGING', true),
    'recommendations' => env('MODULE_RECOMMENDATIONS', true),
    'history' => env('MODULE_HISTORY', true),
    'geofence' => env('MODULE_GEOFENCE', true),
    'waitlist_ext' => env('MODULE_WAITLIST_EXT', true),
    'parking_pass' => env('MODULE_PARKING_PASS', true),
    'api_docs' => env('MODULE_API_DOCS', true),
    'absence_approval' => env('MODULE_ABSENCE_APPROVAL', true),
    'calendar_drag' => env('MODULE_CALENDAR_DRAG', true),
    'widgets' => env('MODULE_WIDGETS', true),

    // ── Integration (disabled by default — requires credentials) ───
    'stripe' => env('MODULE_STRIPE', false),
    'oauth' => env('MODULE_OAUTH', false),
    'web_push' => env('MODULE_WEB_PUSH', false),
    'webhooks' => env('MODULE_WEBHOOKS', false),
    'push_notifications' => env('MODULE_PUSH_NOTIFICATIONS', false),
    'broadcasting' => env('MODULE_BROADCASTING', false),
    'realtime' => env('MODULE_REALTIME', false),

    // ── v4.0 Features (enabled by default) ─────────────────────────
    'plugins' => env('MODULE_PLUGINS', true),
    'graphql' => env('MODULE_GRAPHQL', true),
    'compliance' => env('MODULE_COMPLIANCE', true),

    // ── v4.1 Features (enabled by default) ─────────────────────────
    'sharing' => env('MODULE_SHARING', true),
    'scheduled_reports' => env('MODULE_SCHEDULED_REPORTS', true),
    'api_versioning' => env('MODULE_API_VERSIONING', true),

    // ── v4.2 Features ───────────────────────────────────────────────
    // SECURITY: SSO is disabled by default. The SAML callback endpoint currently
    // performs no XML signature or assertion verification. Production use requires
    // onelogin/php-saml (or an equivalent SAML library) with full signature
    // validation configured. Only enable (MODULE_SSO=true) after integrating a
    // compliant SAML library and verifying IdP certificates are enforced.
    'sso' => env('MODULE_SSO', false),
    'webhooks_v2' => env('MODULE_WEBHOOKS_V2', false),
    'enhanced_pwa' => env('MODULE_ENHANCED_PWA', true),

    // ── v4.3 Features ───────────────────────────────────────────────
    'rbac' => env('MODULE_RBAC', true),
    'audit_export' => env('MODULE_AUDIT_EXPORT', true),
    'parking_zones' => env('MODULE_PARKING_ZONES', true),

    // ── v4.4 Features ───────────────────────────────────────────────
    'notification_center' => env('MODULE_NOTIFICATION_CENTER', true),
    'mobile' => env('MODULE_MOBILE', true),

    // ── Enterprise (disabled by default — opt-in) ──────────────────
    'multi_tenant' => env('MODULE_MULTI_TENANT', false),
    'dynamic_pricing' => env('MODULE_DYNAMIC_PRICING', false),
];
