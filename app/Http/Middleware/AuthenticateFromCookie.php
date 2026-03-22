<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate API requests from the httpOnly parkhub_token cookie.
 *
 * Precedence:
 *   1. Authorization: Bearer header  (standard Sanctum flow, untouched)
 *   2. parkhub_token cookie          (requires X-Requested-With header for CSRF)
 *
 * This middleware runs *before* auth:sanctum so it can inject the
 * Bearer header into the request, letting Sanctum handle the rest.
 */
class AuthenticateFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        // If a Bearer header is already present, let Sanctum handle it directly.
        if ($request->bearerToken()) {
            return $next($request);
        }

        $cookieToken = $request->cookie('parkhub_token');

        if (! $cookieToken) {
            return $next($request);
        }

        // CSRF protection: cookie-based auth requires this header to prove
        // the request originates from our SPA, not a cross-site form.
        if ($request->header('X-Requested-With') !== 'XMLHttpRequest') {
            return response()->json([
                'error' => 'CSRF_REQUIRED',
                'message' => 'Cookie-based authentication requires the X-Requested-With: XMLHttpRequest header.',
            ], 403);
        }

        // Validate the token exists in the database before injecting it.
        $accessToken = PersonalAccessToken::findToken($cookieToken);

        if (! $accessToken) {
            return $next($request);
        }

        // Inject the cookie token as a Bearer header so Sanctum picks it up.
        $request->headers->set('Authorization', 'Bearer '.$cookieToken);

        return $next($request);
    }
}
