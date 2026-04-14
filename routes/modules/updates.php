<?php

use App\Http\Controllers\Api\UpdateController;
use Illuminate\Support\Facades\Route;

// Admin update management (requires admin role)
Route::middleware(['auth:sanctum', 'admin', 'throttle:api'])->prefix('api/v1/admin/updates')->group(function () {
    Route::get('/check', [UpdateController::class, 'check']);
    Route::post('/apply', [UpdateController::class, 'apply']);
    Route::get('/history', [UpdateController::class, 'history']);
    Route::get('/releases', [UpdateController::class, 'releases']);
    Route::post('/rollback', [UpdateController::class, 'rollback']);
});
