<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckModule;
use App\Models\User;
use App\Providers\ModuleServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSystemExtendedTest extends TestCase
{
    use RefreshDatabase;

    // ── CheckModule middleware direct tests ──────────────────────────────

    public function test_disabled_import_module_returns_404(): void
    {
        config(['modules.import' => false]);
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/users/import', ['users' => []])
            ->assertNotFound();
    }

    public function test_disabled_metrics_module_returns_404(): void
    {
        config(['modules.metrics' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/metrics')
            ->assertNotFound();
    }

    public function test_disabled_admin_reports_module_returns_404(): void
    {
        config(['modules.admin_reports' => false]);
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/stats')
            ->assertNotFound();
    }

    public function test_disabled_gdpr_module_returns_404(): void
    {
        config(['modules.gdpr' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/gdpr/export')
            ->assertNotFound();
    }

    public function test_disabled_data_export_module_returns_404(): void
    {
        config(['modules.data_export' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/data-export')
            ->assertNotFound();
    }

    public function test_disabled_setup_wizard_module_returns_404(): void
    {
        config(['modules.setup_wizard' => false]);

        $this->getJson('/api/v1/setup/status')
            ->assertNotFound();
    }

    public function test_disabled_payments_module_returns_404(): void
    {
        config(['modules.payments' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/payments')
            ->assertNotFound();
    }

    public function test_disabled_branding_module_returns_404(): void
    {
        config(['modules.branding' => false]);
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/branding')
            ->assertNotFound();
    }

    public function test_disabled_swap_requests_module_returns_404(): void
    {
        config(['modules.swap_requests' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/swap-requests')
            ->assertNotFound();
    }

    // ── Module toggle edge cases ────────────────────────────────────────

    public function test_re_enable_module_makes_endpoint_accessible(): void
    {
        $user = User::factory()->create();

        config(['modules.vehicles' => false]);
        $this->actingAs($user)->getJson('/api/v1/vehicles')->assertNotFound();

        config(['modules.vehicles' => true]);
        $this->actingAs($user)->getJson('/api/v1/vehicles')->assertOk();
    }

    public function test_module_enabled_helper_for_all_modules(): void
    {
        $modules = config('modules');
        foreach ($modules as $name => $enabled) {
            config(["modules.{$name}" => true]);
            $this->assertTrue(module_enabled($name), "module_enabled('{$name}') should return true");

            config(["modules.{$name}" => false]);
            $this->assertFalse(module_enabled($name), "module_enabled('{$name}') should return false");
        }
    }

    public function test_module_service_provider_all_returns_correct_count(): void
    {
        $all = ModuleServiceProvider::all();
        $this->assertCount(67, $all);
    }

    public function test_modules_endpoint_is_always_public(): void
    {
        // No auth needed
        $this->getJson('/api/v1/modules')->assertOk();
    }

    public function test_disabled_qr_codes_module_returns_404(): void
    {
        config(['modules.qr_codes' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/qr/some-booking-id')
            ->assertNotFound();
    }

    public function test_disabled_broadcasting_module_returns_404(): void
    {
        config(['modules.broadcasting' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/broadcasting/auth')
            ->assertNotFound();
    }

    public function test_all_modules_have_boolean_values(): void
    {
        $modules = ModuleServiceProvider::all();
        foreach ($modules as $name => $enabled) {
            $this->assertIsBool($enabled, "Module '{$name}' should be boolean");
        }
    }
}
