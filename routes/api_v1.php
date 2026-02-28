<?php
/**
 * API v1 routes — compatible with the Rust backend's endpoint structure.
 * All routes are prefixed with /api/v1 (set in bootstrap/app.php).
 */

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SetupController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RecurringBookingController;
use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\MiscController;
use Illuminate\Support\Facades\Route;

// Auth — rate limited: 10 attempts per minute per IP to prevent brute force
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/login',    [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/refresh',  [AuthController::class, 'refresh']);
});

// Setup
Route::get('/setup/status', [SetupController::class, 'status']);
Route::post('/setup', [SetupController::class, 'init']);
Route::post('/setup/change-password', function(\Illuminate\Http\Request $request) {
    $request->validate(['current_password' => 'required', 'new_password' => 'required|min:8']);
    $admin = \App\Models\User::where('role', 'admin')->first();
    if (!$admin || !\Illuminate\Support\Facades\Hash::check($request->current_password, $admin->password)) {
        return response()->json(['success' => false, 'error' => ['code' => 'INVALID_PASSWORD', 'message' => 'Current password is incorrect']], 401);
    }
    $admin->password = bcrypt($request->new_password);
    $admin->save();
    \App\Models\Setting::set('needs_password_change', 'false');
    $token = $admin->createToken('auth-token');
    return response()->json(['success' => true, 'data' => [
        'user' => $admin,
        'tokens' => ['access_token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => now()->addDays(7)->toISOString()],
    ]]);
});
Route::post('/setup/complete', function(\Illuminate\Http\Request $request) {
    \App\Models\Setting::set('setup_completed', 'true');
    if ($request->company_name) \App\Models\Setting::set('company_name', $request->company_name);
    if ($request->use_case) \App\Models\Setting::set('use_case', $request->use_case);
    return response()->json(['success' => true, 'data' => ['message' => 'Setup completed']]);
});

// Public
Route::get('/public/occupancy', [PublicController::class, 'occupancy']);
Route::get('/public/display', [PublicController::class, 'display']);

    // Branding
    Route::get('/branding', function() {
        $s = \App\Models\Setting::pluck('value', 'key')->toArray();
        return response()->json([
            'company_name' => $s['company_name'] ?? 'ParkHub',
            'primary_color' => $s['primary_color'] ?? '#d97706',
            'secondary_color' => $s['secondary_color'] ?? '#475569',
            'logo_url' => $s['logo_url'] ?? null,
            'favicon_url' => null,
            'login_background_color' => '#0f172a',
            'custom_css' => null,
        ]);
    });

// Announcements (public)
Route::get('/announcements/active', function() {
    $announcements = \App\Models\Announcement::where('active', true)
        ->where(function($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })
        ->orderBy('created_at', 'desc')
        ->get();
    return response()->json($announcements);
});

// Protected
Route::middleware('auth:sanctum')->group(function () {
    // Users
    Route::get('/users/me', [AuthController::class, 'me']);
    Route::put('/users/me', [AuthController::class, 'updateMe']);
    Route::get('/users/me/export', [UserController::class, 'exportData']);
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
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::post('/bookings/quick', [BookingController::class, 'quickBook']);
    Route::post('/bookings/guest', [BookingController::class, 'guestBooking']);
    Route::post('/bookings/swap', [BookingController::class, 'swap']);
    Route::put('/bookings/{id}/notes', [BookingController::class, 'updateNotes']);

    // Recurring
    Route::get('/recurring-bookings', [RecurringBookingController::class, 'index']);
    Route::post('/recurring-bookings', [RecurringBookingController::class, 'store']);
    Route::delete('/recurring-bookings/{id}', [RecurringBookingController::class, 'destroy']);

    // Absences (maps homeoffice + vacation to unified absences)
    Route::get('/homeoffice', function(\Illuminate\Http\Request $request) {
        $user = $request->user();
        // Return HomeofficeSettings format expected by Rust frontend
        return response()->json([
            'pattern' => ['weekdays' => []],
            'single_days' => \App\Models\Absence::where('user_id', $user->id)
                ->where('absence_type', 'homeoffice')
                ->get()
                ->map(fn($a) => ['id' => $a->id, 'date' => $a->start_date, 'reason' => $a->note]),
            'parkingSlot' => null,
        ]);
    });
    Route::post('/homeoffice/days', [AbsenceController::class, 'store']);
    Route::delete('/homeoffice/days/{id}', [AbsenceController::class, 'destroy']);
    Route::put('/homeoffice/pattern', [AbsenceController::class, 'update']);
    Route::get('/vacation', [AbsenceController::class, 'index']);
    Route::post('/vacation', [AbsenceController::class, 'store']);
    Route::delete('/vacation/{id}', [AbsenceController::class, 'destroy']);
    Route::get('/vacation/team', [TeamController::class, 'index']);
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::post('/absences', [AbsenceController::class, 'store']);
    Route::delete('/absences/{id}', [AbsenceController::class, 'destroy']);

    // Vehicles
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);

    // Team
    Route::get('/team', [TeamController::class, 'index']);

    // Admin
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::get('/admin/heatmap', [AdminController::class, 'heatmap']);
    Route::get('/admin/audit-log', [AdminController::class, 'auditLog']);
    Route::get('/admin/settings', [AdminController::class, 'getSettings']);
    Route::put('/admin/settings', [AdminController::class, 'updateSettings']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser']);
    Route::post('/admin/users/import', [AdminController::class, 'importUsers']);
    Route::get('/admin/bookings', [AdminController::class, 'bookings']);
    Route::patch('/admin/bookings/{id}/cancel', [AdminController::class, 'cancelBooking']);
    Route::get('/admin/announcements', [AdminController::class, 'announcements']);
    Route::post('/admin/announcements', [AdminController::class, 'createAnnouncement']);
    Route::put('/admin/announcements/{id}', [AdminController::class, 'updateAnnouncement']);
    Route::delete('/admin/announcements/{id}', [AdminController::class, 'deleteAnnouncement']);
    Route::get('/admin/updates/check', function() { return response()->json(['update_available' => false, 'current_version' => '1.0.0-php']); });



    // Notifications
    Route::get('/notifications', [UserController::class, 'notifications']);
    Route::put('/notifications/{id}/read', [UserController::class, 'markNotificationRead']);

    // User preferences
    Route::get('/user/preferences', [UserController::class, 'preferences']);
    Route::put('/user/preferences', [UserController::class, 'updatePreferences']);
    Route::get('/user/stats', [UserController::class, 'stats']);
    Route::get('/user/favorites', [UserController::class, 'favorites']);
    Route::post('/user/favorites', [UserController::class, 'addFavorite']);
    Route::delete('/user/favorites/{slotId}', [UserController::class, 'removeFavorite']);

    // Calendar
    Route::get('/calendar', [BookingController::class, 'index']);

    // Push / Webhooks / QR
    Route::post('/push/subscribe', [MiscController::class, 'pushSubscribe']);
    Route::get('/webhooks', [MiscController::class, 'webhooks']);
    Route::post('/webhooks', [MiscController::class, 'createWebhook']);
    Route::get('/update/check', function() { return response()->json(['update_available' => false, 'current_version' => '1.0.0']); });
});

// ── New feature-parity routes ──────────────────────────────────────────────

// Health (no auth)
Route::get('/health/live', [\App\Http\Controllers\Api\HealthController::class, 'live']);
Route::get('/health/ready', [\App\Http\Controllers\Api\HealthController::class, 'ready']);

// Impressum — public (DDG § 5 requires it to be freely accessible)
Route::get('/legal/impressum', [\App\Http\Controllers\Api\AdminController::class, 'publicImpress']);

Route::middleware('auth:sanctum')->group(function () {
    // iCal export
    Route::get('/user/calendar.ics', [\App\Http\Controllers\Api\UserController::class, 'calendarExport']);

    // Invoice (HTML, printer-friendly — use browser "Print → Save as PDF")
    Route::get('/bookings/{id}/invoice', [\App\Http\Controllers\Api\BookingInvoiceController::class, 'show']);

    // GDPR data export
    Route::get('/user/export', [\App\Http\Controllers\Api\UserController::class, 'exportData']);

    // Vehicle photos
    Route::post('/vehicles/{id}/photo', [\App\Http\Controllers\Api\VehicleController::class, 'uploadPhoto']);
    Route::get('/vehicles/{id}/photo',  [\App\Http\Controllers\Api\VehicleController::class, 'servePhoto']);

    // City codes (no photo auth needed but put behind auth to avoid abuse)
    Route::get('/vehicles/city-codes', [\App\Http\Controllers\Api\VehicleController::class, 'cityCodes']);

    // Waitlist
    Route::get('/waitlist',        [\App\Http\Controllers\Api\WaitlistController::class, 'index']);
    Route::post('/waitlist',       [\App\Http\Controllers\Api\WaitlistController::class, 'store']);
    Route::delete('/waitlist/{id}',[\App\Http\Controllers\Api\WaitlistController::class, 'destroy']);

    // Admin CSV export
    Route::get('/admin/bookings/export', [\App\Http\Controllers\Api\AdminController::class, 'exportBookingsCsv']);
});

// ── Feature parity batch 2: system, auth, bookings, absences ──────────────

use App\Http\Controllers\Api\SystemController;

// System (public)
Route::get('/system/version',     [SystemController::class, 'version']);
Route::get('/system/maintenance', [SystemController::class, 'maintenance']);

// Auth (public) — rate limited: 5 password resets per 15 min per IP
Route::middleware('throttle:5,15')->group(function () {
    Route::post('/auth/forgot-password', [\App\Http\Controllers\Api\AuthController::class, 'forgotPassword']);
});

// Branding logo (public)
Route::get('/branding/logo', [\App\Http\Controllers\Api\AdminController::class, 'serveBrandingLogo']);

Route::middleware('auth:sanctum')->group(function () {

    // Auth (protected)
    Route::patch('/users/me/password', [\App\Http\Controllers\Api\AuthController::class, 'changePassword']);

    // Bookings
    Route::patch('/bookings/{id}',              [BookingController::class, 'update']);
    Route::post('/bookings/{id}/checkin',       [BookingController::class, 'checkin']);
    Route::get('/calendar/events',              [BookingController::class, 'calendarEvents']);
    Route::post('/bookings/{id}/swap-request',  [BookingController::class, 'createSwapRequest']);
    Route::put('/swap-requests/{id}',           [BookingController::class, 'respondSwapRequest']);

    // iCal import (absences + vacation)
    Route::post('/absences/import',  [AbsenceController::class, 'importIcal']);
    Route::post('/vacation/import',  [AbsenceController::class, 'importIcal']);

    // Absence pattern + team
    Route::get('/absences/pattern',  [AbsenceController::class, 'getPattern']);
    Route::post('/absences/pattern', [AbsenceController::class, 'setPattern']);
    Route::get('/absences/team',     [AbsenceController::class, 'teamAbsences']);
    Route::get('/vacation/team',     [AbsenceController::class, 'teamAbsences']);

    // Team today
    Route::get('/team/today', [TeamController::class, 'today']);

    // Notifications: mark all read
    Route::post('/notifications/read-all', [UserController::class, 'markAllNotificationsRead']);

    // Push: unsubscribe
    Route::delete('/push/unsubscribe', [UserController::class, 'pushUnsubscribe']);

    // GDPR Art. 17 — Right to Erasure (anonymize, not hard-delete)
    Route::post('/users/me/anonymize', [UserController::class, 'anonymizeAccount']);

    // QR codes
    Route::get('/lots/{id}/qr',                   [LotController::class, 'qrCode']);
    Route::get('/lots/{lotId}/slots/{slotId}/qr',  [LotController::class, 'slotQrCode']);

    // Admin: branding, privacy, reports, charts, settings, reset
    Route::get('/admin/branding',          [AdminController::class, 'getBranding']);
    Route::put('/admin/branding',          [AdminController::class, 'updateBranding']);
    Route::post('/admin/branding/logo',    [AdminController::class, 'uploadBrandingLogo']);
    Route::get('/admin/privacy',           [AdminController::class, 'getPrivacy']);
    Route::put('/admin/privacy',           [AdminController::class, 'updatePrivacy']);

    // Impressum admin editor (DDG § 5 fields)
    Route::get('/admin/impressum',         [AdminController::class, 'getImpress']);
    Route::put('/admin/impressum',         [AdminController::class, 'updateImpress']);
    Route::get('/admin/reports',           [AdminController::class, 'reports']);
    Route::get('/admin/dashboard/charts',  [AdminController::class, 'dashboardCharts']);
    Route::post('/admin/reset',            [AdminController::class, 'resetDatabase']);
    Route::get('/admin/settings/auto-release',  [AdminController::class, 'getAutoReleaseSettings']);
    Route::put('/admin/settings/auto-release',  [AdminController::class, 'updateAutoReleaseSettings']);
    Route::get('/admin/settings/email',    [AdminController::class, 'getEmailSettings']);
    Route::put('/admin/settings/email',    [AdminController::class, 'updateEmailSettings']);
    Route::get('/admin/settings/webhooks', [AdminController::class, 'getWebhookSettings']);
    Route::put('/admin/settings/webhooks', [AdminController::class, 'updateWebhookSettings']);
    Route::patch('/admin/slots/{id}',      [AdminController::class, 'updateSlot']);
    Route::delete('/admin/lots/{id}',      [AdminController::class, 'deleteLot']);
    Route::delete('/admin/users/{id}',     [AdminController::class, 'deleteUser']);
});
