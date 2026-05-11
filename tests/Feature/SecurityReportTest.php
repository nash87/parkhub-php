<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Covers the browser-generated security report endpoints wired up by
 * T-1749: /api/v1/security/csp-report + /api/v1/security/nel-report.
 */
class SecurityReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The named rate limiter is keyed by IP — make sure every test
        // starts from a clean slate, otherwise earlier test traffic on
        // the same IP (127.0.0.1) can poison this run.
        RateLimiter::clear('10:1|127.0.0.1');
    }

    public function test_csp_report_with_valid_body_returns_204(): void
    {
        $response = $this->postJson('/api/v1/security/csp-report', [
            'body' => [
                'csp-report' => [
                    'document-uri' => 'https://parkhub.example/app',
                    'violated-directive' => "script-src 'self'",
                    'blocked-uri' => 'https://evil.example/x.js',
                ],
            ],
        ]);

        $response->assertStatus(204);
        $this->assertDatabaseHas('audit_log', ['action' => 'csp_violation']);
    }

    public function test_nel_report_with_valid_body_returns_204(): void
    {
        $response = $this->postJson('/api/v1/security/nel-report', [
            'body' => [
                'type' => 'network-error',
                'url' => 'https://parkhub.example/assets/app.js',
                'server_ip' => '203.0.113.5',
                'protocol' => 'http/1.1',
                'status_code' => 0,
                'phase' => 'connection',
            ],
        ]);

        $response->assertStatus(204);
        $this->assertDatabaseHas('audit_log', ['action' => 'nel_report']);
    }

    public function test_csp_report_without_body_returns_422(): void
    {
        $response = $this->postJson('/api/v1/security/csp-report', []);

        $response->assertStatus(422);
    }

    public function test_nel_report_without_body_returns_422(): void
    {
        $response = $this->postJson('/api/v1/security/nel-report', []);

        $response->assertStatus(422);
    }

    public function test_csp_report_rate_limited_after_10_requests_per_minute(): void
    {
        $hitLimit = false;
        $successes = 0;

        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson('/api/v1/security/csp-report', [
                'body' => ['csp-report' => ['i' => $i]],
            ]);

            if ($response->getStatusCode() === 429) {
                $hitLimit = true;
                break;
            }

            if ($response->getStatusCode() === 204) {
                $successes++;
            }
        }

        $this->assertTrue($hitLimit, 'Expected 429 after 10 requests/min — got all 204s');
        $this->assertLessThanOrEqual(10, $successes, 'More than 10 requests slipped through the limiter');
    }

    public function test_security_response_headers_include_reporting_endpoints_and_nel(): void
    {
        $response = $this->getJson('/api/v1/health');

        $reporting = $response->headers->get('Reporting-Endpoints');
        $this->assertNotNull($reporting);
        $this->assertStringContainsString('csp="/api/v1/security/csp-report"', $reporting);
        $this->assertStringContainsString('nel="/api/v1/security/nel-report"', $reporting);

        $nel = $response->headers->get('NEL');
        $this->assertNotNull($nel);
        $this->assertStringContainsString('"report_to":"nel"', $nel);
        $this->assertStringContainsString('"max_age":2592000', $nel);
        $this->assertStringContainsString('"include_subdomains":true', $nel);

        $this->assertNull($response->headers->get('Cross-Origin-Embedder-Policy'));
    }

    public function test_audit_log_persists_full_payload(): void
    {
        $payload = ['body' => ['csp-report' => ['blocked-uri' => 'https://evil.example']]];

        $this->postJson('/api/v1/security/csp-report', $payload)->assertStatus(204);

        /** @var AuditLog|null $entry */
        $entry = AuditLog::where('action', 'csp_violation')->latest()->first();
        $this->assertNotNull($entry);
        $this->assertSame('security_report', $entry->event_type);
        $this->assertSame($payload, $entry->details);
    }
}
