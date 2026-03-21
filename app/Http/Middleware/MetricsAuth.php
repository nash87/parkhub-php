<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MetricsAuth Middleware
 *
 * Protects the /api/metrics endpoint.
 * Allows access via:
 * 1. Bearer token matching METRICS_TOKEN env var (for Prometheus scraping)
 * 2. Authenticated admin user session (Sanctum)
 */
class MetricsAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('app.metrics_token');

        // Allow Prometheus scraping via a static bearer token
        if ($token && $request->bearerToken() === $token) {
            return $next($request);
        }

        // Allow authenticated admin users
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
