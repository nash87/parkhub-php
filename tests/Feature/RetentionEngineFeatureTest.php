<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\Retention\RetentionClass;
use App\Services\Retention\RetentionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionEngineFeatureTest extends TestCase
{
    use RefreshDatabase;

    private RetentionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(RetentionEngine::class);
    }

    // ── (a) Purge respects per-class TTL ──────────────────────────────────────

    public function test_purge_deletes_rows_older_than_class_ttl(): void
    {
        $class = RetentionClass::BookingHistory; // 90 days default

        // 91 days old — should be deleted
        AuditLog::forceCreate([
            'action' => 'booking_viewed',
            'event_type' => 'booking_viewed',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => now()->subDays(91),
            'updated_at' => now()->subDays(91),
        ]);

        // 89 days old — should be kept
        AuditLog::forceCreate([
            'action' => 'booking_viewed',
            'event_type' => 'booking_viewed',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => now()->subDays(89),
            'updated_at' => now()->subDays(89),
        ]);

        $before = AuditLog::where('event_type', 'booking_viewed')->count();
        $this->assertSame(2, $before);

        $this->engine->purge(dryRun: false);

        $after = AuditLog::where('event_type', 'booking_viewed')->count();
        $this->assertSame(1, $after, 'Only the row within TTL window should remain');
    }

    public function test_purge_respects_custom_ttl_override(): void
    {
        $class = RetentionClass::BookingHistory; // default 90
        $this->engine->setTtlDays($class, 60);

        // 65 days old — past custom TTL, should be deleted
        AuditLog::forceCreate([
            'action' => 'booking_viewed',
            'event_type' => 'booking_viewed',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => now()->subDays(65),
            'updated_at' => now()->subDays(65),
        ]);

        // 55 days old — within custom TTL, should be kept
        AuditLog::forceCreate([
            'action' => 'booking_viewed',
            'event_type' => 'booking_viewed',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => now()->subDays(55),
            'updated_at' => now()->subDays(55),
        ]);

        $this->engine->purge(dryRun: false);

        $this->assertSame(
            1,
            AuditLog::where('event_type', 'booking_viewed')->count(),
        );
    }

    // ── (b) dry_run deletes nothing + reports counts ──────────────────────────

    public function test_dry_run_deletes_nothing(): void
    {
        AuditLog::forceCreate([
            'action' => 'presence_check',
            'event_type' => 'presence_check',
            'details' => ['retention_deletion_class' => RetentionClass::OperationalPresence->value],
            'created_at' => now()->subDays(40), // past 30-day TTL
            'updated_at' => now()->subDays(40),
        ]);

        $countBefore = AuditLog::count();

        $results = $this->engine->purge(dryRun: true);

        $this->assertSame($countBefore, AuditLog::count(), 'dry_run must not delete any rows');

        // The result for operational_presence should report count=1
        $row = collect($results)->firstWhere('class', RetentionClass::OperationalPresence->value);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['record_count']);
        $this->assertTrue($row['dry_run']);
    }

    public function test_dry_run_creates_no_evidence_entries(): void
    {
        AuditLog::forceCreate([
            'action' => 'presence_check',
            'event_type' => 'presence_check',
            'details' => ['retention_deletion_class' => RetentionClass::OperationalPresence->value],
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $this->engine->purge(dryRun: true);

        $evidence = AuditLog::where('event_type', 'RetentionPurgeRun')->count();
        $this->assertSame(0, $evidence, 'dry_run must not create any evidence log entries');
    }

    public function test_dry_run_reports_oldest_and_newest_timestamps(): void
    {
        $class = RetentionClass::OperationalPresence;
        $old = now()->subDays(50);
        $young = now()->subDays(35);

        AuditLog::forceCreate([
            'action' => 'presence_check', 'event_type' => 'presence_check',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => $old, 'updated_at' => $old,
        ]);
        AuditLog::forceCreate([
            'action' => 'presence_check', 'event_type' => 'presence_check',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => $young, 'updated_at' => $young,
        ]);

        $results = $this->engine->purge(dryRun: true);
        $row = collect($results)->firstWhere('class', $class->value);

        $this->assertSame(2, $row['record_count']);
        $this->assertNotNull($row['oldest_deleted_at']);
        $this->assertNotNull($row['newest_deleted_at']);
        $this->assertLessThan($row['newest_deleted_at'], $row['oldest_deleted_at']);
    }

    // ── (c) Evidence entry correctness ────────────────────────────────────────

    public function test_non_dry_run_creates_evidence_per_class_with_correct_fields(): void
    {
        $class = RetentionClass::AnprRaw; // 3 days
        $old = now()->subDays(5);
        $older = now()->subDays(10);

        AuditLog::forceCreate([
            'action' => 'plate_scan', 'event_type' => 'plate_scan',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => $old, 'updated_at' => $old,
        ]);
        AuditLog::forceCreate([
            'action' => 'plate_scan', 'event_type' => 'plate_scan',
            'details' => ['retention_deletion_class' => $class->value],
            'created_at' => $older, 'updated_at' => $older,
        ]);

        $this->engine->purge(dryRun: false);

        $evidence = AuditLog::where('event_type', 'RetentionPurgeRun')
            ->where('details->purged_class', $class->value)
            ->first();

        $this->assertNotNull($evidence, 'Evidence entry must exist for purged class');
        $this->assertSame(2, $evidence->details['record_count']);
        $this->assertFalse($evidence->details['dry_run']);
        $this->assertNotNull($evidence->details['oldest_deleted_at']);
        $this->assertNotNull($evidence->details['newest_deleted_at']);
        $this->assertLessThan(
            $evidence->details['newest_deleted_at'],
            $evidence->details['oldest_deleted_at'],
        );
    }

    public function test_evidence_is_tagged_as_security_audit_log_class(): void
    {
        AuditLog::forceCreate([
            'action' => 'plate_scan', 'event_type' => 'plate_scan',
            'details' => ['retention_deletion_class' => RetentionClass::AnprRaw->value],
            'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
        ]);

        $this->engine->purge(dryRun: false);

        $evidence = AuditLog::where('event_type', 'RetentionPurgeRun')->first();
        $this->assertNotNull($evidence);
        $this->assertSame(
            RetentionClass::SecurityAuditLog->value,
            $evidence->details['retention_deletion_class'],
        );
    }

    public function test_evidence_never_contains_deleted_row_content(): void
    {
        AuditLog::forceCreate([
            'action' => 'plate_scan',
            'event_type' => 'plate_scan',
            'username' => 'secret_user',
            'ip_address' => '10.0.0.1',
            'details' => [
                'retention_deletion_class' => RetentionClass::AnprRaw->value,
                'plate' => 'ABC-123',
            ],
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $this->engine->purge(dryRun: false);

        $evidence = AuditLog::where('event_type', 'RetentionPurgeRun')->first();
        $this->assertNotNull($evidence);

        $evidenceJson = json_encode($evidence->details);
        $this->assertStringNotContainsString('ABC-123', $evidenceJson);
        $this->assertStringNotContainsString('secret_user', $evidenceJson);
        $this->assertStringNotContainsString('10.0.0.1', $evidenceJson);
    }

    public function test_no_evidence_created_for_classes_with_no_expired_rows(): void
    {
        // A row within TTL — should not trigger evidence
        AuditLog::forceCreate([
            'action' => 'presence_check',
            'event_type' => 'presence_check',
            'details' => ['retention_deletion_class' => RetentionClass::OperationalPresence->value],
            'created_at' => now()->subDays(5), // within 30-day TTL
            'updated_at' => now()->subDays(5),
        ]);

        $this->engine->purge(dryRun: false);

        $evidence = AuditLog::where('event_type', 'RetentionPurgeRun')
            ->where('details->purged_class', RetentionClass::OperationalPresence->value)
            ->count();

        $this->assertSame(0, $evidence);
    }

    // ── (d) Legal-hold override rejection ─────────────────────────────────────

    public function test_legal_hold_override_below_statutory_minimum_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->setTtlDays(RetentionClass::BillingFiscal, 100);
    }

    public function test_legal_hold_override_at_statutory_minimum_is_accepted(): void
    {
        $this->engine->setTtlDays(RetentionClass::BillingFiscal, 2922);
        $this->assertSame(2922, $this->engine->getTtlDays(RetentionClass::BillingFiscal));
    }

    public function test_legal_hold_override_above_statutory_minimum_is_accepted(): void
    {
        $this->engine->setTtlDays(RetentionClass::HrLabour, 1200);
        $this->assertSame(1200, $this->engine->getTtlDays(RetentionClass::HrLabour));
    }

    public function test_non_legal_hold_class_accepts_any_positive_ttl(): void
    {
        $this->engine->setTtlDays(RetentionClass::OperationalPresence, 1);
        $this->assertSame(1, $this->engine->getTtlDays(RetentionClass::OperationalPresence));
    }

    // ── (e) Unknown-class rows never deleted (fail-safe) ─────────────────────

    public function test_rows_with_unknown_retention_class_are_never_deleted(): void
    {
        // An old row with an unknown class value — must survive purge
        AuditLog::forceCreate([
            'action' => 'some_event',
            'event_type' => 'some_event',
            'details' => ['retention_deletion_class' => 'totally_unknown_class'],
            'created_at' => now()->subDays(9999),
            'updated_at' => now()->subDays(9999),
        ]);

        $countBefore = AuditLog::count();
        $this->engine->purge(dryRun: false);

        $this->assertSame(
            $countBefore,
            AuditLog::count(),
            'Rows with unknown retention_deletion_class must never be deleted',
        );
    }

    public function test_rows_without_retention_class_are_never_deleted(): void
    {
        // A very old row with no retention_deletion_class in details
        AuditLog::forceCreate([
            'action' => 'generic_event',
            'event_type' => 'generic_event',
            'details' => ['some_other_key' => 'value'],
            'created_at' => now()->subDays(9999),
            'updated_at' => now()->subDays(9999),
        ]);

        $countBefore = AuditLog::count();
        $this->engine->purge(dryRun: false);

        $this->assertSame($countBefore, AuditLog::count());
    }

    // ── policies roundtrip ────────────────────────────────────────────────────

    public function test_get_policies_returns_all_seven_classes(): void
    {
        $policies = $this->engine->getPolicies();
        $this->assertCount(7, $policies);

        foreach (RetentionClass::cases() as $class) {
            $this->assertArrayHasKey($class->value, $policies);
            $this->assertIsInt($policies[$class->value]);
        }
    }

    public function test_default_ttl_used_when_no_override_set(): void
    {
        $this->assertSame(
            RetentionClass::OperationalPresence->defaultTtlDays(),
            $this->engine->getTtlDays(RetentionClass::OperationalPresence),
        );
    }

    // ── purge return value structure ──────────────────────────────────────────

    public function test_purge_returns_result_for_every_class(): void
    {
        $results = $this->engine->purge(dryRun: true);
        $this->assertCount(7, $results);

        $classes = array_column($results, 'class');
        foreach (RetentionClass::cases() as $class) {
            $this->assertContains($class->value, $classes);
        }
    }

    public function test_purge_result_has_required_fields(): void
    {
        $results = $this->engine->purge(dryRun: true);
        foreach ($results as $result) {
            $this->assertArrayHasKey('class', $result);
            $this->assertArrayHasKey('record_count', $result);
            $this->assertArrayHasKey('oldest_deleted_at', $result);
            $this->assertArrayHasKey('newest_deleted_at', $result);
            $this->assertArrayHasKey('dry_run', $result);
        }
    }
}
