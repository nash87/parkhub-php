<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\TenantScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function runMiddleware(?User $user = null): Response
    {
        $request = Request::create('/api/v1/test', 'GET');
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        $middleware = new TenantScope;

        return $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));
    }

    public function test_passes_when_multi_tenant_module_disabled(): void
    {
        config(['modules.multi_tenant' => false]);
        $response = $this->runMiddleware();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_for_user_without_tenant(): void
    {
        config(['modules.multi_tenant' => true]);
        $user = User::factory()->create(['tenant_id' => null]);
        $response = $this->runMiddleware($user);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_for_unauthenticated_when_enabled(): void
    {
        config(['modules.multi_tenant' => true]);
        $response = $this->runMiddleware(null);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
