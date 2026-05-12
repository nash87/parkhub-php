<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_readiness_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.database', 'ok')
            ->assertJsonStructure(['data' => ['status', 'database', 'version']]);
    }

    public function test_health_check_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_status_alias_matches_health_contract(): void
    {
        $response = $this->getJson('/api/v1/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_health_endpoints_require_no_auth(): void
    {
        // All health endpoints must work without authentication
        $this->getJson('/api/v1/health')->assertStatus(200);
        $this->getJson('/api/v1/health/live')->assertStatus(200);
        $this->getJson('/api/v1/health/ready')->assertStatus(200);
    }

    public function test_top_level_health_endpoint(): void
    {
        // The top-level /api/health is excluded from the response wrapper
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok');
    }
}
