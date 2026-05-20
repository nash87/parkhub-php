<?php

/**
 * Recommendations module routes (api/v1).
 * Loaded only when MODULE_RECOMMENDATIONS=true.
 */

use App\Http\Controllers\Api\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:recommendations', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::get('/recommendations/stats', [RecommendationController::class, 'stats']);
    Route::post('/recommendations/allocation/exact-cover', [RecommendationController::class, 'exactCoverAllocation']);
});
