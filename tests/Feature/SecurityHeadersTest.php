<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_include_core_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_api_responses_include_permissions_policy(): void
    {
        $response = $this->getJson('/api/v1/health');

        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        $this->assertNotNull($permissionsPolicy);
        $this->assertStringContainsString('camera=()', $permissionsPolicy);
        $this->assertStringContainsString('microphone=()', $permissionsPolicy);
        $this->assertStringContainsString('geolocation=(self)', $permissionsPolicy);
        $this->assertStringContainsString('payment=()', $permissionsPolicy);
        $this->assertStringContainsString('interest-cohort=()', $permissionsPolicy);
    }

    public function test_spa_csp_allows_map_tile_images(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type', ''));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);

        $imgSrc = collect(explode(';', $csp))
            ->map(fn (string $directive): string => trim($directive))
            ->first(fn (string $directive): bool => str_starts_with($directive, 'img-src '));

        $this->assertNotNull($imgSrc);
        foreach (SecurityHeaders::OPENSTREETMAP_TILE_HOSTS as $host) {
            $this->assertStringContainsString($host, $imgSrc);
        }
    }

    public function test_hsts_header_absent_by_default(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_header_present_when_enabled(): void
    {
        config(['app.hsts' => true]);

        $response = $this->getJson('/api/v1/health');

        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts);
    }

    public function test_health_endpoint_still_works(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_cors_config_has_no_wildcard_methods(): void
    {
        $cors = config('cors');

        $this->assertNotContains('*', $cors['allowed_methods']);
        $this->assertContains('GET', $cors['allowed_methods']);
        $this->assertContains('POST', $cors['allowed_methods']);
        $this->assertContains('DELETE', $cors['allowed_methods']);
    }

    public function test_cors_config_has_no_wildcard_headers(): void
    {
        $cors = config('cors');

        $this->assertNotContains('*', $cors['allowed_headers']);
        $this->assertContains('Authorization', $cors['allowed_headers']);
        $this->assertContains('Content-Type', $cors['allowed_headers']);
    }

    public function test_cors_config_has_no_wildcard_origins(): void
    {
        $cors = config('cors');

        $this->assertNotContains('*', $cors['allowed_origins']);
    }

    public function test_cors_exposes_rate_limit_headers(): void
    {
        $cors = config('cors');

        $this->assertContains('X-RateLimit-Limit', $cors['exposed_headers']);
        $this->assertContains('X-RateLimit-Remaining', $cors['exposed_headers']);
        $this->assertContains('Retry-After', $cors['exposed_headers']);
    }
}
