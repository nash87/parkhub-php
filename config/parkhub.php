<?php

return [
    'demo_mode' => env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    */

    'password_min_length' => (int) env('PASSWORD_MIN_LENGTH', 8),
    'password_require_uppercase' => (bool) env('PASSWORD_REQUIRE_UPPERCASE', true),
    'password_require_number' => (bool) env('PASSWORD_REQUIRE_NUMBER', true),
    'password_require_special' => (bool) env('PASSWORD_REQUIRE_SPECIAL', false),

    /*
    |--------------------------------------------------------------------------
    | Booking Policies
    |--------------------------------------------------------------------------
    */

    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 90),
    'min_duration_hours' => (float) env('BOOKING_MIN_DURATION_HOURS', 0.5),
    'max_duration_hours' => (float) env('BOOKING_MAX_DURATION_HOURS', 720),
    'max_active_bookings' => (int) env('BOOKING_MAX_ACTIVE', 10),

    /*
    |--------------------------------------------------------------------------
    | GDPR / Audit Retention
    |--------------------------------------------------------------------------
    |
    | Days to keep rows in the audit_log table. Enforced by
    | App\Jobs\PurgeAuditLogsJob, scheduled daily at 03:15 UTC.
    | See docs/GDPR.md.
    */

    'audit_retention_days' => (int) env('AUDIT_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Self-Update
    |--------------------------------------------------------------------------
    |
    | The release/update controller defaults to the conventional origin/main
    | pair, but local development worktrees may keep a stale Gitea remote as
    | origin while GitHub is the canonical source of truth.
    */

    'updates' => [
        'remote' => env('PARKHUB_UPDATE_REMOTE', 'origin'),
        'branch' => env('PARKHUB_UPDATE_BRANCH', 'main'),
    ],
];
