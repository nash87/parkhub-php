<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

        // The cookie is encrypted by Laravel's EncryptCookies middleware.
        // Normally the middleware stack decrypts cookies before they reach
        // application code, so $request->cookie() returns the plaintext.
        // We keep a try/catch as a safety net in case the value is still
        // encrypted (e.g. middleware ordering edge cases) or was tampered with.
        $rawCookie = $request->cookie('parkhub_token');

        if (! $rawCookie) {
            return $next($request);
        }

        try {
            // If EncryptCookies already decrypted this, decryptString will
            // throw because the plaintext isn't valid ciphertext — fall back
            // to using the value as-is (already decrypted).
            $cookieToken = Crypt::decryptString($rawCookie);
        } catch (DecryptException) {
            // Value was already decrypted by EncryptCookies middleware or is
            // a plain Sanctum token format — use it directly.
            $cookieToken = $rawCookie;
        }

        if (! $cookieToken) {
            return $next($request);
        }

        // CSRF protection: cookie-based auth requires X-Requested-With to
        // prove the request originates from our SPA, not a cross-site form.
        // If the header is missing we simply do not inject the Bearer header
        // — downstream middleware (auth:sanctum) will then return its normal
        // 401 for protected routes, and public routes (/theme, /branding,
        // /translations/overrides) still work with anonymous fetch('/…').
        // Returning 403 here previously broke every page-mount fetch that
        // didn't set the header explicitly, including the global theme load
        // in App.tsx.
        if ($request->header('X-Requested-With') !== 'XMLHttpRequest') {
            return $next($request);
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
