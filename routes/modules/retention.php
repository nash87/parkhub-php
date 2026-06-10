<?php

/**
 * Retention-policy module routes (api/v1).
 * Loaded only when MODULE_RETENTION=true.
 *
 * All endpoints are admin-only; the module gate provides a runtime
 * disable switch consistent with the rest of the module system.
 */

use App\Http\Controllers\Api\RetentionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:retention', 'auth:sanctum', 'throttle:api', 'admin'])
    ->prefix('admin/retention')
    ->group(function () {
        Route::get('/policies', [RetentionController::class, 'policies']);
        Route::put('/policies/{class}', [RetentionController::class, 'updatePolicy']);
        Route::post('/run', [RetentionController::class, 'run']);
        Route::get('/evidence', [RetentionController::class, 'evidence']);
    });
