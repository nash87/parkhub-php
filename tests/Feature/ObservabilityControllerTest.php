<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Feature tests for the Web Vitals RUM ingest endpoint.
 *
 * Endpoint contract:
 *   - POST /api/observability/web-vitals
 *   - No auth.
 *   - 204 on success, 422 on validation failure, 429 when rate-limited.
 *   - Body is logged (not persisted) — assert the logger was called.
 */
class ObservabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset rate limiter between tests so the per-IP bucket from a
        // prior test doesn't leak into the next one.
        RateLimiter::clear('rum:web-vitals:127.0.0.1');
    }

    public function test_accepts_valid_web_vitals_beacon(): void
    {
        Log::spy();
        Log::shouldReceive('channel')
            ->with(config('logging.web_vitals_channel', 'stack'))
            ->andReturnSelf();

        $response = $this->postJson('/api/observability/web-vitals', [
            'name' => 'LCP',
            'value' => 1234.5,
            'rating' => 'good',
            'delta' => 1234.5,
            'id' => 'v3-1700000000000-1234567890123',
            'navigationType' => 'navigate',
            'path' => '/dashboard',
            'visibilityState' => 'visible',
            'userAgent' => 'Mozilla/5.0 (Test)',
            'timestamp' => '2026-04-26T12:00:00.000Z',
        ]);

        $response->assertStatus(204);
        Log::shouldHaveReceived('channel')->atLeast()->once();
        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context): bool => $message === 'rum.web_vitals'
                && $context['metric'] === 'LCP'
                && $context['value'] === 1234.5
                && $context['path'] === '/dashboard'
        )->once();
    }

    public function test_rejects_unknown_metric_name(): void
    {
        $response = $this->postJson('/api/observability/web-vitals', [
            'name' => 'NOT_A_METRIC',
            'value' => 1.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_missing_value(): void
    {
        $response = $this->postJson('/api/observability/web-vitals', [
            'name' => 'CLS',
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_non_numeric_value(): void
    {
        $response = $this->postJson('/api/observability/web-vitals', [
            'name' => 'INP',
            'value' => 'not-a-number',
        ]);

        $response->assertStatus(422);
    }

    public function test_endpoint_requires_no_authentication(): void
    {
        // No login. Should not 401.
        $response = $this->postJson('/api/observability/web-vitals', [
            'name' => 'TTFB',
            'value' => 100,
        ]);

        $response->assertStatus(204);
    }

    public function test_rate_limits_excessive_beacons(): void
    {
        // Burn the controller-level 60/min budget, then assert 429 on #61.
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/observability/web-vitals', [
                'name' => 'CLS',
                'value' => 0.01,
            ])->assertStatus(204);
        }

        $response = $this->postJson('/api/observability/web-vitals', [
            'name' => 'CLS',
            'value' => 0.01,
        ]);

        $response->assertStatus(429);
    }

    public function test_accepts_minimal_beacon_payload(): void
    {
        $response = $this->postJson('/api/observability/web-vitals', [
            'name' => 'FCP',
            'value' => 800,
        ]);

        $response->assertStatus(204);
    }
}
