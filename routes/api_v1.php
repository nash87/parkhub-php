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

// Auth
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

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
    Route::get('/users/me/export', [UserController::class, 'stats']);
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

Route::middleware('auth:sanctum')->group(function () {
    // iCal export
    Route::get('/user/calendar.ics', [\App\Http\Controllers\Api\UserController::class, 'calendarExport']);

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
