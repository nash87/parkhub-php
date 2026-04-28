<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Real-User-Monitoring (RUM) ingest endpoint.
 *
 * Receives Web Vitals beacons from `parkhub-web/src/lib/observability/webVitals.ts`
 * and writes them to Laravel's logging stack so they can be picked up by the
 * existing log shipper / Loki pipeline.
 *
 * Why a controller (not a queued job): Web Vitals beacons are tiny (<1 KB),
 * fire-and-forget, sent via `navigator.sendBeacon`. Keeping the work
 * synchronous + log-only avoids a queue dependency for a feature that must
 * tolerate the user navigating away mid-request. Promote to a queued job
 * (or NATS publish) when the volume justifies it.
 *
 * Spec: https://web.dev/vitals/, https://github.com/GoogleChrome/web-vitals
 */
class ObservabilityController extends Controller
{
    /**
     * POST /api/observability/web-vitals
     *
     * Accepts a single Web Vitals metric payload. Validated, rate-limited per
     * IP, and emitted to the `web-vitals` log channel as structured JSON.
     */
    public function webVitals(Request $request): JsonResponse|Response
    {
        // 60 beacons/min per IP — comfortably above the 5 core metrics +
        // re-fires triggered by SPA route changes, but tight enough to stop
        // a misconfigured client from flooding the log pipeline.
        $key = 'rum:web-vitals:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 60)) {
            return response()->json([
                'error' => 'RATE_LIMITED',
                'message' => 'Too many beacons. Try again later.',
            ], 429);
        }
        RateLimiter::hit($key, decaySeconds: 60);

        $validated = $request->validate([
            'name' => 'required|string|in:CLS,FCP,INP,LCP,TTFB,FID',
            'value' => 'required|numeric',
            'rating' => 'nullable|string|in:good,needs-improvement,poor',
            'delta' => 'nullable|numeric',
            'id' => 'nullable|string|max:128',
            'navigationType' => 'nullable|string|max:32',
            'path' => 'nullable|string|max:512',
            'visibilityState' => 'nullable|string|max:32',
            'userAgent' => 'nullable|string|max:512',
            'timestamp' => 'nullable|string|max:64',
        ]);

        // Strip any client-controlled UA/path values down to log-safe shape.
        Log::channel(config('logging.web_vitals_channel', 'stack'))->info('rum.web_vitals', [
            'metric' => $validated['name'],
            'value' => $validated['value'],
            'rating' => $validated['rating'] ?? null,
            'delta' => $validated['delta'] ?? null,
            'id' => $validated['id'] ?? null,
            'navigation_type' => $validated['navigationType'] ?? null,
            'path' => $validated['path'] ?? null,
            'visibility_state' => $validated['visibilityState'] ?? null,
            'user_agent' => $validated['userAgent'] ?? $request->userAgent(),
            'client_timestamp' => $validated['timestamp'] ?? null,
            'server_timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'request_id' => $request->headers->get('x-request-id'),
        ]);

        // 204 — beacon contract: no body, no caching. `sendBeacon()` ignores
        // the response anyway, but keep it minimal for the fetch fallback.
        return response()->noContent();
    }
}
