<?php

/**
 * Bookings module routes (api/v1).
 * Loaded only when MODULE_BOOKINGS=true.
 */

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingInvoiceController;
use App\Http\Controllers\Api\RecommendationController;
use Illuminate\Support\Facades\Route;

// Protected booking routes
Route::middleware(['module:bookings', 'auth:sanctum', 'throttle:api'])->group(function () {
    // Literal paths MUST come before the /{id} catch-all, otherwise
    // Laravel matches /bookings/guest against /bookings/{id} and calls
    // BookingController@show with id='guest' which returns a 404.
    Route::get('/bookings/recommendations', [RecommendationController::class, 'index']);
    Route::get('/bookings/guest', [BookingController::class, 'listGuestBookings']);
    Route::post('/bookings/guest', [BookingController::class, 'guestBooking']);
    Route::delete('/bookings/guest/{id}', [BookingController::class, 'deleteGuestBooking']);
    Route::post('/bookings/quick', [BookingController::class, 'quickBook']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::put('/bookings/{id}/notes', [BookingController::class, 'updateNotes']);
    Route::patch('/bookings/{id}', [BookingController::class, 'update']);
    Route::post('/bookings/{id}/checkin', [BookingController::class, 'checkin']);
    Route::post('/bookings/{id}/extend', [BookingController::class, 'extend']);
    Route::get('/calendar', [BookingController::class, 'index']);
    Route::get('/calendar/events', [BookingController::class, 'calendarEvents']);

    // Invoice (both dot and slash notation for Rust API compatibility)
    Route::get('/bookings/{id}/invoice', [BookingInvoiceController::class, 'show']);
    Route::get('/bookings/{id}/invoice.pdf', [BookingInvoiceController::class, 'pdf']);
    Route::get('/bookings/{id}/invoice/pdf', [BookingInvoiceController::class, 'pdf']);

    // iCal feed
    Route::get('/bookings/ical', [BookingController::class, 'ical']);

    // Admin bookings
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/bookings', [AdminController::class, 'bookings']);
        Route::patch('/bookings/{id}/cancel', [AdminController::class, 'cancelBooking']);
        Route::get('/guest-bookings', [AdminController::class, 'guestBookings']);
        Route::patch('/guest-bookings/{id}/cancel', [AdminController::class, 'cancelGuestBooking']);
        Route::get('/bookings/export', [AdminReportController::class, 'exportBookingsCsv']);
        Route::post('/invoices/bulk', [BookingInvoiceController::class, 'bulkExport']);
    });
});
