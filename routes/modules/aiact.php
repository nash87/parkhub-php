<?php

/**
 * EU AI Act Art. 50 transparency module routes (api/v1).
 * Loaded only when MODULE_AIACT=true.
 *
 * Admin-only endpoints for reading and updating the allocation
 * transparency mode (algorithmic | fifo_only).
 */

use App\Http\Controllers\Api\AiActTransparencyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:aiact', 'auth:sanctum', 'throttle:api', 'admin'])
    ->prefix('admin/aiact')
    ->group(function () {
        Route::get('/transparency-mode', [AiActTransparencyController::class, 'getMode']);
        Route::put('/transparency-mode', [AiActTransparencyController::class, 'putMode']);
    });
