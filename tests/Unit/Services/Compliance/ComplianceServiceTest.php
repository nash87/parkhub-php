<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Compliance;

use App\Services\Compliance\ComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_returns_ten_checks_with_expected_categories(): void
    {
        $report = app(ComplianceService::class)->report();

        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('overall_status', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('data_categories', $report);
        $this->assertArrayHasKey('legal_basis', $report);
        $this->assertArrayHasKey('retention_periods', $report);
        $this->assertArrayHasKey('sub_processors', $report);
        $this->assertArrayHasKey('tom_summary', $report);

        $this->assertCount(10, $report['checks']);

        $ids = array_column($report['checks'], 'id');
        $this->assertContains('encryption-at-rest', $ids);
        $this->assertContains('data-portability', $ids);
        $this->assertContains('dpo-appointed', $ids);
    }

    public function test_report_overall_status_is_one_of_three_well_known_values(): void
    {
        $report = app(ComplianceService::class)->report();

        $this->assertContains(
            $report['overall_status'],
            ['compliant', 'warning', 'non_compliant'],
        );
    }

    public function test_tom_summary_mirrors_the_live_check_statuses(): void
    {
        $report = app(ComplianceService::class)->report();

        $tom = $report['tom_summary'];
        // Static rows — always hard-coded true/false.
        $this->assertTrue($tom['backup_encryption']);
        $this->assertTrue($tom['privacy_by_design']);
        $this->assertFalse($tom['incident_response_plan']);
        $this->assertFalse($tom['regular_audits']);

        // Dynamic rows — must mirror the check's status verbatim.
        $encryptionCheck = collect($report['checks'])->firstWhere('id', 'encryption-at-rest');
        $this->assertSame(
            $encryptionCheck['status'] === 'compliant',
            $tom['encryption_at_rest'],
        );
    }

    public function test_data_map_contains_five_processing_activities(): void
    {
        $map = app(ComplianceService::class)->dataMap();

        $this->assertArrayHasKey('organization', $map);
        $this->assertArrayHasKey('processing_activities', $map);
        $this->assertCount(5, $map['processing_activities']);

        $names = array_column($map['processing_activities'], 'name');
        $this->assertContains('User Account Management', $names);
        $this->assertContains('Payment Processing', $names);

        foreach ($map['processing_activities'] as $activity) {
            $this->assertArrayHasKey('purpose', $activity);
            $this->assertArrayHasKey('legal_basis', $activity);
            $this->assertArrayHasKey('retention', $activity);
        }
    }

    public function test_audit_logs_returns_null_when_table_missing(): void
    {
        // Dropping the real table is what "the audit module is off" means.
        // This test used to drop `audit_logs` — a table no migration ever
        // creates — so it passed while asserting nothing about the code
        // path that runs in production.
        DB::getSchemaBuilder()->drop('audit_log');

        $this->assertNull(app(ComplianceService::class)->auditLogs(100));
    }

    public function test_audit_logs_returns_rows_newest_first_when_table_exists(): void
    {
        DB::table('audit_log')->insert([
            [
                'id' => (string) Str::uuid(),
                'user_id' => (string) Str::uuid(),
                'action' => 'older, action "with quotes"',
                'target_type' => 'booking',
                'target_id' => 'b-1',
                'ip_address' => '203.0.113.1',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => (string) Str::uuid(),
                'action' => 'newest_event',
                'target_type' => 'user',
                'target_id' => 'u-1',
                'ip_address' => '203.0.113.2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $logs = app(ComplianceService::class)->auditLogs(10);

        $this->assertNotNull($logs);
        $this->assertSame(2, $logs->count());
        $this->assertSame('newest_event', $logs->first()->action);
    }

    public function test_audit_logs_csv_escapes_quotes_in_action(): void
    {
        DB::table('audit_log')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'action' => 'login "admin"',
            'target_type' => 'user',
            'target_id' => 'u-1',
            'ip_address' => '203.0.113.3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ComplianceService::class);
        $csv = $service->auditLogsCsv($service->auditLogs(10));

        $this->assertStringStartsWith('id,user_id,action,resource_type,resource_id,ip_address,created_at', $csv);
        // Internal double-quotes must be CSV-escaped (RFC 4180).
        $this->assertStringContainsString('""admin""', $csv);
    }
}
