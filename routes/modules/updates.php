<?php

use App\Http\Controllers\Api\UpdateController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// Admin update management (requires admin role)
Route::middleware([StartSession::class, 'module:updates', 'auth:sanctum', 'admin', 'throttle:api', 'session.absolute'])->prefix('admin/updates')->group(function () {
    Route::get('/check', [UpdateController::class, 'check']);
    Route::post('/apply', [UpdateController::class, 'apply']);
    Route::get('/history', [UpdateController::class, 'history']);
    Route::get('/releases', [UpdateController::class, 'releases']);
    Route::post('/rollback', [UpdateController::class, 'rollback']);
});
