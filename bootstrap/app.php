<?php

use App\Http\Middleware\ApiResponseWrapper;
use App\Http\Middleware\ApiVersionHeader;
use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\CheckModule;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RequestIdLogging;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Request correlation ID — mirrors the rust server's x-request-id
        // plumbing. Must run first so every downstream log line + the
        // SecurityHeaders response echo can see the same ID.
        $middleware->prepend(RequestIdLogging::class);

        // Security headers on every response (web + API)
        $middleware->append(SecurityHeaders::class);

        // Trust every upstream proxy and honour the full X-Forwarded-*
        // header set. Render, Cloudflare, and Fly.io all terminate TLS
        // upstream, so without this Laravel sees every request as plain
        // HTTP and $request->isSecure() returns false — which in turn
        // makes authCookie() emit a non-Secure cookie on HTTPS and would
        // make `redirect()->secure()` silently downgrade.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // All cookies are encrypted — including parkhub_token. The
        // AuthenticateFromCookie middleware decrypts it before use.

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            AuthenticateFromCookie::class,
            ApiResponseWrapper::class,
            ApiVersionHeader::class,
        ]);

        $middleware->alias([
            'admin' => RequireAdmin::class,
            'module' => CheckModule::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry-compatible error tracking (works against Sentry.io or a
        // self-hosted GlitchTip). Opts in via SENTRY_LARAVEL_DSN env var;
        // a no-op when unset, so local dev and the AGPL release pay no
        // cost. Registers a reporter so any uncaught exception is shipped
        // before Laravel's render pipeline runs.
        \Sentry\Laravel\Integration::handles($exceptions);

        // Consistent JSON error responses for API routes
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'NOT_FOUND',
                    'message' => $e->getMessage() ?: 'The requested endpoint does not exist.',
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'FORBIDDEN',
                    'message' => $e->getMessage() ?: 'You do not have permission to perform this action.',
                ], 403);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'METHOD_NOT_ALLOWED',
                    'message' => 'The HTTP method is not supported for this endpoint.',
                ], 405);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

                return response()->json([
                    'error' => 'RATE_LIMITED',
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => $retryAfter,
                ], 429);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
