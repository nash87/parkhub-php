<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_info_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health/info');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['version', 'php_version', 'laravel_version', 'environment', 'modules']]);
    }

    public function test_health_live_includes_uptime(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['status', 'uptime']]);
    }

    public function test_health_ready_checks_cache(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['status', 'database', 'cache', 'version']]);
    }

    public function test_health_info_requires_no_auth(): void
    {
        $this->getJson('/api/v1/health/info')->assertStatus(200);
    }

    public function test_health_detailed_alias_matches_info_contract(): void
    {
        $response = $this->getJson('/api/v1/health/detailed');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['version', 'build', 'php_version', 'laravel_version', 'environment', 'modules']]);
    }

    public function test_health_info_uses_canonical_module_slugs(): void
    {
        config([
            'modules.absence_approval' => true,
            'modules.api_versioning' => true,
            'modules.realtime' => true,
            'modules.dynamic_pricing' => true,
            'modules.multi_tenant' => true,
            'modules.push_notifications' => true,
        ]);

        $response = $this->getJson('/api/v1/health/info');
        $response->assertStatus(200);

        $modules = $response->json('data.modules');
        $this->assertIsArray($modules);
        $this->assertArrayHasKey('absence-approval', $modules);
        $this->assertArrayHasKey('dynamic-pricing', $modules);
        $this->assertArrayHasKey('multi-tenant', $modules);
        $this->assertArrayHasKey('api-versioning', $modules);
        $this->assertArrayHasKey('realtime', $modules);
        $this->assertArrayHasKey('push', $modules);
        $this->assertArrayHasKey('email', $modules);

        $this->assertArrayNotHasKey('absence_approval', $modules);
        $this->assertArrayNotHasKey('api_versioning', $modules);
        $this->assertArrayNotHasKey('broadcasting', $modules);
        $this->assertArrayNotHasKey('dynamic_pricing', $modules);
        $this->assertArrayNotHasKey('multi_tenant', $modules);
        $this->assertArrayNotHasKey('push_notifications', $modules);
        $this->assertArrayNotHasKey('websocket', $modules);
        $this->assertArrayNotHasKey('web_push', $modules);
    }
}
