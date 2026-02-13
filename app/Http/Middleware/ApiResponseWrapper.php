<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseWrapper
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only wrap JSON responses from API routes
        if (!$request->is('api/*') || !$response->headers->contains('Content-Type', 'application/json')) {
            // Try to detect JSON responses
            $content = $response->getContent();
            if (empty($content) || !$this->isJson($content)) {
                return $response;
            }
        }
        
        $content = $response->getContent();
        if (!$this->isJson($content)) {
            return $response;
        }
        
        $data = json_decode($content, true);
        $status = $response->getStatusCode();
        
        // Don't double-wrap if already in our format
        if (isset($data['success'])) {
            return $response;
        }
        
        // Health endpoint - don't wrap
        if ($request->is('health') || $request->is('api/health')) {
            return $response;
        }
        
        if ($status >= 200 && $status < 300) {
            // Success response
            $wrapped = [
                'success' => true,
                'data' => $data,
                'error' => null,
                'meta' => null,
            ];
        } else {
            // Error response
            $message = $data['message'] ?? $data['error'] ?? 'Unknown error';
            $code = $data['error'] ?? 'ERROR';
            $wrapped = [
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => is_string($code) ? $code : 'ERROR',
                    'message' => $message,
                ],
                'meta' => null,
            ];
        }
        
        $response->setContent(json_encode($wrapped));
        $response->headers->set('Content-Type', 'application/json');
        
        return $response;
    }
    
    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
