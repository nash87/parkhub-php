<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce an absolute maximum session lifetime on authenticated requests.
 *
 * Laravel's default session lifetime is an *idle* timeout: every request
 * refreshes it, so a stolen cookie (or leaked Sanctum token) that keeps
 * getting used stays valid indefinitely. This middleware adds a hard cap
 * anchored to the initial authentication timestamp, independent of
 * activity — matching BSI TR-03107 and OWASP ASVS 3.3.2.
 *
 * Configuration:
 *   - `config('session.absolute_lifetime')` — minutes, default 1440 (24h)
 *   - `SESSION_ABSOLUTE_LIFETIME` env var
 *
 * Behaviour:
 *   - Skips unauthenticated requests (no user to cap).
 *   - On the first authenticated request, stamps `auth_at` into the session.
 *   - On every subsequent request, compares `now - auth_at` to the cap.
 *     If exceeded: invalidates the session, flushes tokens, returns 401 JSON.
 */
class EnforceAbsoluteSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            // No authenticated principal — nothing to cap. Let downstream
            // middleware (e.g. auth:sanctum) handle the 401 if required.
            return $next($request);
        }

        // Default cap is 24h (1440 min). Admin sessions get a tighter
        // 12h cap (720 min) per OWASP ASVS 3.3.2 + audit L-3 — admin
        // privileges merit a more aggressive re-auth interval.
        // Override via env: SESSION_ABSOLUTE_LIFETIME (default cap),
        // SESSION_ABSOLUTE_LIFETIME_ADMIN (admin-specific cap).
        $defaultMinutes = (int) config('session.absolute_lifetime', 1440);
        $adminMinutes = (int) config('session.absolute_lifetime_admin', 720);
        $isAdmin = method_exists($user, 'hasRole')
            ? ((bool) $user->hasRole('admin') || (bool) $user->hasRole('superadmin'))
            : (in_array($user->role ?? null, ['admin', 'superadmin'], true));
        $absoluteLifetimeMinutes = $isAdmin ? $adminMinutes : $defaultMinutes;
        $absoluteLifetimeSeconds = $absoluteLifetimeMinutes * 60;

        $session = $request->session();
        $now = now()->timestamp;
        /** @var int|null $authAt */
        $authAt = $session->get('auth_at');

        if ($authAt === null) {
            // First authenticated request — stamp the session.
            $session->put('auth_at', $now);

            return $next($request);
        }

        if (($now - $authAt) > $absoluteLifetimeSeconds) {
            // Hard cap exceeded — kill the session and revoke the Sanctum
            // token that was used on this request so a stolen cookie can't
            // be replayed. `currentAccessToken()` is always a
            // PersonalAccessToken for bearer-authenticated API requests.
            $session->flush();
            $session->invalidate();
            $user->currentAccessToken()->delete();

            return response()->json([
                'error' => 'SESSION_EXPIRED',
                'message' => 'Session expired',
            ], 401);
        }

        return $next($request);
    }
}
