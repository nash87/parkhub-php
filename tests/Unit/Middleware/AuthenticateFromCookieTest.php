<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\AuthenticateFromCookie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthenticateFromCookieTest extends TestCase
{
    use RefreshDatabase;

    private function runMiddleware(array $cookies = [], array $headers = []): Response
    {
        $request = Request::create('/api/v1/test', 'GET');
        foreach ($cookies as $key => $value) {
            $request->cookies->set($key, $value);
        }
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        $middleware = new AuthenticateFromCookie;

        return $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));
    }

    public function test_passes_through_without_cookie(): void
    {
        $response = $this->runMiddleware();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_through_when_bearer_token_present(): void
    {
        $response = $this->runMiddleware([], ['Authorization' => 'Bearer some-token']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_returns_403_when_cookie_present_without_xhr_header(): void
    {
        // Cookie present but no X-Requested-With header — CSRF protection kicks in
        $response = $this->runMiddleware(['parkhub_token' => 'cookie-token']);
        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('CSRF_REQUIRED', $content['error']);
    }

    public function test_injects_bearer_from_cookie_with_xhr_header(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $request = Request::create('/api/v1/test', 'GET');
        $request->cookies->set('parkhub_token', $token);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $middleware = new AuthenticateFromCookie;

        $middleware->handle($request, function ($r) use ($token) {
            // After middleware, the Authorization header should be set
            $this->assertEquals('Bearer '.$token, $r->headers->get('Authorization'));

            return response()->json(['ok' => true]);
        });
    }

    public function test_passes_through_when_cookie_token_invalid(): void
    {
        // Invalid token with XHR header — should pass through without setting Authorization
        $response = $this->runMiddleware(
            ['parkhub_token' => 'invalid-nonexistent-token'],
            ['X-Requested-With' => 'XMLHttpRequest']
        );
        $this->assertEquals(200, $response->getStatusCode());
    }
}
