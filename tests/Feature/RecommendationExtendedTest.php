<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_include_reason_badges(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Badge Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 5.0,
        ]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('recommendation_id', $data[0]);
        $this->assertArrayHasKey('reason_badges', $data[0]);
        $this->assertContains('available_now', $data[0]['reason_badges']);
    }

    public function test_recommendations_emit_served_audit_log(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Audit Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
            'hourly_rate' => 4.0,
        ]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $recommendationId = $response->json('data.0.recommendation_id');
        $this->assertNotEmpty($recommendationId);

        $entry = AuditLog::query()->where('action', 'recommendation_served')->first();
        $this->assertNotNull($entry);
        $this->assertSame('RecommendationServed', $entry->event_type);
        $this->assertSame('recommendation', $entry->target_type);
        $this->assertSame($recommendationId, $entry->target_id);
        $this->assertSame($recommendationId, $entry->details['recommendation_id']);
        $this->assertSame('weighted_v1', $entry->details['algorithm']);
        $this->assertSame('weighted_v1', $entry->details['adapter']['effective_algorithm']);
        $this->assertFalse($entry->details['adapter']['attempted']);
        $this->assertSame([$slot->id], $entry->details['candidate_ids']);
        $this->assertTrue($entry->details['profile_safe_mode']);
        $this->assertTrue($entry->details['explain']);
        $this->assertFalse($entry->details['legal_boundary']['execution_allowed']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $entry->details['config_hash']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $entry->details['weights_hash']);
    }

    public function test_recommendations_weighted_scoring(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Weighted Lot',
            'total_slots' => 3,
            'available_slots' => 3,
            'status' => 'open',
            'hourly_rate' => 3.0,
        ]);

        $slot1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        $slot2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '50', 'status' => 'available']);

        // Create history for slot1 to boost frequency score
        for ($i = 0; $i < 5; $i++) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot1->id,
                'lot_name' => 'Weighted Lot',
                'slot_number' => '1',
                'start_time' => now()->subDays($i + 1),
                'end_time' => now()->subDays($i + 1)->addHours(2),
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        // Slot 1 should score higher due to frequency (40% weight) + distance (10% weight)
        $this->assertEquals($slot1->id, $data[0]['slot_id']);
        $this->assertContains('your_usual_spot', $data[0]['reason_badges']);
    }

    public function test_weighted_v1_fixture_matches_contract(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('docs/recommendation-engine-fixtures/weighted_v1.basic.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame('weighted_v1', $fixture['algorithm']);

        $user = User::factory()->create();
        $lots = [];
        $slots = [];
        $slotLotKeys = [];

        foreach ($fixture['candidate_lots'] as $fixtureLot) {
            $lot = ParkingLot::create([
                'name' => $fixtureLot['id'],
                'total_slots' => count($fixtureLot['slots']),
                'available_slots' => count($fixtureLot['slots']),
                'status' => 'open',
                'hourly_rate' => $fixtureLot['hourly_rate'],
            ]);
            $lots[$fixtureLot['id']] = $lot;

            foreach ($fixtureLot['slots'] as $fixtureSlot) {
                $slots[$fixtureSlot['id']] = ParkingSlot::create([
                    'lot_id' => $lot->id,
                    'slot_number' => (string) $fixtureSlot['slot_number'],
                    'status' => $fixtureSlot['status'],
                    'is_accessible' => $fixtureSlot['is_accessible'],
                    'features' => $fixtureSlot['features'],
                ]);
                $slotLotKeys[$fixtureSlot['id']] = $fixtureLot['id'];
            }
        }

        foreach ($fixture['history']['slot_usage'] as $slotKey => $usage) {
            $slot = $slots[$slotKey];
            $lot = $lots[$slotLotKeys[$slotKey]];
            for ($i = 0; $i < $usage; $i++) {
                Booking::create([
                    'user_id' => $user->id,
                    'lot_id' => $lot->id,
                    'slot_id' => $slot->id,
                    'lot_name' => $lot->name,
                    'slot_number' => $slot->slot_number,
                    'start_time' => now()->subDays($i + 1),
                    'end_time' => now()->subDays($i + 1)->addHours(2),
                    'status' => 'completed',
                ]);
            }
        }

        $slotKeysById = collect($slots)->mapWithKeys(fn ($slot, string $key) => [$slot->id => $key]);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $actual = collect($response->json('data'))->map(fn (array $item) => [
            'slot_id' => $slotKeysById[$item['slot_id']],
            'score' => round((float) $item['score'], 2),
            'badges' => $item['reason_badges'],
            'reasons' => $item['reasons'],
        ])->values()->all();
        $expected = collect($fixture['expected_ranked_slots'])->map(fn (array $item) => [
            'slot_id' => $item['slot_id'],
            'score' => (float) $item['score'],
            'badges' => $item['badges'],
            'reasons' => $item['reasons'],
        ])->all();

        $this->assertSame($expected, $actual);
    }

    public function test_fop_pipeline_v1_success_reorders_known_candidates(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Pipeline Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 2.0,
        ]);
        $slot1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        $slot2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '2', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '3', 'status' => 'available']);
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'algorithm'), json_encode('fop_pipeline_v1'));
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'pipeline_endpoint'), json_encode('http://fop-pipeline.test:9310'));
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'pipeline_name'), json_encode('parkhub-recommendations'));
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'max_results'), json_encode(1));

        Http::fake([
            'http://fop-pipeline.test:9310/*' => Http::response([
                'ok' => true,
                'data' => [
                    'ranked' => [
                        [
                            'slot_id' => $slot2->id,
                            'score' => 99.5,
                            'reasons' => ['Pipeline selected'],
                            'reason_badges' => ['available_now'],
                        ],
                        [
                            'slot_id' => $slot1->id,
                            'score' => 10,
                            'reasons' => ['Pipeline fallback rank'],
                            'reason_badges' => ['available_now'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('data.0.slot_id', $slot2->id)
            ->assertJsonPath('data.0.score', 99.5)
            ->assertJsonPath('data.0.reasons.0', 'Pipeline selected')
            ->assertJsonCount(1, 'data');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/pipeline/parkhub-recommendations/run')
            && data_get($request->data(), 'algorithm') === 'fop_pipeline_v1'
            && data_get($request->data(), 'fallback_algorithm') === 'weighted_v1'
            && data_get($request->data(), 'profile_safe_mode') === true
            && data_get($request->data(), 'max_results') === 1
            && count((array) data_get($request->data(), 'candidates')) === 3);

        $entry = AuditLog::query()->where('action', 'recommendation_served')->first();
        $this->assertSame('fop_pipeline_v1', $entry->details['algorithm']);
        $this->assertSame('fop_pipeline_v1', $entry->details['adapter']['effective_algorithm']);
        $this->assertSame('succeeded', $entry->details['adapter']['status']);
        $this->assertTrue($entry->details['adapter']['attempted']);
    }

    public function test_fop_pipeline_v1_unknown_ranked_slot_falls_back_to_weighted_v1(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Unknown Slot Pipeline Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 2.0,
        ]);
        $slot1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        $slot2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '2', 'status' => 'available']);
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'algorithm'), json_encode('fop_pipeline_v1'));
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'pipeline_endpoint'), json_encode('http://fop-pipeline.test:9310'));
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'pipeline_name'), json_encode('parkhub-recommendations'));

        Http::fake([
            'http://fop-pipeline.test:9310/*' => Http::response([
                'ok' => true,
                'data' => [
                    'ranked' => [
                        ['slot_id' => 'slot-from-another-candidate-set', 'score' => 100],
                        ['slot_id' => $slot2->id, 'score' => 99],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('data.0.slot_id', $slot1->id);

        $entry = AuditLog::query()->where('action', 'recommendation_served')->first();
        $this->assertSame('fop_pipeline_v1', $entry->details['algorithm']);
        $this->assertSame('weighted_v1', $entry->details['adapter']['effective_algorithm']);
        $this->assertSame('fallback_error', $entry->details['adapter']['status']);
        $this->assertTrue($entry->details['adapter']['attempted']);
        $this->assertStringContainsString('unknown slot_id', $entry->details['adapter']['error']);
    }

    public function test_fop_pipeline_v1_falls_back_when_endpoint_missing(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Fallback Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
            'hourly_rate' => 2.0,
        ]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'algorithm'), json_encode('fop_pipeline_v1'));

        Http::fake();

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('data.0.slot_id', $slot->id);
        Http::assertNothingSent();

        $entry = AuditLog::query()->where('action', 'recommendation_served')->first();
        $this->assertSame('fop_pipeline_v1', $entry->details['algorithm']);
        $this->assertSame('weighted_v1', $entry->details['adapter']['effective_algorithm']);
        $this->assertSame('fallback_not_configured', $entry->details['adapter']['status']);
        $this->assertFalse($entry->details['adapter']['attempted']);
    }

    public function test_fop_pipeline_v1_rejects_external_endpoint_and_falls_back(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'External Endpoint Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
            'hourly_rate' => 2.0,
        ]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'algorithm'), json_encode('fop_pipeline_v1'));
        Setting::set(ModuleRegistry::configSettingKey('recommendations', 'pipeline_endpoint'), json_encode('https://example.com/pipeline'));

        Http::fake();

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('data.0.slot_id', $slot->id);
        Http::assertNothingSent();

        $entry = AuditLog::query()->where('action', 'recommendation_served')->first();
        $this->assertSame('fop_pipeline_v1', $entry->details['algorithm']);
        $this->assertSame('weighted_v1', $entry->details['adapter']['effective_algorithm']);
        $this->assertSame('fallback_not_configured', $entry->details['adapter']['status']);
        $this->assertFalse($entry->details['adapter']['endpoint_configured']);
    }

    public function test_recommendations_stats_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/recommendations/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_recommendations', 0)
            ->assertJsonPath('data.total_recommendations_served', 0)
            ->assertJsonPath('data.accepted_recommendations', null)
            ->assertJsonPath('data.acceptance_rate', null)
            ->assertJsonPath('data.acceptance_metric_source', 'not_tracked')
            ->assertJsonPath('data.unique_users', 0)
            ->assertJsonPath('data.avg_score', null)
            ->assertJsonPath('data.metrics_source', 'audit_log.recommendation_served')
            ->assertJsonPath('data.algorithm_weights.frequency', 40)
            ->assertJsonPath('data.algorithm_weights.availability', 30)
            ->assertJsonPath('data.algorithm_weights.price', 20)
            ->assertJsonPath('data.algorithm_weights.distance', 10)
            ->assertJsonPath('data.algorithm_weights.feature_bonus', 2)
            ->assertJsonPath('data.allocation.strategy', 'weighted_v1')
            ->assertJsonPath('data.allocation.exact_cover_max_options', 256)
            ->assertJsonPath('data.allocation.exact_cover_max_search_nodes', 10000)
            ->assertJsonPath('data.algorithm_adapter.effective_algorithm', 'weighted_v1')
            ->assertJsonPath('data.algorithm_adapter.fallback_enabled', true)
            ->assertJsonPath('data.legal_boundary.legal_review_required', true)
            ->assertJsonPath('data.legal_boundary.attorney_review_status', 'required_before_customer_wording')
            ->assertJsonPath('data.legal_boundary.execution_allowed', false)
            // Exact wording is a cross-repo contract: parkhub-rust serves the
            // byte-identical disclaimer from RecommendationLegalBoundary.
            ->assertJsonPath('data.legal_boundary.disclaimer', 'fop legal output is reference-only drafting support; attorney review, citation verification, client authorization, and final legal judgment remain required before customer-facing profiling or legal wording ships.')
            ->assertJsonCount(0, 'data.top_recommended_lots');
    }

    public function test_admin_exact_cover_allocation_endpoint_solves_batch_constraints(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'allocation_strategy'),
            json_encode('exact_cover_v1')
        );
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'exact_cover_max_options'),
            json_encode(10)
        );
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'exact_cover_max_search_nodes'),
            json_encode(500)
        );

        $response = $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['tenant:alpha', 'tenant:beta', 'ev', 'accessible'],
            'options' => [
                ['id' => 'slot-a', 'covers' => ['tenant:alpha', 'ev'], 'weight' => 90],
                ['id' => 'slot-b', 'covers' => ['tenant:beta', 'accessible'], 'weight' => 80],
                ['id' => 'slot-c', 'covers' => ['tenant:beta'], 'weight' => 70],
            ],
            'limits' => [
                'max_options' => 3,
                'max_search_nodes' => 50,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.result.strategy', 'exact_cover_v1')
            ->assertJsonPath('data.result.status', 'solved')
            ->assertJsonPath('data.result.selected_option_ids', ['slot-a', 'slot-b'])
            ->assertJsonPath('data.legal_boundary.legal_review_required', true)
            ->assertJsonPath('data.legal_boundary.attorney_review_status', 'required_before_customer_wording')
            ->assertJsonPath('data.legal_boundary.execution_allowed', false)
            // Exact wording is a cross-repo contract: parkhub-rust serves the
            // byte-identical disclaimer from ExactCoverLegalBoundary.
            ->assertJsonPath('data.legal_boundary.disclaimer', 'exact_cover_v1 is operational scheduling support; attorney review, citation verification, client authorization, and final legal judgment remain required before customer-facing legal or profiling claims ship.');

        $this->assertNotEmpty($response->json('data.allocation_trace_id'));

        $trace = AuditLog::query()
            ->where('event_type', 'ExactCoverAllocationServed')
            ->latest()
            ->first();

        $this->assertNotNull($trace);
        $this->assertSame($response->json('data.allocation_trace_id'), $trace->target_id);
        $this->assertSame('recommendation_allocation', $trace->target_type);
        $this->assertSame('exact_cover_v1', $trace->details['solver_name']);
        $this->assertSame(['slot-a', 'slot-b'], $trace->details['selected_option_ids']);
        $this->assertSame(['slot-c'], $trace->details['rejected_candidate_ids']);
        $this->assertSame(3, $trace->details['tie_break_inputs']['max_options']);
        $this->assertSame(50, $trace->details['tie_break_inputs']['max_search_nodes']);
        $this->assertArrayHasKey('tenant_id', $trace->details);
        $this->assertSame('solved', $trace->details['fallback_status']);
        $this->assertSame('operational_evidence_personal_data_possible', $trace->details['retention_deletion_class']);
    }

    public function test_admin_exact_cover_allocation_audit_records_effective_request_limits(): void
    {
        $admin = User::factory()->admin()->create();
        // Module defaults are deliberately wider than the request overrides so
        // the audit trace must capture the effective limits actually used, not
        // the configured defaults (otherwise fallback_*_limited is unreproducible).
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'exact_cover_max_options'),
            json_encode(256)
        );
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'exact_cover_max_search_nodes'),
            json_encode(10000)
        );

        $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['tenant:alpha', 'tenant:beta'],
            'options' => [
                ['id' => 'slot-a', 'covers' => ['tenant:alpha'], 'weight' => 90],
                ['id' => 'slot-b', 'covers' => ['tenant:beta'], 'weight' => 80],
            ],
            'limits' => [
                'max_options' => 5,
                'max_search_nodes' => 25,
            ],
        ])->assertOk();

        $trace = AuditLog::query()
            ->where('event_type', 'ExactCoverAllocationServed')
            ->latest()
            ->first();

        $this->assertNotNull($trace);
        $this->assertArrayHasKey('effective_limits', $trace->details);
        $this->assertSame(5, $trace->details['effective_limits']['max_options']);
        $this->assertSame(25, $trace->details['effective_limits']['max_search_nodes']);
        // The effective limits must reflect the request overrides, not the
        // wider module defaults configured above.
        $this->assertNotSame(256, $trace->details['effective_limits']['max_options']);
        $this->assertNotSame(10000, $trace->details['effective_limits']['max_search_nodes']);
    }

    public function test_admin_exact_cover_allocation_rejects_duplicate_option_ids(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['tenant:alpha', 'tenant:beta'],
            'options' => [
                ['id' => 'slot-a', 'covers' => ['tenant:alpha'], 'weight' => 90],
                ['id' => 'slot-a', 'covers' => ['tenant:beta'], 'weight' => 80],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('error.code', 'DUPLICATE_EXACT_COVER_OPTION_ID')
            ->assertJsonPath('error.message', 'Exact-cover option IDs must be unique: slot-a');
    }

    public function test_admin_exact_cover_allocation_rejects_empty_option_covers(): void
    {
        $admin = User::factory()->admin()->create();

        // An empty `covers` array must be rejected at the validation boundary
        // via `min:1`, matching the OpenAPI schema's `covers.minItems: 1`.
        $response = $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['tenant:alpha'],
            'options' => [
                ['id' => 'slot-a', 'covers' => [], 'weight' => 90],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertStringContainsString('options.0.covers', (string) $response->json('error.message'));
    }

    public function test_admin_exact_cover_allocation_rejects_blank_option_covers(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['tenant:alpha'],
            'options' => [
                ['id' => 'slot-a', 'covers' => ['  '], 'weight' => 90],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('error.code', 'EXACT_COVER_OPTION_COVERS_REQUIRED')
            ->assertJsonPath('error.message', 'Exact-cover options must cover at least one constraint: slot-a');
    }

    public function test_recommendations_stats_requires_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/recommendations/stats')->assertForbidden();
    }

    public function test_recommendations_stats_reflect_configured_engine_weights(): void
    {
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'weight_frequency'),
            json_encode(55.0),
        );
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'weight_preferred_lot'),
            json_encode(15.0),
        );
        Setting::set(
            ModuleRegistry::configSettingKey('recommendations', 'profile_safe_mode'),
            json_encode(true),
        );

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/recommendations/stats')
            ->assertOk()
            ->assertJsonPath('data.algorithm', 'weighted_v1')
            ->assertJsonPath('data.algorithm_weights.frequency', 55)
            ->assertJsonPath('data.algorithm_weights.preferred_lot', 15);
    }

    public function test_recommendations_stats_uses_served_audit_logs_not_bookings(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Stats Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Stats Lot',
            'slot_number' => '1',
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'status' => 'completed',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Stats Lot',
            'slot_number' => '1',
            'start_time' => now(),
            'end_time' => now()->addHours(2),
            'status' => 'active',
        ]);
        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->email,
            'action' => 'recommendation_served',
            'event_type' => 'RecommendationServed',
            'target_type' => 'recommendation',
            'target_id' => 'rec-1',
            'details' => [
                'candidates' => [
                    ['slot_id' => $slot->id, 'lot_id' => $lot->id, 'score' => 80.0],
                    ['slot_id' => 'other-slot', 'lot_id' => 'missing-lot', 'score' => 70.0],
                ],
            ],
        ]);
        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->email,
            'action' => 'recommendation_served',
            'event_type' => 'RecommendationServed',
            'target_type' => 'recommendation',
            'target_id' => 'rec-2',
            'details' => [
                'candidates' => [
                    ['slot_id' => $slot->id, 'lot_id' => $lot->id, 'score' => 90.0],
                ],
            ],
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/recommendations/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_recommendations', 2)
            ->assertJsonPath('data.total_recommendations_served', 3)
            ->assertJsonPath('data.accepted_recommendations', null)
            ->assertJsonPath('data.acceptance_rate', null)
            ->assertJsonPath('data.unique_users', 1)
            ->assertJsonPath('data.avg_score', 80)
            ->assertJsonPath('data.top_recommended_lots.0.lot_id', $lot->id)
            ->assertJsonPath('data.top_recommended_lots.0.lot_name', 'Stats Lot')
            ->assertJsonPath('data.top_recommended_lots.0.count', 2);
    }

    public function test_recommendations_price_scoring(): void
    {
        $user = User::factory()->create();

        $cheapLot = ParkingLot::create([
            'name' => 'Cheap Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 1.0,
        ]);

        $expensiveLot = ParkingLot::create([
            'name' => 'Expensive Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 10.0,
        ]);

        ParkingSlot::create(['lot_id' => $cheapLot->id, 'slot_number' => '5', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $expensiveLot->id, 'slot_number' => '5', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        // Cheap lot slot should rank higher due to price component (20% weight)
        $this->assertEquals($cheapLot->id, $data[0]['lot_id']);
    }

    public function test_recommendations_closest_entrance_badge(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Distance Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
        ]);

        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '100', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        // Slot 1 (closest) should have closest_entrance badge
        $slot1Data = collect($data)->firstWhere('slot_number', 1);
        $this->assertContains('closest_entrance', $slot1Data['reason_badges']);
    }

    public function test_recommendations_empty_when_no_available_slots(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Full Lot',
            'total_slots' => 2,
            'available_slots' => 0,
            'status' => 'open',
        ]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'occupied']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '2', 'status' => 'occupied']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_disabled_recommendations_module_returns_404(): void
    {
        config(['modules.recommendations' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/bookings/recommendations')->assertNotFound();
        $this->actingAs($user)->getJson('/api/v1/recommendations/stats')->assertNotFound();
    }
}
