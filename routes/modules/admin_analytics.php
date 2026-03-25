<?php

/**
 * Admin analytics module routes (api/v1).
 * Loaded only when MODULE_ADMIN_ANALYTICS=true.
 */

use App\Http\Controllers\Api\AdminAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:admin_analytics', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/analytics')->group(function () {
    Route::get('/occupancy', [AdminAnalyticsController::class, 'occupancy']);
    Route::get('/revenue', [AdminAnalyticsController::class, 'revenue']);
    Route::get('/popular-lots', [AdminAnalyticsController::class, 'popularLots']);
});
