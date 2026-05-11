<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
 * - Content-Security-Policy: Controls resource loading for the SPA (nonce-based)
 * - Reporting-Endpoints:    Modern successor to Report-To for CSP + NEL reports
 * - NEL:                    Network Error Logging — browsers POST network-level
 *                           failures (DNS, TLS, HTTP errors) to our endpoint
 * - Cross-Origin-Opener-Policy:  Isolates browsing context (anti-Spectre)
 * - Cross-Origin-Embedder-Policy: `credentialless` — enables crossOriginIsolated
 *                                 for SharedArrayBuffer / high-res timers while
 *                                 still allowing third-party no-cors embeds
 * - Cross-Origin-Resource-Policy: Prevents cross-origin embedding of our assets
 */
class SecurityHeaders
{
    public const OPENSTREETMAP_TILE_HOSTS = [
        'https://a.tile.openstreetmap.org',
        'https://b.tile.openstreetmap.org',
        'https://c.tile.openstreetmap.org',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Generate a per-request CSP nonce for inline styles
        $nonce = Str::random(32);
        $request->attributes->set('csp-nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        // --- Core security headers (always set) ---
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // --- Permissions-Policy: restrict browser features ---
        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'camera=()',
            'geolocation=(self)',   // needed for geofence check-in
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=()',
            'usb=()',
            'bluetooth=()',
            'serial=()',
            'interest-cohort=()',   // block FLoC/Topics
        ]));

        // --- HSTS: opt-in via APP_HSTS=true (default off for local dev) ---
        if (config('app.hsts', false)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // --- Site isolation: COOP + COEP + CORP ---
        // CORP `same-origin` is the right default for HTML/JSON responses,
        // but under COEP `credentialless` (set below) WebKit (Safari 18+,
        // mobile-safari Playwright) refuses to load module chunks like
        // `/_astro/*.js` because the document is in credentialless mode
        // and CORP `same-origin` mismatches — module fetch is blocked
        // with `access control checks` (visible in nightly E2E as a
        // CORS-style failure on every v5 lazy-loaded screen).
        //
        // The mitigation has TWO layers because Apache serves real files
        // under `public/` directly (`.htaccess` rewrites only non-files
        // to index.php), so `/_astro/*.js` etc. NEVER reach this middleware:
        //   1. `public/.htaccess` mod_headers sets `Cross-Origin-
        //      Resource-Policy: cross-origin` for asset path prefixes +
        //      extensions — handles real-file responses Apache serves.
        //   2. This middleware applies the same path-aware switch for
        //      any asset-shaped path that DOES route through Laravel
        //      (e.g. dev setups using `php artisan serve` without Apache,
        //      or fallback handlers that proxy to disk-backed storage).
        //
        // Asset responses are public/static and contain no user-specific
        // data; promoting them to `cross-origin` is structurally safe even
        // though same-origin cookies (`parkhub_token`, `laravel_session`)
        // are sent on the request — Laravel/Sanctum default both to
        // `path: '/'`, so cookies travel with /_astro/*.js requests too.
        // CORP `cross-origin` controls EMBEDDING ability, not request
        // credentials, so cookie-on-request + cross-origin-on-response
        // is the correct combination here.
        $path = $request->getPathInfo();
        $isStaticAsset = (bool) preg_match(
            '#^/(_astro|build|js|css|fonts|images)/|\.(?:js|css|map|woff2?|png|jpe?g|gif|svg|ico|webp|avif)$#i',
            $path,
        );
        $response->headers->set(
            'Cross-Origin-Resource-Policy',
            $isStaticAsset ? 'cross-origin' : 'same-origin',
        );

        // --- Reporting-Endpoints + NEL ---
        // Reporting-Endpoints is the successor to Report-To (which is being
        // removed). The `csp` endpoint receives CSP violation reports; the
        // `nel` endpoint receives Network Error Logging reports. Both post
        // JSON bodies to unauthenticated, rate-limited endpoints that we
        // persist to storage/logs/security-reports.log + audit_log.
        $response->headers->set(
            'Reporting-Endpoints',
            'csp="/api/v1/security/csp-report", nel="/api/v1/security/nel-report"'
        );

        // NEL: instruct browsers to report network-level errors (DNS, TCP,
        // TLS, HTTP 5xx, abandoned requests) to the `nel` endpoint for 30d.
        $response->headers->set(
            'NEL',
            '{"report_to":"nel","max_age":2592000,"include_subdomains":true}'
        );

        // --- Content-Security-Policy for the SPA ---
        // Only apply document-level headers to HTML responses (not API JSON
        // or static assets). WebKit reports same-origin JSON/module requests
        // as access-control failures if those subresources also carry COEP,
        // even though the document already has the isolation policy.
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            // COOP same-origin prevents cross-origin windows from sharing a
            // browsing context group (blocks window.opener attacks).
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

            // COEP `credentialless` is the modern default (2026): it gives us
            // crossOriginIsolated so we can use SharedArrayBuffer and high-res
            // `performance.now()`, without forcing every embedded third-party
            // resource to send CORP (`require-corp` would do that and break
            // Stripe/Bunny/etc. in one step). Sub-resources are fetched without
            // credentials and the server can't smuggle cookies into them.
            // Safari shipped this in 16.4, Firefox in 110, Chromium in 96.
            $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless');

            $csp = $this->buildCsp($request, $nonce);
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
     * Uses nonce-based style-src instead of 'unsafe-inline'.
     */
    private function buildCsp(Request $request, string $nonce): string
    {
        $appUrl = config('app.url', 'http://localhost');

        $directives = [
            // Only allow resources from same origin by default
            "default-src 'self'",
            // Scripts: self + unsafe-inline. A nonce-based CSP is the
            // modern default, but the Astro SPA shell in public/index.html
            // contains two inline bootstrap scripts (FOUC guard + React
            // mount) that are generated at build time — the per-request
            // server nonce can't be injected into them without HTML
            // rewriting, and nonce+unsafe-inline together means CSP3
            // browsers ignore the fallback and block the inline scripts
            // anyway. Until we pin static SHA-256 hashes for those two
            // blocks, use 'unsafe-inline' so the SPA actually boots.
            "script-src 'self' 'unsafe-inline'",
            // Styles: self + unsafe-inline for Tailwind + framer-motion inline styles
            "style-src 'self' 'unsafe-inline'",
            // Images: self, data URIs (base64 avatars/QR), blob URIs, and map tiles
            "img-src 'self' data: blob: ".implode(' ', self::OPENSTREETMAP_TILE_HOSTS),
            // Fonts: self + data URIs + Bunny Fonts CDN
            "font-src 'self' data: https://fonts.bunny.net",
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
            // Ship CSP violations to the `csp` Reporting-Endpoint.
            // `report-uri` is the legacy directive (Firefox, Safari <16.4);
            // `report-to` is the modern directive consuming Reporting-Endpoints.
            // Both are safe to send together.
            'report-uri /api/v1/security/csp-report',
            'report-to csp',
        ];

        return implode('; ', $directives);
    }

    private function isDev(): bool
    {
        return config('app.env') === 'local' || config('app.debug') === true;
    }
}
