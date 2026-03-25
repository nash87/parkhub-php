<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckModule;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CheckModuleTest extends TestCase
{
    private function runMiddleware(string $module): Response
    {
        $request = Request::create('/api/v1/test', 'GET');
        $middleware = new CheckModule;

        return $middleware->handle($request, fn ($r) => response()->json(['ok' => true]), $module);
    }

    public function test_enabled_module_passes(): void
    {
        config(['modules.bookings' => true]);
        $response = $this->runMiddleware('bookings');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_disabled_module_returns_404(): void
    {
        config(['modules.fleet' => false]);
        $response = $this->runMiddleware('fleet');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_disabled_module_returns_module_disabled_error(): void
    {
        config(['modules.fleet' => false]);
        $response = $this->runMiddleware('fleet');
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('MODULE_DISABLED', $content['error'] ?? '');
    }

    public function test_unknown_module_defaults_to_disabled(): void
    {
        $response = $this->runMiddleware('nonexistent_module_xyz');
        $this->assertEquals(404, $response->getStatusCode());
    }
}
