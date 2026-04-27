<?php

/**
 * Observability / RUM routes.
 *
 * Mounted from `bootstrap/app.php` via `withRouting(then: ...)` under the
 * `api` middleware group, prefix `api/`. The endpoint receives Web Vitals
 * beacons from `parkhub-web/src/lib/observability/webVitals.ts` (POSTed via
 * `navigator.sendBeacon`).
 *
 * No authentication — the beacon is fired from any browser session
 * (logged-in or anonymous). Rate-limiting (60/min/IP) is enforced inside
 * the controller; no Sanctum guard.
 *
 * Module-style file kept separate from `routes/api.php` so the observability
 * surface area stays self-contained and can be lifted/dropped without
 * touching the main API route map.
 */

use App\Http\Controllers\Api\ObservabilityController;
use Illuminate\Support\Facades\Route;

Route::prefix('observability')->group(function (): void {
    // Public ingest — no auth, controller-level rate limit.
    Route::post('/web-vitals', [ObservabilityController::class, 'webVitals']);
});
