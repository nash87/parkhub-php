<?php

/**
 * No-show auto-release + waitlist auto-promotion module routes (api/v1).
 * Loaded only when MODULE_NOSHOW_WAITLIST=true.
 *
 * FIFO promotion order: priority ASC, then created_at ASC.
 */

use App\Http\Controllers\Api\BookingCheckInController;
use App\Http\Controllers\Api\WaitlistOfferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:noshow_waitlist', 'auth:sanctum', 'throttle:api'])->group(function () {
    // Idempotent check-in (hyphenated path per API contract)
    Route::post('/bookings/{id}/check-in', [BookingCheckInController::class, 'checkInAction']);

    // Waitlist offer endpoints
    Route::get('/waitlist/offers', [WaitlistOfferController::class, 'index']);
    Route::post('/waitlist/offers/{id}/claim', [WaitlistOfferController::class, 'claim']);
});
