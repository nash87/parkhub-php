<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\Fairness\FairnessReportService;
use App\Services\Retention\RetentionClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Betriebsrat fairness & §87 BetrVG transparency — PHP twin of the Rust edition.
 *
 * TDD-first contract coverage:
 *  - Gini coefficient fixtures: all-equal → 0.0, one-user-takes-all → (n-1)/n
 *  - k-anonymity folding: buckets < K_ANON_THRESHOLD folded into "other"
 *  - Date-window filtering: events outside the window are excluded
 *  - Disclosure: all 7 RetentionClass cases are present
 *  - RBAC: non-admin gets 403
 *  - HTTP contract: response envelope, field names, default window
 */
class FairnessReportTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ────────────────────────────────────────────────────────────────

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
        config(['modules.fairness' => true]);
    }

    /**
     * Seed $count RecommendationServed events, each attributed to a distinct
     * synthetic user, at the given timestamp.
     *
     * Uses create + saveQuietly to control created_at precisely (established
     * pattern from PurgeAuditLogsJobTest).
     *
     * @param  non-negative-int  $count
     */
    private function seedAllocations(int $count, ?\DateTimeInterface $at = null): void
    {
        $at ??= now();

        for ($i = 0; $i < $count; $i++) {
            $log = AuditLog::create([
                'user_id' => (string) ($i + 9000),
                'username' => "seed_user_{$i}",
                'action' => 'recommendation_served',
                'event_type' => 'RecommendationServed',
                'details' => [],
                'ip_address' => '127.0.0.1',
            ]);
            $log->created_at = $at;
            $log->updated_at = $at;
            $log->saveQuietly();
        }
    }

    // ── Gini coefficient unit tests ────────────────────────────────────────────

    public function test_gini_all_equal_is_zero(): void
    {
        $service = app(FairnessReportService::class);

        // All users have the same count → perfect equality → G = 0
        $gini = $service->computeGini([3, 3, 3, 3, 3]);

        $this->assertSame(0.0, $gini);
    }

    public function test_gini_one_user_takes_all(): void
    {
        $service = app(FairnessReportService::class);
        $n = 10;

        // 1 user has all allocations, rest have 0 → G = (n-1)/n
        $counts = array_fill(0, $n - 1, 0);
        $counts[] = 50;

        $gini = $service->computeGini($counts);

        $expected = ($n - 1) / $n; // = 0.9
        $this->assertEqualsWithDelta($expected, $gini, 1e-5);
    }

    public function test_gini_two_users_one_dominant(): void
    {
        $service = app(FairnessReportService::class);

        // [0, 10]: G = (2*1*0 + 2*2*10 - 3*(0+10)) / (2 * 10)
        //        = (0 + 40 - 30) / 20 = 10/20 = 0.5
        $gini = $service->computeGini([0, 10]);

        $this->assertEqualsWithDelta(0.5, $gini, 1e-5);
    }

    public function test_gini_empty_returns_null(): void
    {
        $service = app(FairnessReportService::class);

        $this->assertNull($service->computeGini([]));
    }

    public function test_gini_all_zeros_returns_zero(): void
    {
        $service = app(FairnessReportService::class);

        // Nobody received an allocation → perfect (trivial) equality
        $this->assertSame(0.0, $service->computeGini([0, 0, 0]));
    }

    public function test_gini_single_user(): void
    {
        $service = app(FairnessReportService::class);

        // Single user: G = (2*1*5 - 2*5) / (1 * 5) = 0
        $this->assertSame(0.0, $service->computeGini([5]));
    }

    // ── k-anonymity tests ──────────────────────────────────────────────────────

    public function test_k_anonymity_folds_thin_buckets(): void
    {
        $this->enableModule();

        // Seed exactly 3 users → 3 events, each user gets 1 allocation.
        // Bucket "1-2" will have 3 users → below K_ANON_THRESHOLD (5) → folded.
        $this->seedAllocations(3);

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();
        $buckets = $response->json('data.allocation_frequency_buckets');

        // The "1-2" bucket has 3 users which is < threshold → must be folded
        $this->assertArrayNotHasKey('1-2', $buckets);
        $this->assertArrayHasKey('other (<5)', $buckets);
        $this->assertSame(3, $buckets['other (<5)']);
    }

    public function test_k_anonymity_does_not_fold_buckets_at_threshold(): void
    {
        $this->enableModule();

        // Seed exactly 5 distinct users, each with 1 allocation.
        // Bucket "1-2" should have 5 users → exactly at threshold → NOT folded.
        $this->seedAllocations(5);

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();
        $buckets = $response->json('data.allocation_frequency_buckets');

        // 5 == threshold → bucket is NOT folded
        $this->assertArrayHasKey('1-2', $buckets);
        $this->assertSame(5, $buckets['1-2']);
        $this->assertArrayNotHasKey('other (<5)', $buckets);
    }

    public function test_k_anonymity_threshold_is_reported_in_response(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk()
            ->assertJsonPath('data.k_anonymity_threshold', FairnessReportService::K_ANON_THRESHOLD);
    }

    // ── Date-window filtering ──────────────────────────────────────────────────

    public function test_date_window_excludes_events_outside_range(): void
    {
        $this->enableModule();

        $inside = AuditLog::create([
            'user_id' => '1001',
            'username' => 'inside_user',
            'action' => 'recommendation_served',
            'event_type' => 'RecommendationServed',
            'details' => [],
            'ip_address' => '127.0.0.1',
        ]);
        $inside->created_at = now()->subDays(5);
        $inside->updated_at = $inside->created_at;
        $inside->saveQuietly();

        $outside = AuditLog::create([
            'user_id' => '1002',
            'username' => 'outside_user',
            'action' => 'recommendation_served',
            'event_type' => 'RecommendationServed',
            'details' => [],
            'ip_address' => '127.0.0.1',
        ]);
        $outside->created_at = now()->subDays(60);
        $outside->updated_at = $outside->created_at;
        $outside->saveQuietly();

        $admin = $this->adminUser();

        $from = now()->subDays(10)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/fairness/report?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total_allocations'));
        $this->assertSame(1, $response->json('data.unique_users_allocated'));
    }

    public function test_both_event_types_counted(): void
    {
        $this->enableModule();

        $log1 = AuditLog::create([
            'user_id' => '2001',
            'username' => 'dual_user',
            'action' => 'recommendation_served',
            'event_type' => 'RecommendationServed',
            'details' => [],
            'ip_address' => '127.0.0.1',
        ]);
        $log1->created_at = now()->subHours(2);
        $log1->updated_at = $log1->created_at;
        $log1->saveQuietly();

        $log2 = AuditLog::create([
            'user_id' => '2001',
            'username' => 'dual_user',
            'action' => 'exact_cover_allocation_served',
            'event_type' => 'ExactCoverAllocationServed',
            'details' => ['status' => 'solved'],
            'ip_address' => '127.0.0.1',
        ]);
        $log2->created_at = now()->subHour();
        $log2->updated_at = $log2->created_at;
        $log2->saveQuietly();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total_allocations'));
        $this->assertSame(1, $response->json('data.unique_users_allocated'));
    }

    // ── Denial reasons aggregation ─────────────────────────────────────────────

    public function test_denial_reasons_excludes_solved(): void
    {
        $this->enableModule();

        $logSolved = AuditLog::create([
            'user_id' => '3001',
            'username' => 'user_a',
            'action' => 'exact_cover_allocation_served',
            'event_type' => 'ExactCoverAllocationServed',
            'details' => ['status' => 'solved'],
            'ip_address' => '127.0.0.1',
        ]);
        $logSolved->created_at = now()->subHours(3);
        $logSolved->updated_at = $logSolved->created_at;
        $logSolved->saveQuietly();

        $logInfeasible1 = AuditLog::create([
            'user_id' => '3002',
            'username' => 'user_b',
            'action' => 'exact_cover_allocation_served',
            'event_type' => 'ExactCoverAllocationServed',
            'details' => ['status' => 'infeasible'],
            'ip_address' => '127.0.0.1',
        ]);
        $logInfeasible1->created_at = now()->subHours(2);
        $logInfeasible1->updated_at = $logInfeasible1->created_at;
        $logInfeasible1->saveQuietly();

        $logInfeasible2 = AuditLog::create([
            'user_id' => '3003',
            'username' => 'user_c',
            'action' => 'exact_cover_allocation_served',
            'event_type' => 'ExactCoverAllocationServed',
            'details' => ['status' => 'infeasible'],
            'ip_address' => '127.0.0.1',
        ]);
        $logInfeasible2->created_at = now()->subHour();
        $logInfeasible2->updated_at = $logInfeasible2->created_at;
        $logInfeasible2->saveQuietly();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();
        $denials = $response->json('data.denial_reasons');
        $this->assertArrayNotHasKey('solved', $denials);
        $this->assertArrayHasKey('infeasible', $denials);
        $this->assertSame(2, $denials['infeasible']);
    }

    // ── §87 disclosure tests ───────────────────────────────────────────────────

    public function test_disclosure_lists_all_seven_retention_classes(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/transparency/data-collection');

        $response->assertOk();
        $classes = $response->json('data.classes');

        $this->assertCount(7, $classes);

        $classValues = array_column($classes, 'class');
        foreach (RetentionClass::cases() as $case) {
            $this->assertContains($case->value, $classValues,
                "Expected retention class '{$case->value}' in disclosure.");
        }
    }

    public function test_disclosure_has_no_covert_monitoring_guarantee(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/transparency/data-collection');

        $response->assertOk()
            ->assertJsonPath('data.no_covert_monitoring', true)
            ->assertJsonPath('data.no_performance_evaluation', true);
    }

    public function test_disclosure_legal_hold_classes_have_statutory_min(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/transparency/data-collection');

        $response->assertOk();
        $classes = $response->json('data.classes');

        foreach ($classes as $entry) {
            if ($entry['is_legal_hold']) {
                $this->assertNotNull($entry['statutory_min_days'],
                    "Legal-hold class '{$entry['class']}' must have statutory_min_days set.");
            }
        }
    }

    public function test_disclosure_includes_surfaces_for_each_class(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/transparency/data-collection');

        $response->assertOk();
        $classes = $response->json('data.classes');

        foreach ($classes as $entry) {
            $this->assertIsArray($entry['surfaces'],
                "Class '{$entry['class']}' must have a surfaces array.");
            $this->assertNotEmpty($entry['surfaces'],
                "Class '{$entry['class']}' must list at least one surface.");
        }
    }

    public function test_disclosure_has_legal_basis_statement(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/transparency/data-collection');

        $response->assertOk();
        $legalBasis = $response->json('data.legal_basis');
        $this->assertStringContainsString('§87', $legalBasis);
    }

    // ── RBAC enforcement ───────────────────────────────────────────────────────

    public function test_non_admin_cannot_access_fairness_report(): void
    {
        $this->enableModule();

        $user = $this->regularUser();
        $this->actingAs($user)
            ->getJson('/api/v1/admin/fairness/report')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_access_disclosure(): void
    {
        $this->enableModule();

        $user = $this->regularUser();
        $this->actingAs($user)
            ->getJson('/api/v1/admin/transparency/data-collection')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->enableModule();

        $this->getJson('/api/v1/admin/fairness/report')->assertStatus(401);
        $this->getJson('/api/v1/admin/transparency/data-collection')->assertStatus(401);
    }

    public function test_disabled_module_returns_404(): void
    {
        config(['modules.fairness' => false]);

        $admin = $this->adminUser();
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/fairness/report')
            ->assertStatus(404);
    }

    // ── HTTP contract ──────────────────────────────────────────────────────────

    public function test_fairness_report_response_envelope(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'window' => ['from', 'to'],
                    'total_allocations',
                    'unique_users_allocated',
                    'allocation_frequency_buckets',
                    'denial_reasons',
                    'k_anonymity_threshold',
                ],
                'error',
            ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_fairness_report_invalid_window_returns_422(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/fairness/report?from=2025-12-31&to=2025-01-01')
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_WINDOW');
    }

    public function test_fairness_report_uses_default_window_when_no_params(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();

        $from = $response->json('data.window.from');
        $to = $response->json('data.window.to');
        $this->assertNotNull($from);
        $this->assertNotNull($to);
    }

    public function test_disclosure_response_envelope(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/transparency/data-collection');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'disclosure_version',
                    'legal_basis',
                    'classes',
                    'no_covert_monitoring',
                    'no_performance_evaluation',
                ],
                'error',
            ]);
    }

    public function test_gini_coefficient_present_when_allocations_exist(): void
    {
        $this->enableModule();

        // Seed 5 distinct users each with 1 allocation → all-equal → Gini = 0
        $this->seedAllocations(5);

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();
        $gini = $response->json('data.gini_coefficient');
        $this->assertNotNull($gini);
        $this->assertEqualsWithDelta(0.0, $gini, 1e-5);
    }

    public function test_gini_null_when_no_allocations(): void
    {
        $this->enableModule();

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();
        $this->assertNull($response->json('data.gini_coefficient'));
    }

    public function test_booking_to_allocation_ratio_computed(): void
    {
        $this->enableModule();

        $user = User::factory()->create();

        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
            'hourly_rate' => 5.0,
        ]);

        $slot1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        $slot2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '2', 'status' => 'available']);

        // 2 bookings in the window
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot1->id,
            'start_time' => now()->subHours(4),
            'end_time' => now()->subHours(2),
            'status' => 'confirmed',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot2->id,
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHours(1),
            'status' => 'confirmed',
        ]);

        // 1 allocation event in the window
        AuditLog::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'recommendation_served',
            'event_type' => 'RecommendationServed',
            'details' => [],
            'ip_address' => '127.0.0.1',
        ]);

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fairness/report');

        $response->assertOk();
        $ratio = $response->json('data.booking_to_allocation_ratio');
        $this->assertEqualsWithDelta(2.0, $ratio, 1e-4);
    }
}
