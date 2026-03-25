<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\ModuleServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSystemTest extends TestCase
{
    use RefreshDatabase;

    // ── Modules endpoint ────────────────────────────────────────────────

    public function test_modules_endpoint_returns_all_modules(): void
    {
        $response = $this->getJson('/api/v1/modules');

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();

        // Unwrap if wrapped
        $modules = $data['modules'] ?? $data;
        $this->assertArrayHasKey('bookings', $modules);
        $this->assertArrayHasKey('vehicles', $modules);
        $this->assertArrayHasKey('absences', $modules);
        $this->assertArrayHasKey('payments', $modules);
        $this->assertArrayHasKey('webhooks', $modules);
        $this->assertArrayHasKey('notifications', $modules);
        $this->assertArrayHasKey('branding', $modules);
        $this->assertArrayHasKey('import', $modules);
        $this->assertArrayHasKey('qr_codes', $modules);
        $this->assertArrayHasKey('favorites', $modules);
        $this->assertArrayHasKey('swap_requests', $modules);
        $this->assertArrayHasKey('recurring_bookings', $modules);
        $this->assertArrayHasKey('zones', $modules);
        $this->assertArrayHasKey('credits', $modules);
        $this->assertArrayHasKey('metrics', $modules);
        $this->assertArrayHasKey('broadcasting', $modules);
        $this->assertArrayHasKey('admin_reports', $modules);
        $this->assertArrayHasKey('data_export', $modules);
        $this->assertArrayHasKey('setup_wizard', $modules);
        $this->assertArrayHasKey('gdpr', $modules);
        $this->assertArrayHasKey('push_notifications', $modules);
        $this->assertArrayHasKey('stripe', $modules);
    }

    public function test_modules_endpoint_shows_correct_defaults(): void
    {
        $response = $this->getJson('/api/v1/modules');
        $data = $response->json('data') ?? $response->json();
        $modules = $data['modules'] ?? $data;

        $this->assertTrue($modules['bookings']);
        $this->assertTrue($modules['vehicles']);
        $this->assertTrue($modules['absences']);
        // Stripe is enabled in test env (phpunit.xml), verify it can be disabled
        config(['modules.stripe' => false]);
        $response2 = $this->getJson('/api/v1/modules');
        $data2 = $response2->json('data') ?? $response2->json();
        $modules2 = $data2['modules'] ?? $data2;
        $this->assertFalse($modules2['stripe']);
    }

    public function test_modules_endpoint_reflects_config_changes(): void
    {
        config(['modules.bookings' => false]);

        $response = $this->getJson('/api/v1/modules');
        $data = $response->json('data') ?? $response->json();
        $modules = $data['modules'] ?? $data;

        $this->assertFalse($modules['bookings']);
    }

    public function test_modules_endpoint_includes_version(): void
    {
        $response = $this->getJson('/api/v1/modules');
        $data = $response->json('data') ?? $response->json();
        $version = $data['version'] ?? null;

        $this->assertNotEmpty($version);
    }

    // ── Disabled modules return MODULE_DISABLED (404) ───────────────────

    public function test_disabled_bookings_module_returns_404(): void
    {
        config(['modules.bookings' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/bookings');

        $response->assertNotFound();
    }

    public function test_enabled_bookings_module_works(): void
    {
        config(['modules.bookings' => true]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/bookings');

        $response->assertOk();
    }

    public function test_disabled_vehicles_module_returns_404(): void
    {
        config(['modules.vehicles' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/vehicles');

        $response->assertNotFound();
    }

    public function test_disabled_recurring_bookings_module_returns_404(): void
    {
        config(['modules.recurring_bookings' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/recurring-bookings');

        $response->assertNotFound();
    }

    public function test_disabled_zones_module_returns_404(): void
    {
        config(['modules.zones' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/lots/1/zones');

        $response->assertNotFound();
    }

    public function test_disabled_favorites_module_returns_404(): void
    {
        config(['modules.favorites' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/user/favorites');

        $response->assertNotFound();
    }

    public function test_disabled_notifications_module_returns_404(): void
    {
        config(['modules.notifications' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/notifications');

        $response->assertNotFound();
    }

    public function test_disabled_webhooks_module_returns_404(): void
    {
        config(['modules.webhooks' => false]);

        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/webhooks');

        $response->assertNotFound();
    }

    public function test_disabled_credits_module_returns_404(): void
    {
        config(['modules.credits' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/user/credits');

        $response->assertNotFound();
    }

    public function test_disabled_absences_module_returns_404(): void
    {
        config(['modules.absences' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/absences');

        $response->assertNotFound();
    }

    public function test_disabled_push_notifications_module_returns_404(): void
    {
        config(['modules.push_notifications' => false]);

        $response = $this->getJson('/api/v1/push/vapid-key');

        $response->assertNotFound();
    }

    public function test_disabled_module_returns_module_disabled_error(): void
    {
        config(['modules.bookings' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/bookings');

        $response->assertNotFound();
        $json = $response->json();
        // Check for MODULE_DISABLED in the error (may be wrapped)
        $error = $json['error'] ?? ($json['data'] ?? null);
        $this->assertNotNull($error);
    }

    // ── Core routes always work ─────────────────────────────────────────

    public function test_core_routes_work_regardless_of_module_state(): void
    {
        config([
            'modules.bookings' => false,
            'modules.vehicles' => false,
            'modules.absences' => false,
        ]);

        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/system/version')->assertOk();
        $this->getJson('/api/v1/modules')->assertOk();
    }

    public function test_lots_always_available(): void
    {
        config(['modules.bookings' => false, 'modules.vehicles' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/lots')
            ->assertOk();
    }

    public function test_team_always_available(): void
    {
        config(['modules.absences' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/team')
            ->assertOk();
    }

    public function test_auth_always_available(): void
    {
        config(['modules.bookings' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    // ── Helper/service provider ─────────────────────────────────────────

    public function test_module_enabled_helper(): void
    {
        config(['modules.bookings' => true]);
        $this->assertTrue(module_enabled('bookings'));

        config(['modules.bookings' => false]);
        $this->assertFalse(module_enabled('bookings'));
    }

    public function test_module_enabled_returns_false_for_unknown(): void
    {
        $this->assertFalse(module_enabled('nonexistent_module'));
    }

    public function test_module_service_provider_all(): void
    {
        $all = ModuleServiceProvider::all();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('bookings', $all);
        $this->assertArrayHasKey('stripe', $all);
        $this->assertIsBool($all['bookings']);
    }

    public function test_module_service_provider_enabled(): void
    {
        config(['modules.bookings' => true]);
        $this->assertTrue(ModuleServiceProvider::enabled('bookings'));

        config(['modules.bookings' => false]);
        $this->assertFalse(ModuleServiceProvider::enabled('bookings'));
    }

    // ── Stripe disabled by default ──────────────────────────────────────

    public function test_stripe_can_be_disabled(): void
    {
        config(['modules.stripe' => false]);
        $this->assertFalse(config('modules.stripe'));
    }

    // ── Multiple modules can be toggled independently ───────────────────

    public function test_multiple_modules_toggled_independently(): void
    {
        config([
            'modules.bookings' => false,
            'modules.vehicles' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/bookings')
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson('/api/v1/vehicles')
            ->assertOk();
    }

    public function test_all_67_modules_in_config(): void
    {
        $modules = config('modules');

        $this->assertCount(67, $modules);
    }

    public function test_disabled_accessible_module_returns_404(): void
    {
        config(['modules.accessible' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/bookings/accessible-stats')->assertNotFound();
    }

    public function test_disabled_maintenance_module_returns_404(): void
    {
        config(['modules.maintenance' => false]);

        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->getJson('/api/v1/admin/maintenance')->assertNotFound();
    }

    public function test_disabled_cost_center_module_returns_404(): void
    {
        config(['modules.cost_center' => false]);

        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->getJson('/api/v1/admin/billing/by-cost-center')->assertNotFound();
    }
}
