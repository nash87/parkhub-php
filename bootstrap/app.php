<?php

use App\Http\Middleware\ApiResponseWrapper;
use App\Http\Middleware\ApiVersionHeader;
use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\CheckModule;
use App\Http\Middleware\ForceJsonResponse;
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
        // Security headers on every response (web + API)
        $middleware->append(SecurityHeaders::class);

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
