<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 *
 * Adds security-related HTTP response headers to every request.
 * Applied globally via bootstrap/app.php.
 *
 * Headers set:
 * - X-Content-Type-Options: Prevents MIME sniffing attacks
 * - X-Frame-Options:        Prevents clickjacking (embedding in iframes)
 * - X-XSS-Protection:       Legacy XSS filter for older browsers
 * - Referrer-Policy:        Controls how much referrer info is sent
 *
 * Note: CSP and HSTS are intentionally not set here — they must be
 * configured per-deployment in the reverse proxy (nginx/Apache/Caddy)
 * to match the operator's specific origin and TLS termination setup.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
