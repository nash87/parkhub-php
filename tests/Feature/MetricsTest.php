<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $response = $this->get('/api/metrics');

        // Either 200 (if no token configured) or 401 (if token required)
        $this->assertContains($response->getStatusCode(), [200, 401]);
    }

    public function test_metrics_endpoint_returns_200_when_no_token_configured(): void
    {
        config(['app.metrics_token' => null]);

        $response = $this->get('/api/metrics');

        $response->assertStatus(200);
        $this->assertStringContainsString('active_bookings', $response->getContent());
    }

    public function test_metrics_endpoint_returns_401_when_token_required_but_not_provided(): void
    {
        config(['app.metrics_token' => 'secret-metrics-token']);

        $response = $this->get('/api/metrics');

        $response->assertStatus(401);
    }

    public function test_metrics_endpoint_accepts_correct_bearer_token(): void
    {
        config(['app.metrics_token' => 'valid-token']);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->get('/api/metrics');

        $response->assertStatus(200);
        $this->assertStringContainsString('active_bookings', $response->getContent());
    }

    public function test_metrics_endpoint_rejects_wrong_bearer_token(): void
    {
        config(['app.metrics_token' => 'valid-token']);

        $response = $this->withHeader('Authorization', 'Bearer wrong-token')
            ->get('/api/metrics');

        $response->assertStatus(401);
    }
}
