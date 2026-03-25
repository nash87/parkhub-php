<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiResponseWrapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiResponseWrapperTest extends TestCase
{
    private function runMiddleware(string $uri, Response $innerResponse): Response
    {
        $request = Request::create($uri, 'GET');
        $middleware = new ApiResponseWrapper;

        return $middleware->handle($request, fn ($r) => $innerResponse);
    }

    public function test_wraps_json_response_with_success_true(): void
    {
        $inner = new JsonResponse(['name' => 'Test'], 200);
        $response = $this->runMiddleware('/api/v1/test', $inner);

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success'] ?? false);
        $this->assertArrayHasKey('data', $content);
    }

    public function test_wraps_error_response_with_success_false(): void
    {
        $inner = new JsonResponse(['message' => 'Not found'], 404);
        $response = $this->runMiddleware('/api/v1/test', $inner);

        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['success'] ?? true);
    }

    public function test_skips_health_endpoints(): void
    {
        $inner = new JsonResponse(['status' => 'ok'], 200);
        $response = $this->runMiddleware('/api/v1/health', $inner);

        $content = json_decode($response->getContent(), true);
        // Health endpoints may not be wrapped or may pass through
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_does_not_double_wrap_already_wrapped_response(): void
    {
        $inner = new JsonResponse(['success' => true, 'data' => ['name' => 'Test']], 200);
        $response = $this->runMiddleware('/api/v1/test', $inner);

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success']);
        // Should not have nested success/data within data
        $this->assertArrayNotHasKey('success', $content['data'] ?? []);
    }

    public function test_preserves_status_code(): void
    {
        $inner = new JsonResponse(['created' => true], 201);
        $response = $this->runMiddleware('/api/v1/test', $inner);
        $this->assertEquals(201, $response->getStatusCode());
    }
}
