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
 * - Permissions-Policy:     Restricts browser feature access
 * - Strict-Transport-Security: Forces HTTPS for configured duration
 * - Content-Security-Policy: Controls resource loading for the SPA
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // --- Core security headers (always set) ---
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // --- Permissions-Policy: deny everything the SPA doesn't need ---
        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=()',
            'usb=()',
            'interest-cohort=()',  // block FLoC/Topics
        ]));

        // --- HSTS: opt-in via APP_HSTS=true (default off for local dev) ---
        if (config('app.hsts', false)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // --- Content-Security-Policy for the SPA ---
        // Only apply CSP to HTML responses (not API JSON or static assets)
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            $csp = $this->buildCsp($request);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Prevent caching of authenticated API responses
        if ($request->is('api/*') && $request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    /**
     * Build CSP directives for the SPA frontend.
     */
    private function buildCsp(Request $request): string
    {
        $appUrl = config('app.url', 'http://localhost');

        $directives = [
            // Only allow resources from same origin by default
            "default-src 'self'",
            // Scripts: self only (no inline — the SPA is bundled)
            "script-src 'self'",
            // Styles: self only — Vite bundles CSS into JS; 'unsafe-inline' removed (issue #58).
            // In local dev, Vite HMR injects style tags, so we allow it only then.
            "style-src 'self'".($this->isDev() ? " 'unsafe-inline'" : ''),
            // Images: self, data URIs (base64 avatars), and HTTPS sources
            "img-src 'self' data: https:",
            // Fonts: self + Bunny Fonts CDN
            "font-src 'self' https://fonts.bunny.net",
            // API connections: self + configured app URL + Vite HMR websocket in dev
            "connect-src 'self' {$appUrl}".($this->isDev() ? ' ws://localhost:5173 ws://127.0.0.1:5173' : ''),
            // No iframes allowed
            "frame-ancestors 'none'",
            // Forms only submit to self
            "form-action 'self'",
            // Base URI locked to self (prevent base-tag hijacking)
            "base-uri 'self'",
            // Block all object/embed/applet
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }

    private function isDev(): bool
    {
        return config('app.env') === 'local' || config('app.debug') === true;
    }
}
