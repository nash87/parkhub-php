<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The GDPR audit-trail export is the artifact an auditor asks for.
 *
 * `ComplianceService` queried a table named `audit_logs`; the real table is
 * `audit_log`, and its columns are `target_type` / `target_id`, not
 * `resource_type` / `resource_id`. The table check therefore always failed,
 * `auditLogs()` always returned null, and the controller mapped null to
 * `{"success": true, "logs": [], "count": 0}` with HTTP 200 — a permanently
 * empty export that reports success. The compliance report separately told
 * every operator they were non-compliant on the one control they did have.
 */
class ComplianceAuditExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return [$admin, $admin->createToken('test')->plainTextToken];
    }

    private function seedAuditEntry(User $actor): AuditLog
    {
        return AuditLog::create([
            'user_id' => $actor->id,
            'username' => $actor->username,
            'action' => 'booking_cancelled',
            'target_type' => 'booking',
            'target_id' => 'booking-123',
            'ip_address' => '198.51.100.7',
            'details' => ['reason' => 'test'],
        ]);
    }

    public function test_audit_export_returns_the_recorded_entries(): void
    {
        [$admin, $token] = $this->admin();
        $this->seedAuditEntry($admin);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/compliance/audit-export')
            ->assertOk();

        $this->assertGreaterThan(
            0,
            $response->json('data.count'),
            'the audit export returned no rows even though the audit log has entries',
        );
    }

    public function test_audit_export_csv_carries_the_target_columns(): void
    {
        [$admin, $token] = $this->admin();
        $this->seedAuditEntry($admin);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/compliance/audit-export?format=csv')
            ->assertOk();

        $csv = (string) $response->json('data.content');

        $this->assertStringContainsString('booking_cancelled', $csv);
        $this->assertStringContainsString('booking-123', $csv, 'the target id is missing from the export');
        $this->assertStringContainsString('198.51.100.7', $csv);
    }

    public function test_compliance_report_recognises_the_audit_trail(): void
    {
        [$admin, $token] = $this->admin();
        $this->seedAuditEntry($admin);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/compliance/report')
            ->assertOk();

        $json = json_encode($response->json());

        $this->assertStringNotContainsString(
            'Audit log table not found',
            (string) $json,
            'the compliance report claims the audit log is missing on an install that has one',
        );
    }

    /**
     * If the trail genuinely cannot be produced, saying "success" is worse
     * than saying nothing — this is the exact artifact an auditor relies on.
     */
    public function test_audit_export_does_not_claim_success_when_the_trail_is_unavailable(): void
    {
        [$admin, $token] = $this->admin();
        Schema::drop('audit_log');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/compliance/audit-export');

        $this->assertNotSame(
            200,
            $response->status(),
            'the export reported HTTP 200 while unable to produce any audit trail',
        );
    }
}
