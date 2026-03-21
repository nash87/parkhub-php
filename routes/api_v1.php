<?php

/**
 * API v1 routes — compatible with the Rust backend's endpoint structure.
 * All routes are prefixed with /api/v1 (set in bootstrap/app.php).
 */

use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminCreditController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingInvoiceController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\MiscController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\PulseController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RecurringBookingController;
use App\Http\Controllers\Api\SetupController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\ZoneController;
use Illuminate\Support\Facades\Route;

// Auth — rate limited: 5 attempts per minute per IP to prevent brute force
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
});

// Setup — status is always public; mutation endpoints are blocked once setup is completed
Route::get('/setup/status', [SetupController::class, 'status']);
Route::post('/setup', [SetupController::class, 'init']);
Route::middleware('throttle:setup')->group(function () {
    Route::post('/setup/change-password', [SetupController::class, 'changePassword']);
    Route::post('/setup/complete', [SetupController::class, 'complete']);
});

// Public
Route::get('/public/occupancy', [PublicController::class, 'occupancy']);
Route::get('/public/display', [PublicController::class, 'display']);
Route::get('/theme', [AdminSettingsController::class, 'getPublicTheme']);

// VAPID public key for push subscriptions
Route::get('/push/vapid-key', [PublicController::class, 'vapidKey']);

// Branding
Route::get('/branding', [PublicController::class, 'branding']);

// Announcements (public)
Route::get('/announcements/active', [PublicController::class, 'activeAnnouncements']);

// Demo mode (public, no auth — by design for public demo)
Route::prefix('demo')->group(function () {
    Route::get('/status', [DemoController::class, 'status']);
    Route::get('/config', [DemoController::class, 'config']);
    // POST endpoints rate-limited: 2 per minute per IP (heavy DB operations)
    Route::middleware('throttle:demo-action')->group(function () {
        Route::post('/vote', [DemoController::class, 'vote']);
        Route::post('/reset', [DemoController::class, 'reset']);
    });
});

// Protected
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Auth (protected)
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Users — /me aliases for frontend compatibility (Rust edition uses /me)
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::get('/users/me', [AuthController::class, 'me']);
    Route::put('/users/me', [AuthController::class, 'updateMe']);
    Route::get('/users/me/export', [UserController::class, 'export']);

    // Feature flags — stub for frontend compatibility
    Route::get('/features', [PublicController::class, 'features']);
    Route::delete('/users/me/delete', [AuthController::class, 'deleteAccount']);

    // Lots
    Route::get('/lots', [LotController::class, 'index']);
    Route::post('/lots', [LotController::class, 'store']);
    Route::get('/lots/{id}', [LotController::class, 'show']);
    Route::put('/lots/{id}', [LotController::class, 'update']);
    Route::delete('/lots/{id}', [LotController::class, 'destroy']);
    Route::get('/lots/{id}/slots', [LotController::class, 'slots']);
    Route::get('/lots/{id}/occupancy', [LotController::class, 'occupancy']);
    Route::get('/lots/{id}/layout', [LotController::class, 'show']); // Layout is part of lot detail
    Route::put('/lots/{id}/layout', [LotController::class, 'update']);

    // Slots
    Route::post('/lots/{lotId}/slots', [SlotController::class, 'store']);
    Route::put('/lots/{lotId}/slots/{slotId}', [SlotController::class, 'update']);
    Route::delete('/lots/{lotId}/slots/{slotId}', [SlotController::class, 'destroy']);

    // Zones
    Route::get('/lots/{lotId}/zones', [ZoneController::class, 'index']);
    Route::post('/lots/{lotId}/zones', [ZoneController::class, 'store']);

    // Bookings
    Route::get('/bookings/recommendations', [RecommendationController::class, 'index']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::post('/bookings/quick', [BookingController::class, 'quickBook']);
    Route::post('/bookings/guest', [BookingController::class, 'guestBooking']);
    Route::post('/bookings/swap', [BookingController::class, 'swap']);
    Route::put('/bookings/{id}/notes', [BookingController::class, 'updateNotes']);

    // Recurring
    Route::get('/recurring-bookings', [RecurringBookingController::class, 'index']);
    Route::post('/recurring-bookings', [RecurringBookingController::class, 'store']);
    Route::put('/recurring-bookings/{id}', [RecurringBookingController::class, 'update']);
    Route::delete('/recurring-bookings/{id}', [RecurringBookingController::class, 'destroy']);

    // Absences (maps homeoffice + vacation to unified absences)
    Route::get('/homeoffice', [PublicController::class, 'homeoffice']);
    Route::post('/homeoffice/days', [AbsenceController::class, 'store']);
    Route::delete('/homeoffice/days/{id}', [AbsenceController::class, 'destroy']);
    Route::put('/homeoffice/pattern', [AbsenceController::class, 'update']);
    Route::get('/vacation', [AbsenceController::class, 'index']);
    Route::post('/vacation', [AbsenceController::class, 'store']);
    Route::delete('/vacation/{id}', [AbsenceController::class, 'destroy']);
    Route::get('/vacation/team', [TeamController::class, 'index']);
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::post('/absences', [AbsenceController::class, 'store']);
    Route::put('/absences/{id}', [AbsenceController::class, 'update']);
    Route::delete('/absences/{id}', [AbsenceController::class, 'destroy']);

    // Vehicles
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);

    // Team
    Route::get('/team', [TeamController::class, 'index']);

    // Admin — middleware enforces admin role at the routing layer (defense in depth)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Reports & stats
        Route::get('/stats', [AdminReportController::class, 'stats']);
        Route::get('/heatmap', [AdminReportController::class, 'heatmap']);
        Route::get('/users/export-csv', [AdminReportController::class, 'exportUsersCsv']);

        // Audit log
        Route::get('/audit-log', [AdminController::class, 'auditLog']);

        // Settings
        Route::get('/settings', [AdminSettingsController::class, 'getSettings']);
        Route::put('/settings', [AdminSettingsController::class, 'updateSettings']);
        Route::get('/settings/use-case', [AdminSettingsController::class, 'getUseCase']);

        // User management
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::post('/users/import', [AdminController::class, 'importUsers']);

        // Bookings
        Route::get('/bookings', [AdminController::class, 'bookings']);
        Route::patch('/bookings/{id}/cancel', [AdminController::class, 'cancelBooking']);
        Route::get('/guest-bookings', [AdminController::class, 'guestBookings']);
        Route::patch('/guest-bookings/{id}/cancel', [AdminController::class, 'cancelGuestBooking']);

        // Announcements
        Route::get('/announcements', [AdminAnnouncementController::class, 'announcements']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'createAnnouncement']);
        Route::put('/announcements/{id}', [AdminAnnouncementController::class, 'updateAnnouncement']);
        Route::delete('/announcements/{id}', [AdminAnnouncementController::class, 'deleteAnnouncement']);

        Route::get('/updates/check', [AdminController::class, 'updatesCheck']);

        // Credits management
        Route::put('/users/{id}/quota', [AdminCreditController::class, 'updateUserQuota']);
        Route::post('/users/{id}/credits', [AdminCreditController::class, 'grantCredits']);
        Route::get('/credits/transactions', [AdminCreditController::class, 'creditTransactions']);
        Route::post('/credits/refill-all', [AdminCreditController::class, 'refillAllCredits']);

        // Feature flags — stub for frontend compatibility
        Route::get('/features', [AdminController::class, 'features']);
        Route::put('/features', [AdminController::class, 'updateFeatures']);

        // System pulse / monitoring
        Route::get('/pulse', [PulseController::class, 'index']);
    });

    // Notifications
    Route::get('/notifications', [UserController::class, 'notifications']);
    Route::put('/notifications/{id}/read', [UserController::class, 'markNotificationRead']);

    // User preferences
    Route::get('/user/preferences', [UserController::class, 'preferences']);
    Route::put('/user/preferences', [UserController::class, 'updatePreferences']);
    Route::get('/user/stats', [UserController::class, 'stats']);
    Route::get('/user/credits', [UserController::class, 'credits']);
    Route::get('/user/favorites', [UserController::class, 'favorites']);
    Route::post('/user/favorites', [UserController::class, 'addFavorite']);
    Route::delete('/user/favorites/{slotId}', [UserController::class, 'removeFavorite']);

    // Calendar
    Route::get('/calendar', [BookingController::class, 'index']);

    // Push / Webhooks / QR
    Route::post('/push/subscribe', [MiscController::class, 'pushSubscribe']);
    Route::get('/webhooks', [MiscController::class, 'webhooks']);
    Route::post('/webhooks', [MiscController::class, 'createWebhook']);
    Route::put('/webhooks/{id}', [MiscController::class, 'updateWebhook']);
    Route::delete('/webhooks/{id}', [MiscController::class, 'deleteWebhook']);
    Route::post('/webhooks/{id}/test', [MiscController::class, 'testWebhook']);
    Route::get('/update/check', [PublicController::class, 'updateCheck']);

    // Translation management (overrides is public — see above)
    Route::get('/translations/proposals', [TranslationController::class, 'proposals']);
    Route::get('/translations/proposals/{id}', [TranslationController::class, 'showProposal']);
    Route::post('/translations/proposals', [TranslationController::class, 'createProposal']);
    Route::post('/translations/proposals/{id}/vote', [TranslationController::class, 'vote']);
    Route::put('/translations/proposals/{id}/review', [TranslationController::class, 'review']);
});

// ── New feature-parity routes ──────────────────────────────────────────────

// Health (no auth)
Route::get('/health', [PublicController::class, 'health']);
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);

// Impressum — public (DDG § 5 requires it to be freely accessible)
Route::get('/legal/impressum', [AdminSettingsController::class, 'publicImpress']);

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // iCal export
    Route::get('/user/calendar.ics', [UserController::class, 'calendarExport']);

    // Invoice (HTML, printer-friendly — use browser "Print → Save as PDF")
    Route::get('/bookings/{id}/invoice', [BookingInvoiceController::class, 'show']);

    // GDPR data export
    Route::get('/user/export', [UserController::class, 'exportData']);

    // Vehicle photos
    Route::post('/vehicles/{id}/photo', [VehicleController::class, 'uploadPhoto']);
    Route::get('/vehicles/{id}/photo', [VehicleController::class, 'servePhoto']);

    // City codes (no photo auth needed but put behind auth to avoid abuse)
    Route::get('/vehicles/city-codes', [VehicleController::class, 'cityCodes']);

    // Waitlist
    Route::get('/waitlist', [WaitlistController::class, 'index']);
    Route::post('/waitlist', [WaitlistController::class, 'store']);
    Route::delete('/waitlist/{id}', [WaitlistController::class, 'destroy']);

    // Admin CSV export
    Route::middleware('admin')->get('/admin/bookings/export', [AdminReportController::class, 'exportBookingsCsv']);
});

// ── Feature parity batch 2: system, auth, bookings, absences ──────────────

// System (public)
Route::get('/system/version', [SystemController::class, 'version']);
Route::get('/system/maintenance', [SystemController::class, 'maintenance']);

// Auth (public) — rate limited: 3 password resets per 15 min per IP
Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// Branding logo (public)
Route::get('/branding/logo', [AdminSettingsController::class, 'serveBrandingLogo']);

// Translation overrides (public — frontend needs runtime i18n patching without login)
Route::get('/translations/overrides', [TranslationController::class, 'overrides']);

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth (protected)
    Route::patch('/users/me/password', [AuthController::class, 'changePassword']);

    // Bookings
    Route::patch('/bookings/{id}', [BookingController::class, 'update']);
    Route::post('/bookings/{id}/checkin', [BookingController::class, 'checkin']);
    Route::get('/calendar/events', [BookingController::class, 'calendarEvents']);
    Route::get('/swap-requests', [BookingController::class, 'swapRequests']);
    Route::post('/bookings/{id}/swap-request', [BookingController::class, 'createSwapRequest']);
    Route::put('/swap-requests/{id}', [BookingController::class, 'respondSwapRequest']);

    // iCal import (absences + vacation)
    Route::post('/absences/import', [AbsenceController::class, 'importIcal']);
    Route::post('/vacation/import', [AbsenceController::class, 'importIcal']);

    // Absence pattern + team
    Route::get('/absences/pattern', [AbsenceController::class, 'getPattern']);
    Route::post('/absences/pattern', [AbsenceController::class, 'setPattern']);
    Route::get('/absences/team', [AbsenceController::class, 'teamAbsences']);
    Route::get('/vacation/team', [AbsenceController::class, 'teamAbsences']);

    // Team today
    Route::get('/team/today', [TeamController::class, 'today']);

    // Notifications: mark all read
    Route::post('/notifications/read-all', [UserController::class, 'markAllNotificationsRead']);

    // Push: unsubscribe
    Route::delete('/push/unsubscribe', [UserController::class, 'pushUnsubscribe']);

    // GDPR Art. 17 — Right to Erasure (anonymize, not hard-delete)
    Route::post('/users/me/anonymize', [UserController::class, 'anonymizeAccount']);

    // QR codes
    Route::get('/lots/{id}/qr', [LotController::class, 'qrCode']);
    Route::get('/lots/{lotId}/slots/{slotId}/qr', [LotController::class, 'slotQrCode']);

    // Admin: branding, privacy, reports, charts, settings, reset
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Settings (branding, privacy, impressum, auto-release, email, webhooks, reset)
        Route::get('/branding', [AdminSettingsController::class, 'getBranding']);
        Route::put('/branding', [AdminSettingsController::class, 'updateBranding']);
        Route::post('/branding/logo', [AdminSettingsController::class, 'uploadBrandingLogo']);
        Route::get('/privacy', [AdminSettingsController::class, 'getPrivacy']);
        Route::put('/privacy', [AdminSettingsController::class, 'updatePrivacy']);
        Route::get('/impressum', [AdminSettingsController::class, 'getImpress']);
        Route::put('/impressum', [AdminSettingsController::class, 'updateImpress']);
        Route::post('/reset', [AdminSettingsController::class, 'resetDatabase']);
        Route::get('/settings/auto-release', [AdminSettingsController::class, 'getAutoReleaseSettings']);
        Route::put('/settings/auto-release', [AdminSettingsController::class, 'updateAutoReleaseSettings']);
        Route::get('/settings/email', [AdminSettingsController::class, 'getEmailSettings']);
        Route::put('/settings/email', [AdminSettingsController::class, 'updateEmailSettings']);
        Route::get('/settings/webhooks', [AdminSettingsController::class, 'getWebhookSettings']);
        Route::put('/settings/webhooks', [AdminSettingsController::class, 'updateWebhookSettings']);

        // Reports
        Route::get('/reports', [AdminReportController::class, 'reports']);
        Route::get('/dashboard/charts', [AdminReportController::class, 'dashboardCharts']);

        // User/lot/slot management
        Route::patch('/slots/{id}', [AdminController::class, 'updateSlot']);
        Route::delete('/lots/{id}', [AdminController::class, 'deleteLot']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        // System pulse / monitoring
        Route::get('/pulse', [PulseController::class, 'index']);
    });
});
