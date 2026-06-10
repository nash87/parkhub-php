<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Retention\RetentionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function enableModule(): void
    {
        config(['modules.retention' => true]);
    }

    // ── (f) policies GET/PUT roundtrip + RBAC ────────────────────────────────

    public function test_policies_get_returns_all_seven_classes(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/retention/policies');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'policies' => [
                        ['class', 'ttl_days', 'default_ttl_days', 'is_legal_hold', 'statutory_min_days'],
                    ],
                ],
            ]);

        $policies = $response->json('data.policies');
        $this->assertCount(7, $policies);

        $classes = array_column($policies, 'class');
        $this->assertContains('operational_presence', $classes);
        $this->assertContains('billing_fiscal', $classes);
        $this->assertContains('hr_labour', $classes);
    }

    public function test_policies_get_reflects_default_ttls(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/retention/policies');

        $policies = collect($response->json('data.policies'))->keyBy('class');

        $this->assertSame(30, $policies['operational_presence']['ttl_days']);
        $this->assertSame(2922, $policies['billing_fiscal']['ttl_days']);
        $this->assertSame(3, $policies['anpr_raw']['ttl_days']);
    }

    public function test_policies_get_marks_legal_hold_classes(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/retention/policies');
        $policies = collect($response->json('data.policies'))->keyBy('class');

        $this->assertTrue($policies['billing_fiscal']['is_legal_hold']);
        $this->assertTrue($policies['hr_labour']['is_legal_hold']);
        $this->assertFalse($policies['operational_presence']['is_legal_hold']);
        $this->assertFalse($policies['security_audit_log']['is_legal_hold']);
    }

    public function test_policies_put_updates_ttl_and_returns_updated_policy(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/admin/retention/policies/booking_history', [
                'ttl_days' => 120,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['class', 'ttl_days', 'default_ttl_days', 'is_legal_hold', 'statutory_min_days'],
            ]);

        $this->assertSame('booking_history', $response->json('data.class'));
        $this->assertSame(120, $response->json('data.ttl_days'));
        $this->assertTrue($response->json('success'));
    }

    public function test_policies_put_roundtrip_via_get(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/retention/policies/anpr_raw', ['ttl_days' => 7]);

        $getResponse = $this->actingAs($admin)->getJson('/api/v1/admin/retention/policies');
        $policies = collect($getResponse->json('data.policies'))->keyBy('class');

        $this->assertSame(7, $policies['anpr_raw']['ttl_days']);
    }

    public function test_policies_put_legal_hold_below_minimum_returns_422(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/admin/retention/policies/billing_fiscal', [
                'ttl_days' => 365,
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    public function test_policies_put_hr_labour_below_minimum_returns_422(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/admin/retention/policies/hr_labour', [
                'ttl_days' => 100,
            ]);

        $response->assertStatus(422);
    }

    public function test_policies_put_unknown_class_returns_422(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/admin/retention/policies/nonexistent_class', [
                'ttl_days' => 30,
            ]);

        $response->assertStatus(422);
    }

    public function test_policies_put_missing_ttl_days_returns_422(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/admin/retention/policies/booking_history', []);

        $response->assertStatus(422);
    }

    public function test_policies_get_requires_admin_non_admin_403(): void
    {
        $this->enableModule();
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/retention/policies');

        $response->assertStatus(403);
    }

    public function test_policies_put_requires_admin_non_admin_403(): void
    {
        $this->enableModule();
        $user = $this->regularUser();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/admin/retention/policies/booking_history', ['ttl_days' => 60]);

        $response->assertStatus(403);
    }

    public function test_retention_run_requires_admin(): void
    {
        $this->enableModule();
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson('/api/v1/admin/retention/run');

        $response->assertStatus(403);
    }

    public function test_evidence_get_requires_admin(): void
    {
        $this->enableModule();
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/retention/evidence');

        $response->assertStatus(403);
    }

    // ── POST /run ─────────────────────────────────────────────────────────────

    public function test_run_dry_run_returns_counts_without_deleting(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        AuditLog::forceCreate([
            'action' => 'presence_check',
            'event_type' => 'presence_check',
            'details' => ['retention_deletion_class' => 'operational_presence'],
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $countBefore = AuditLog::count();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/retention/run', ['dry_run' => true]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['results', 'ran_at', 'dry_run'],
            ]);

        $this->assertTrue($response->json('data.dry_run'));
        $this->assertSame($countBefore, AuditLog::count(), 'dry_run must not delete rows');

        // Result for operational_presence should show 1 count
        $results = collect($response->json('data.results'));
        $row = $results->firstWhere('class', 'operational_presence');
        $this->assertNotNull($row);
        $this->assertSame(1, $row['record_count']);
        $this->assertTrue($row['dry_run']);
    }

    public function test_run_non_dry_run_deletes_expired_rows(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        AuditLog::forceCreate([
            'action' => 'anpr_check',
            'event_type' => 'anpr_check',
            'details' => ['retention_deletion_class' => 'anpr_raw'],
            'created_at' => now()->subDays(5), // past 3-day TTL
            'updated_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/retention/run', ['dry_run' => false]);

        $response->assertOk();
        $this->assertFalse($response->json('data.dry_run'));

        $results = collect($response->json('data.results'));
        $row = $results->firstWhere('class', 'anpr_raw');
        $this->assertNotNull($row);
        $this->assertSame(1, $row['record_count']);

        $this->assertSame(
            0,
            AuditLog::where('event_type', 'anpr_check')->count(),
            'Expired row must have been deleted',
        );
    }

    public function test_run_defaults_to_non_dry_run_when_param_omitted(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        AuditLog::forceCreate([
            'action' => 'anpr_check',
            'event_type' => 'anpr_check',
            'details' => ['retention_deletion_class' => 'anpr_raw'],
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/retention/run', []);

        $response->assertOk();
        $this->assertFalse($response->json('data.dry_run'));
    }

    public function test_run_returns_ran_at_timestamp(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/retention/run', ['dry_run' => true]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.ran_at'));
    }

    public function test_run_response_results_contains_all_seven_classes(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/retention/run', ['dry_run' => true]);

        $response->assertOk();
        $results = $response->json('data.results');
        $this->assertCount(7, $results);
    }

    // ── GET /evidence ─────────────────────────────────────────────────────────

    public function test_evidence_returns_purge_run_log_entries(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        // Create an expired anpr_raw row and purge it to generate evidence
        AuditLog::forceCreate([
            'action' => 'anpr_check',
            'event_type' => 'anpr_check',
            'details' => ['retention_deletion_class' => 'anpr_raw'],
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);
        app(RetentionEngine::class)->purge(dryRun: false);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/retention/evidence');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'evidence' => [
                        ['id', 'purged_class', 'record_count', 'oldest_deleted_at', 'newest_deleted_at', 'dry_run', 'occurred_at'],
                    ],
                ],
            ]);

        $evidence = $response->json('data.evidence');
        $this->assertNotEmpty($evidence);

        $row = collect($evidence)->firstWhere('purged_class', 'anpr_raw');
        $this->assertNotNull($row);
        $this->assertSame(1, $row['record_count']);
        $this->assertFalse($row['dry_run']);
    }

    public function test_evidence_returns_empty_array_when_no_runs(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/retention/evidence');

        $response->assertOk();
        $this->assertSame([], $response->json('data.evidence'));
    }

    // ── Module gate ───────────────────────────────────────────────────────────

    public function test_endpoints_return_404_when_module_disabled(): void
    {
        config(['modules.retention' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/retention/policies')->assertStatus(404);
        $this->actingAs($admin)->postJson('/api/v1/admin/retention/run')->assertStatus(404);
        $this->actingAs($admin)->getJson('/api/v1/admin/retention/evidence')->assertStatus(404);
    }
}
