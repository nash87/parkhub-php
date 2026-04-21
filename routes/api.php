<?php

use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminCreditController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingSwapController;
use App\Http\Controllers\Api\GuestBookingController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\MiscController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\PulseController;
use App\Http\Controllers\Api\RecurringBookingController;
use App\Http\Controllers\Api\SetupController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\ZoneController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// Health check (no auth)
Route::get('/health', [PublicController::class, 'healthCheck']);
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health/detailed', [HealthController::class, 'info']);

// Public routes (no auth) — rate limited to prevent brute-force and registration spam
Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Password reset — tighter rate limit (3 per 15 min per IP)
Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('throttle:setup')->group(function () {
    Route::get('/setup/status', [SetupController::class, 'status']);
    Route::post('/setup/init', [SetupController::class, 'init']);
});
Route::get('/public/occupancy', [PublicController::class, 'occupancy']);
Route::get('/public/display', [PublicController::class, 'display']);

// Prometheus metrics (no auth — scraped by monitoring)
Route::get('/metrics', [MetricsController::class, 'index']);

// Public legal routes
Route::get('/legal/privacy', [PublicController::class, 'legalPrivacy']);
Route::get('/legal/impressum', [PublicController::class, 'legalImpressum']);

// Protected routes
Route::middleware([StartSession::class, 'auth:sanctum', 'session.absolute'])->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Lots
    Route::get('/lots', [LotController::class, 'index']);
    Route::post('/lots', [LotController::class, 'store']);
    Route::get('/lots/{id}', [LotController::class, 'show']);
    Route::put('/lots/{id}', [LotController::class, 'update']);
    Route::delete('/lots/{id}', [LotController::class, 'destroy']);
    Route::get('/lots/{id}/slots', [LotController::class, 'slots']);
    Route::get('/lots/{id}/occupancy', [LotController::class, 'occupancy']);

    // Slots
    Route::post('/lots/{lotId}/slots', [SlotController::class, 'store']);
    Route::put('/lots/{lotId}/slots/{slotId}', [SlotController::class, 'update']);
    Route::delete('/lots/{lotId}/slots/{slotId}', [SlotController::class, 'destroy']);

    // Zones (read: any user, mutations: admin only)
    Route::get('/lots/{lotId}/zones', [ZoneController::class, 'index']);
    Route::middleware('admin')->group(function () {
        Route::post('/lots/{lotId}/zones', [ZoneController::class, 'store']);
        Route::put('/lots/{lotId}/zones/{id}', [ZoneController::class, 'update']);
        Route::delete('/lots/{lotId}/zones/{id}', [ZoneController::class, 'destroy']);
    });

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::post('/bookings/quick', [BookingController::class, 'quickBook']);
    Route::post('/bookings/guest', [GuestBookingController::class, 'guestBooking']);
    Route::post('/bookings/swap', [BookingSwapController::class, 'swap']);
    Route::put('/bookings/{id}/notes', [BookingController::class, 'updateNotes']);

    // Recurring bookings
    Route::get('/recurring-bookings', [RecurringBookingController::class, 'index']);
    Route::post('/recurring-bookings', [RecurringBookingController::class, 'store']);
    Route::put('/recurring-bookings/{id}', [RecurringBookingController::class, 'update']);
    Route::delete('/recurring-bookings/{id}', [RecurringBookingController::class, 'destroy']);

    // Absences
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::post('/absences', [AbsenceController::class, 'store']);
    Route::put('/absences/{id}', [AbsenceController::class, 'update']);
    Route::delete('/absences/{id}', [AbsenceController::class, 'destroy']);

    // Admin — requires admin or superadmin role
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        // Reports & stats
        Route::get('/stats', [AdminReportController::class, 'stats']);
        Route::get('/heatmap', [AdminReportController::class, 'heatmap']);
        Route::get('/reports', [AdminReportController::class, 'reports']);
        Route::get('/dashboard-charts', [AdminReportController::class, 'dashboardCharts']);
        Route::get('/bookings/export-csv', [AdminReportController::class, 'exportBookingsCsv']);
        Route::post('/users/export-csv', [AdminReportController::class, 'exportUsersCsv']);

        // Audit log
        Route::get('/audit-log', [AdminController::class, 'auditLog']);

        // Announcements
        Route::get('/announcements', [AdminAnnouncementController::class, 'announcements']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'createAnnouncement']);
        Route::put('/announcements/{id}', [AdminAnnouncementController::class, 'updateAnnouncement']);
        Route::delete('/announcements/{id}', [AdminAnnouncementController::class, 'deleteAnnouncement']);

        // Settings
        Route::get('/settings', [AdminSettingsController::class, 'getSettings']);
        Route::put('/settings', [AdminSettingsController::class, 'updateSettings']);
        Route::get('/branding', [AdminSettingsController::class, 'getBranding']);
        Route::put('/branding', [AdminSettingsController::class, 'updateBranding']);
        Route::post('/branding/logo', [AdminSettingsController::class, 'uploadBrandingLogo']);
        Route::get('/privacy', [AdminSettingsController::class, 'getPrivacy']);
        Route::put('/privacy', [AdminSettingsController::class, 'updatePrivacy']);
        Route::get('/impressum', [AdminSettingsController::class, 'getImpressum']);
        Route::put('/impressum', [AdminSettingsController::class, 'updateImpressum']);
        Route::post('/database/reset', [AdminSettingsController::class, 'resetDatabase']);
        Route::get('/auto-release', [AdminSettingsController::class, 'getAutoReleaseSettings']);
        Route::put('/auto-release', [AdminSettingsController::class, 'updateAutoReleaseSettings']);
        Route::get('/email-settings', [AdminSettingsController::class, 'getEmailSettings']);
        Route::put('/email-settings', [AdminSettingsController::class, 'updateEmailSettings']);

        // User management
        Route::post('/users/import', [AdminController::class, 'importUsers']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        // Bookings
        Route::get('/bookings', [AdminController::class, 'bookings']);
        Route::delete('/bookings/{id}', [AdminController::class, 'cancelBooking']);

        // Lots & slots
        Route::delete('/lots/{id}', [AdminController::class, 'deleteLot']);
        Route::put('/slots/{id}', [AdminController::class, 'updateSlot']);

        // Credits
        Route::put('/users/{id}/quota', [AdminCreditController::class, 'updateUserQuota']);
        Route::post('/users/{id}/credits', [AdminCreditController::class, 'grantCredits']);
        Route::get('/credit-transactions', [AdminCreditController::class, 'creditTransactions']);
        Route::post('/credits/refill', [AdminCreditController::class, 'refillAllCredits']);

        // System pulse / monitoring
        Route::get('/pulse', [PulseController::class, 'index']);
    });

    // User
    Route::prefix('user')->group(function () {
        Route::get('/preferences', [UserController::class, 'preferences']);
        Route::put('/preferences', [UserController::class, 'updatePreferences']);
        Route::get('/stats', [UserController::class, 'stats']);
        Route::get('/favorites', [UserController::class, 'favorites']);
        Route::post('/favorites', [UserController::class, 'addFavorite']);
        Route::delete('/favorites/{slotId}', [UserController::class, 'removeFavorite']);
        Route::get('/notifications', [UserController::class, 'notifications']);
        Route::put('/notifications/{id}/read', [UserController::class, 'markNotificationRead']);
    });

    // Team
    Route::get('/team', [TeamController::class, 'index']);

    // Vehicles
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);

    // Push
    Route::post('/push/subscribe', [MiscController::class, 'pushSubscribe']);

    // Email
    Route::get('/email/settings', [MiscController::class, 'emailSettings']);
    Route::put('/email/settings', [MiscController::class, 'updateEmailSettings']);

    // QR
    Route::get('/qr/{bookingId}', [MiscController::class, 'qrCode']);

    // Webhooks
    Route::get('/webhooks', [MiscController::class, 'webhooks']);
    Route::post('/webhooks', [MiscController::class, 'createWebhook']);
    Route::put('/webhooks/{id}', [MiscController::class, 'updateWebhook']);
    Route::delete('/webhooks/{id}', [MiscController::class, 'deleteWebhook']);

    // Booking detail
    Route::get('/bookings/{id}', [BookingController::class, 'show']);

    // User export / calendar
    Route::get('/user/export', [UserController::class, 'export']);
    Route::get('/user/calendar-export', [UserController::class, 'calendarExport']);

    // Absence iCal import
    Route::post('/absences/import-ical', [AbsenceController::class, 'importIcal']);

    // Vehicle photos
    Route::get('/vehicles/{id}/photo', [VehicleController::class, 'servePhoto']);
    Route::post('/vehicles/{id}/photo', [VehicleController::class, 'uploadPhoto']);

    // Team today
    Route::get('/team/today', [TeamController::class, 'today']);

    // Active announcements
    Route::get('/announcements/active', [PublicController::class, 'activeAnnouncements']);

    // Waitlist
    Route::get('/waitlist', [WaitlistController::class, 'index']);
    Route::post('/waitlist', [WaitlistController::class, 'store']);
    Route::delete('/waitlist/{id}', [WaitlistController::class, 'destroy']);
});

// V1 compatibility routes (same endpoints as Rust edition)
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
