<?php

/**
 * API v1 routes — compatible with the Rust backend's endpoint structure.
 * All routes are prefixed with /api/v1 (set in bootstrap/app.php).
 *
 * Module-specific routes are loaded from routes/modules/*.php
 * based on config/modules.php toggle state.
 */

use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\NotificationPreferencesController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Support\Facades\Route;

// ── Core routes (always available) ──────────────────────────────────────────

// Auth — rate limited: 5 attempts per minute per IP to prevent brute force
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
});

// Auth (public) — rate limited: 3 password resets per 15 min per IP
Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// Public
Route::get('/public/occupancy', [PublicController::class, 'occupancy']);
Route::get('/public/display', [PublicController::class, 'display']);

// Announcements (public)
Route::get('/announcements/active', [PublicController::class, 'activeAnnouncementsWrapped']);

// Demo mode (public, no auth — by design for public demo)
Route::prefix('demo')->group(function () {
    Route::get('/status', [DemoController::class, 'status']);
    Route::get('/config', [DemoController::class, 'config']);
    Route::middleware('throttle:demo-action')->group(function () {
        Route::post('/vote', [DemoController::class, 'vote']);
        Route::post('/reset', [DemoController::class, 'reset']);
    });
});

// Health (no auth)
Route::get('/health', [PublicController::class, 'healthCheck']);
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health/info', [HealthController::class, 'info']);

// System (public)
Route::get('/system/version', [SystemController::class, 'version']);
Route::get('/system/maintenance', [SystemController::class, 'maintenance']);

// Translation overrides (public — frontend needs runtime i18n patching without login)
Route::get('/translations/overrides', [TranslationController::class, 'overrides']);

// Modules endpoint (public — frontend uses this to discover available features)
Route::get('/modules', [ModuleController::class, 'index']);

// Discovery / handshake endpoint (public)
Route::get('/discover', [PublicController::class, 'discover']);

// ── Map module (public — must be before /lots/{id} catch-all) ────────────────
module_routes('map', 'map.php');

// ── Core protected routes ───────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Auth (protected)
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::patch('/users/me/password', [AuthController::class, 'changePassword']);

    // 2FA
    Route::post('/auth/2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('/auth/2fa/verify', [TwoFactorController::class, 'verify']);
    Route::post('/auth/2fa/disable', [TwoFactorController::class, 'disable']);

    // Login history
    Route::get('/auth/login-history', [AuthController::class, 'loginHistory']);

    // Session management
    Route::get('/auth/sessions', [SessionController::class, 'index']);
    Route::delete('/auth/sessions/{id}', [SessionController::class, 'destroy']);
    Route::delete('/auth/sessions', [SessionController::class, 'destroyAll']);

    // API keys
    Route::post('/auth/api-keys', [ApiKeyController::class, 'store']);
    Route::get('/auth/api-keys', [ApiKeyController::class, 'index']);
    Route::delete('/auth/api-keys/{id}', [ApiKeyController::class, 'destroy']);

    // Notification preferences
    Route::get('/preferences/notifications', [NotificationPreferencesController::class, 'show']);
    Route::put('/preferences/notifications', [NotificationPreferencesController::class, 'update']);

    // Users — /me aliases for frontend compatibility
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::get('/users/me', [AuthController::class, 'me']);
    Route::put('/users/me', [AuthController::class, 'updateMe']);

    // Feature flags — stub for frontend compatibility
    Route::get('/features', [PublicController::class, 'featureFlags']);

    // Lots (core — always available)
    Route::get('/lots', [LotController::class, 'index']);
    Route::post('/lots', [LotController::class, 'store']);
    Route::get('/lots/{id}', [LotController::class, 'show']);
    Route::put('/lots/{id}', [LotController::class, 'update']);
    Route::delete('/lots/{id}', [LotController::class, 'destroy']);
    Route::get('/lots/{id}/slots', [LotController::class, 'slots']);
    Route::get('/lots/{id}/occupancy', [LotController::class, 'occupancy']);
    Route::get('/lots/{id}/layout', [LotController::class, 'show']);
    Route::put('/lots/{id}/layout', [LotController::class, 'update']);

    // Slots (core)
    Route::post('/lots/{lotId}/slots', [SlotController::class, 'store']);
    Route::put('/lots/{lotId}/slots/{slotId}', [SlotController::class, 'update']);
    Route::delete('/lots/{lotId}/slots/{slotId}', [SlotController::class, 'destroy']);

    // Team
    Route::get('/team', [TeamController::class, 'index']);
    Route::get('/team/today', [TeamController::class, 'today']);

    // User preferences & stats
    Route::get('/user/preferences', [UserController::class, 'preferences']);
    Route::put('/user/preferences', [UserController::class, 'updatePreferences']);
    Route::get('/user/stats', [UserController::class, 'stats']);

    // Translation management
    Route::get('/translations/proposals', [TranslationController::class, 'proposals']);
    Route::get('/translations/proposals/{id}', [TranslationController::class, 'showProposal']);
    Route::post('/translations/proposals', [TranslationController::class, 'createProposal']);
    Route::post('/translations/proposals/{id}/vote', [TranslationController::class, 'vote']);
    Route::put('/translations/proposals/{id}/review', [TranslationController::class, 'review']);

    Route::get('/update/check', [PublicController::class, 'updateCheck']);

    // Admin — core admin routes (always available)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Audit log
        Route::get('/audit-log', [AdminController::class, 'auditLog']);

        // Settings
        Route::get('/settings', [AdminSettingsController::class, 'getSettings']);
        Route::put('/settings', [AdminSettingsController::class, 'updateSettings']);
        Route::get('/settings/use-case', [AdminSettingsController::class, 'getUseCase']);
        Route::get('/settings/auto-release', [AdminSettingsController::class, 'getAutoReleaseSettings']);
        Route::put('/settings/auto-release', [AdminSettingsController::class, 'updateAutoReleaseSettings']);
        Route::get('/settings/email', [AdminSettingsController::class, 'getEmailSettings']);
        Route::put('/settings/email', [AdminSettingsController::class, 'updateEmailSettings']);
        Route::get('/settings/webhooks', [AdminSettingsController::class, 'getWebhookSettings']);
        Route::put('/settings/webhooks', [AdminSettingsController::class, 'updateWebhookSettings']);
        Route::post('/reset', [AdminSettingsController::class, 'resetDatabase']);

        // Backup / restore
        Route::get('/backup', [AdminSettingsController::class, 'exportBackup']);
        Route::post('/restore', [AdminSettingsController::class, 'importBackup']);

        // User management
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::get('/users/{id}/login-history', [AuthController::class, 'adminLoginHistory']);

        // Bulk operations
        Route::post('/users/bulk', [AdminController::class, 'bulkAction']);

        // Announcements
        Route::get('/announcements', [AdminAnnouncementController::class, 'announcements']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'createAnnouncement']);
        Route::put('/announcements/{id}', [AdminAnnouncementController::class, 'updateAnnouncement']);
        Route::delete('/announcements/{id}', [AdminAnnouncementController::class, 'deleteAnnouncement']);

        Route::get('/updates/check', [PublicController::class, 'updateCheck']);

        // Feature flags — stub for frontend compatibility
        Route::get('/features', [PublicController::class, 'adminFeatureFlags']);
        Route::put('/features', [PublicController::class, 'adminFeatureFlags']);

        // Lot/slot admin
        Route::patch('/slots/{id}', [AdminController::class, 'updateSlot']);
        Route::delete('/lots/{id}', [AdminController::class, 'deleteLot']);
    });

    // Waitlist (core)
    Route::get('/waitlist', [WaitlistController::class, 'index']);
    Route::post('/waitlist', [WaitlistController::class, 'store']);
    Route::delete('/waitlist/{id}', [WaitlistController::class, 'destroy']);
});

// ── Module routes (conditionally loaded) ────────────────────────────────────

// History + Accessible must load before bookings (bookings/{id} catch-all would swallow bookings/history, bookings/stats, bookings/accessible-stats)
module_routes('history', 'history.php');
module_routes('geofence', 'geofence.php');
module_routes('accessible', 'accessible.php');
module_routes('bookings', 'bookings.php');
module_routes('vehicles', 'vehicles.php');
module_routes('absences', 'absences.php');
// Stripe — always load routes before payments (module disabled by default, middleware gates access)
// Must register before payments.php so /payments/config/status matches before /payments/{id}/status
require base_path('routes/modules/stripe.php');
module_routes('payments', 'payments.php');
module_routes('webhooks', 'webhooks.php');
module_routes('notifications', 'notifications.php');
module_routes('branding', 'branding.php');
module_routes('import', 'import.php');
module_routes('qr_codes', 'qr_codes.php');
module_routes('favorites', 'favorites.php');
module_routes('swap_requests', 'swap_requests.php');
module_routes('recurring_bookings', 'recurring_bookings.php');
module_routes('zones', 'zones.php');
module_routes('credits', 'credits.php');
module_routes('metrics', 'metrics.php');
module_routes('admin_reports', 'admin_reports.php');
module_routes('data_export', 'data_export.php');
module_routes('setup_wizard', 'setup_wizard.php');
module_routes('gdpr', 'gdpr.php');
module_routes('push_notifications', 'push_notifications.php');
module_routes('themes', 'themes.php');
module_routes('dynamic_pricing', 'dynamic-pricing.php');
module_routes('operating_hours', 'operating-hours.php');
module_routes('realtime', 'realtime.php');
module_routes('lobby_display', 'lobby.php');
module_routes('analytics', 'analytics.php');
module_routes('admin_analytics', 'admin_analytics.php');
module_routes('ical', 'ical.php');
module_routes('rate_dashboard', 'rate_dashboard.php');
module_routes('multi_tenant', 'multi_tenant.php');
module_routes('audit_log', 'audit_log.php');
module_routes('data_import', 'data_import.php');
module_routes('fleet', 'fleet.php');
module_routes('maintenance', 'maintenance.php');
module_routes('cost_center', 'cost_center.php');

module_routes('visitors', 'visitors.php');
module_routes('ev_charging', 'ev_charging.php');
module_routes('recommendations', 'recommendations.php');

module_routes('waitlist_ext', 'waitlist.php');
module_routes('parking_pass', 'parking_pass.php');

module_routes('absence_approval', 'absence_approval.php');
module_routes('calendar_drag', 'calendar_drag.php');
module_routes('widgets', 'widgets.php');

module_routes('plugins', 'plugins.php');
module_routes('graphql', 'graphql.php');
module_routes('compliance', 'compliance.php');

// v4.1 features
module_routes('sharing', 'sharing.php');
module_routes('scheduled_reports', 'scheduled_reports.php');
module_routes('api_versioning', 'api_versioning.php');

// OAuth — always load routes (module disabled by default, middleware gates access)
require base_path('routes/modules/oauth.php');

// v4.2 features
module_routes('sso', 'sso.php');
module_routes('webhooks_v2', 'webhooks_v2.php');
module_routes('enhanced_pwa', 'enhanced_pwa.php');

// v4.3 features
module_routes('rbac', 'rbac.php');
module_routes('parking_zones', 'parking_zones.php');

// v4.4 features
module_routes('notification_center', 'notification_center.php');
module_routes('mobile', 'mobile.php');
