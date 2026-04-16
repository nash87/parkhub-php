<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Propagate x-request-id into every log line emitted during this request.
 *
 * Render's edge (and the Cloudflare POP in front of it) already attaches
 * x-request-id to inbound requests; upstream clients can also send their
 * own. If neither is present we mint a UUID v4 so every log line still
 * carries a correlation ID. The same value is echoed back on the
 * response as x-request-id, matching the rust server's behaviour.
 */
class RequestIdLogging
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('x-request-id') ?: (string) Str::uuid();

        // Make every Log::{info,warn,error,...} call in this request
        // carry the request_id key.
        Log::withContext(['request_id' => $requestId]);

        // Expose to the app via the request instance for handlers that
        // want to surface it (e.g. API error envelope).
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('x-request-id', $requestId);

        return $response;
    }
}
