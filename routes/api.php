<?php

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

// Public routes (no auth)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/setup/status', [SetupController::class, 'status']);
Route::post('/setup/init', [SetupController::class, 'init']);
Route::get('/public/occupancy', [PublicController::class, 'occupancy']);
Route::get('/public/display', [PublicController::class, 'display']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);

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

    // Zones
    Route::get('/lots/{lotId}/zones', [ZoneController::class, 'index']);
    Route::post('/lots/{lotId}/zones', [ZoneController::class, 'store']);
    Route::put('/lots/{lotId}/zones/{id}', [ZoneController::class, 'update']);
    Route::delete('/lots/{lotId}/zones/{id}', [ZoneController::class, 'destroy']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::post('/bookings/quick', [BookingController::class, 'quickBook']);
    Route::post('/bookings/guest', [BookingController::class, 'guestBooking']);
    Route::post('/bookings/swap', [BookingController::class, 'swap']);
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

    // Admin
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/heatmap', [AdminController::class, 'heatmap']);
        Route::get('/audit-log', [AdminController::class, 'auditLog']);
        Route::get('/announcements', [AdminController::class, 'announcements']);
        Route::post('/announcements', [AdminController::class, 'createAnnouncement']);
        Route::put('/announcements/{id}', [AdminController::class, 'updateAnnouncement']);
        Route::delete('/announcements/{id}', [AdminController::class, 'deleteAnnouncement']);
        Route::post('/users/import', [AdminController::class, 'importUsers']);
        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
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
});

// V1 compatibility routes (same endpoints as Rust edition)
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
