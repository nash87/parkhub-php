<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiVersionHeader;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiVersionHeaderTest extends TestCase
{
    private function runMiddleware(array $headers = []): Response
    {
        $request = Request::create('/api/v1/test', 'GET');
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        $middleware = new ApiVersionHeader;

        return $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));
    }

    public function test_adds_api_version_header_to_response(): void
    {
        $response = $this->runMiddleware();
        $this->assertNotNull($response->headers->get('X-API-Version'));
    }

    public function test_default_api_version_is_1(): void
    {
        $response = $this->runMiddleware();
        $this->assertEquals('1', $response->headers->get('X-API-Version'));
    }

    public function test_supported_version_passes_without_warning(): void
    {
        $response = $this->runMiddleware(['X-API-Version' => '1']);
        $this->assertNull($response->headers->get('X-API-Version-Warning'));
    }

    public function test_unsupported_version_gets_warning_header(): void
    {
        $response = $this->runMiddleware(['X-API-Version' => '999']);
        $this->assertNotNull($response->headers->get('X-API-Version-Warning'));
    }
}
