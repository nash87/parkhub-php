<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\AiActTransparencyController;
use App\Models\AuditLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EU AI Act Art. 50 transparency slice — PHP twin of the Rust edition.
 *
 * Contract:
 *  - Every algorithmic-decision response carries an `automated_decision` notice.
 *  - `fifo_only` mode makes algorithmic endpoints return 409 ALGORITHMIC_DISABLED.
 *  - Admin GET/PUT round-trip for the mode setting with RBAC enforcement.
 *  - Existing `legal_boundary` assertions are never weakened.
 */
class AiActTransparencyTest extends TestCase
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
        config(['modules.aiact' => true]);
        config(['modules.recommendations' => true]);
    }

    private function setTransparencyMode(string $mode): void
    {
        Setting::set(
            ModuleRegistry::configSettingKey('aiact', AiActTransparencyController::SETTING_KEY_SUFFIX),
            $mode
        );
    }

    /** Create a lot with $n available slots and return the lot. */
    private function createLotWithSlots(int $n, float $hourlyRate = 5.0): ParkingLot
    {
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => $n,
            'available_slots' => $n,
            'status' => 'open',
            'hourly_rate' => $hourlyRate,
        ]);
        for ($i = 1; $i <= $n; $i++) {
            ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => (string) $i,
                'status' => 'available',
            ]);
        }

        return $lot;
    }

    // ── automated_decision block present + truthful basis ──────────────────────

    public function test_automated_decision_block_present_on_recommendations(): void
    {
        $this->createLotWithSlots(2);
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('automated_decision.is_automated', true)
            ->assertJsonPath('automated_decision.art22_review_available', true)
            ->assertJsonPath('automated_decision.review_contact', 'administrator')
            ->assertJsonPath('automated_decision.mode', AiActTransparencyController::MODE_ALGORITHMIC);

        $basis = $response->json('automated_decision.basis');
        $this->assertIsArray($basis);
        $this->assertNotEmpty($basis);
    }

    public function test_automated_decision_basis_truthfully_reflects_inputs(): void
    {
        $this->createLotWithSlots(3);
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $basis = $response->json('automated_decision.basis');

        // Basis must mention the scoring factors actually used
        $basisStr = implode(' ', $basis);
        $this->assertStringContainsString('booking_history', $basisStr);
        $this->assertStringContainsString('available_slot', $basisStr);
    }

    public function test_automated_decision_present_on_exact_cover_allocation(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['slot:A', 'slot:B'],
            'options' => [
                ['id' => 'opt-1', 'covers' => ['slot:A', 'slot:B']],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.automated_decision.is_automated', true)
            ->assertJsonPath('data.automated_decision.art22_review_available', true)
            ->assertJsonPath('data.automated_decision.review_contact', 'administrator')
            ->assertJsonPath('data.automated_decision.mode', AiActTransparencyController::MODE_ALGORITHMIC);

        $basis = $response->json('data.automated_decision.basis');
        $this->assertIsArray($basis);
        $basisStr = implode(' ', $basis);
        $this->assertStringContainsString('required_constraint_count', $basisStr);
        $this->assertStringContainsString('option_count', $basisStr);
    }

    // ── default mode is algorithmic ────────────────────────────────────────────

    public function test_default_mode_is_algorithmic_when_no_setting_exists(): void
    {
        // No Setting row — should default to algorithmic
        $mode = AiActTransparencyController::currentMode();

        $this->assertSame(AiActTransparencyController::MODE_ALGORITHMIC, $mode);
    }

    public function test_default_mode_yields_automated_decision_on_recommendations(): void
    {
        // No Setting row set — algorithmic mode is the default
        $this->createLotWithSlots(1);
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('automated_decision.mode', AiActTransparencyController::MODE_ALGORITHMIC);
    }

    // ── fifo_only 409 enforcement ──────────────────────────────────────────────

    public function test_fifo_only_mode_returns_409_on_recommendations(): void
    {
        $this->setTransparencyMode(AiActTransparencyController::MODE_FIFO_ONLY);
        $this->createLotWithSlots(2);
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ALGORITHMIC_DISABLED')
            ->assertJsonPath('error.mode', AiActTransparencyController::MODE_FIFO_ONLY);
    }

    public function test_fifo_only_mode_returns_409_on_exact_cover(): void
    {
        $this->setTransparencyMode(AiActTransparencyController::MODE_FIFO_ONLY);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['slot:A'],
            'options' => [
                ['id' => 'opt-1', 'covers' => ['slot:A']],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ALGORITHMIC_DISABLED')
            ->assertJsonPath('error.mode', AiActTransparencyController::MODE_FIFO_ONLY);
    }

    public function test_fifo_only_mode_does_not_affect_stats_endpoint(): void
    {
        $this->setTransparencyMode(AiActTransparencyController::MODE_FIFO_ONLY);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/recommendations/stats');

        // Stats is analytics, not a decision — must remain available in fifo_only mode
        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ── admin GET/PUT round-trip + RBAC ────────────────────────────────────────

    public function test_admin_get_mode_returns_algorithmic_by_default(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/aiact/transparency-mode');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', AiActTransparencyController::MODE_ALGORITHMIC)
            ->assertJsonStructure(['data' => ['mode', 'description', 'valid_modes', 'law_applies_from', 'article']]);
    }

    public function test_admin_put_mode_updates_setting(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/aiact/transparency-mode', [
            'mode' => AiActTransparencyController::MODE_FIFO_ONLY,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', AiActTransparencyController::MODE_FIFO_ONLY);
    }

    public function test_admin_put_mode_roundtrip(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        // Set to fifo_only
        $this->actingAs($admin)->putJson('/api/v1/admin/aiact/transparency-mode', [
            'mode' => AiActTransparencyController::MODE_FIFO_ONLY,
        ])->assertOk();

        // Read it back
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/aiact/transparency-mode');
        $response->assertOk()
            ->assertJsonPath('data.mode', AiActTransparencyController::MODE_FIFO_ONLY);

        // Switch back to algorithmic
        $this->actingAs($admin)->putJson('/api/v1/admin/aiact/transparency-mode', [
            'mode' => AiActTransparencyController::MODE_ALGORITHMIC,
        ])->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/aiact/transparency-mode');
        $response->assertOk()
            ->assertJsonPath('data.mode', AiActTransparencyController::MODE_ALGORITHMIC);
    }

    public function test_admin_put_mode_rbac_non_admin_forbidden(): void
    {
        $this->enableModule();
        $user = $this->regularUser();

        $response = $this->actingAs($user)->putJson('/api/v1/admin/aiact/transparency-mode', [
            'mode' => AiActTransparencyController::MODE_FIFO_ONLY,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_get_mode_rbac_non_admin_forbidden(): void
    {
        $this->enableModule();
        $user = $this->regularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/aiact/transparency-mode');

        $response->assertForbidden();
    }

    public function test_admin_put_mode_validates_invalid_value(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/aiact/transparency-mode', [
            'mode' => 'random_invalid_value',
        ]);

        $response->assertUnprocessable();
    }

    public function test_admin_put_mode_validates_missing_field(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/aiact/transparency-mode', []);

        $response->assertUnprocessable();
    }

    public function test_admin_put_mode_writes_audit_log(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $this->actingAs($admin)->putJson('/api/v1/admin/aiact/transparency-mode', [
            'mode' => AiActTransparencyController::MODE_FIFO_ONLY,
        ])->assertOk();

        $entry = AuditLog::query()
            ->where('action', 'aiact_transparency_mode_updated')
            ->latest()
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('AiActTransparencyModeUpdated', $entry->event_type);
        $details = $entry->getAttribute('details');
        $this->assertTrue(is_array($details));
        $this->assertSame(AiActTransparencyController::MODE_FIFO_ONLY, $details['new_mode']);
        $this->assertArrayHasKey('previous_mode', $details);
    }

    public function test_admin_module_disabled_returns_404(): void
    {
        config(['modules.aiact' => false]);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/aiact/transparency-mode');

        $response->assertNotFound();
    }

    // ── legal_boundary assertions not weakened ─────────────────────────────────

    public function test_legal_boundary_present_and_intact_on_recommendations_stats(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/recommendations/stats');

        $response->assertOk()
            ->assertJsonPath('data.legal_boundary.legal_review_required', true)
            ->assertJsonPath('data.legal_boundary.attorney_review_status', 'required_before_customer_wording')
            ->assertJsonPath('data.legal_boundary.execution_allowed', false);
    }

    public function test_legal_boundary_present_and_intact_on_exact_cover(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/recommendations/allocation/exact-cover', [
            'required_constraints' => ['slot:X'],
            'options' => [
                ['id' => 'o1', 'covers' => ['slot:X']],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.legal_boundary.legal_review_required', true)
            ->assertJsonPath('data.legal_boundary.attorney_review_status', 'required_before_customer_wording')
            ->assertJsonPath('data.legal_boundary.execution_allowed', false)
            ->assertJsonPath('data.legal_boundary.disclaimer', 'exact_cover_v1 is operational scheduling support; attorney review, citation verification, client authorization, and final legal judgment remain required before customer-facing legal or profiling claims ship.');
    }

    public function test_automated_decision_mode_field_reflects_current_setting(): void
    {
        $this->createLotWithSlots(1);
        $user = $this->regularUser();

        // Explicitly set algorithmic mode
        $this->setTransparencyMode(AiActTransparencyController::MODE_ALGORITHMIC);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('automated_decision.mode', AiActTransparencyController::MODE_ALGORITHMIC);
    }
}
