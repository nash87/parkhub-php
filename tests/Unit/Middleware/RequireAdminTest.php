<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RequireAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RequireAdminTest extends TestCase
{
    use RefreshDatabase;

    private function runMiddleware(?User $user = null): Response
    {
        $request = Request::create('/api/admin/test', 'GET');
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        $middleware = new RequireAdmin;

        return $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));
    }

    public function test_admin_user_passes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $response = $this->runMiddleware($user);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_superadmin_user_passes(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        $response = $this->runMiddleware($user);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_regular_user_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $response = $this->runMiddleware($user);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_premium_user_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'premium']);
        $response = $this->runMiddleware($user);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->runMiddleware(null);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_rejection_contains_error_message(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $response = $this->runMiddleware($user);
        $content = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Administrator', $content['error'] ?? '');
    }
}
