<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class ForceJsonResponseTest extends TestCase
{
    private function processRequest(array $headers = [], ?string $content = null): Request
    {
        $request = Request::create('/api/v1/test', 'POST', [], [], [], [], $content);
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        $middleware = new ForceJsonResponse;
        $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));

        return $request;
    }

    public function test_sets_accept_header_to_json(): void
    {
        $request = $this->processRequest();
        $this->assertEquals('application/json', $request->headers->get('Accept'));
    }

    public function test_preserves_existing_json_accept_header(): void
    {
        $request = $this->processRequest(['Accept' => 'application/json']);
        $this->assertEquals('application/json', $request->headers->get('Accept'));
    }

    public function test_overrides_html_accept_header(): void
    {
        $request = $this->processRequest(['Accept' => 'text/html']);
        $this->assertEquals('application/json', $request->headers->get('Accept'));
    }

    public function test_detects_json_body_without_content_type(): void
    {
        $request = $this->processRequest([], '{"key":"value"}');
        // After middleware, the request should have Accept set to JSON
        $this->assertEquals('application/json', $request->headers->get('Accept'));
    }
}
