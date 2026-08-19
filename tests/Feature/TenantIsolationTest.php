<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-tenant isolation must actually isolate.
 *
 * `App\Http\Middleware\TenantScope` is the only writer of the
 * `current_tenant` container binding, and it was never registered — not in
 * the api group, not in the alias map, not on any route. Both isolation
 * layers read that binding and no-op without it: `BelongsToTenantScope`
 * returns early when it is unbound, and `App\Support\TenantScope::currentId()`
 * returns null, which turns every hand-written defence-in-depth predicate
 * into a no-op too.
 *
 * So enabling `MODULE_MULTI_TENANT` bought nothing, silently.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.multi_tenant' => true]);
    }

    /** @return array{Tenant, User, string} */
    private function tenantWithUser(string $name): array
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => strtolower($name)]);
        $user = User::factory()->create(['role' => 'user', 'tenant_id' => $tenant->id]);

        return [$tenant, $user, $user->createToken('test')->plainTextToken];
    }

    public function test_a_request_resolves_the_callers_tenant(): void
    {
        [, , $token] = $this->tenantWithUser('Acme');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots')
            ->assertOk();

        $this->assertTrue(
            app()->bound('current_tenant'),
            'TenantScope never ran, so every tenant guard in the codebase silently no-ops.',
        );
    }

    /**
     * The observable consequence: a tenant-scoped model must not return
     * another tenant's rows.
     */
    public function test_a_user_does_not_see_another_tenants_parking_lots(): void
    {
        [$acme, , $acmeToken] = $this->tenantWithUser('Acme');
        [$globex] = $this->tenantWithUser('Globex');

        ParkingLot::create(['name' => 'Acme Lot', 'total_slots' => 5, 'status' => 'open', 'tenant_id' => $acme->id]);
        ParkingLot::create(['name' => 'Globex Lot', 'total_slots' => 5, 'status' => 'open', 'tenant_id' => $globex->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$acmeToken)
            ->getJson('/api/v1/lots')
            ->assertOk();

        $body = json_encode($response->json());

        $this->assertStringContainsString('Acme Lot', (string) $body);
        $this->assertStringNotContainsString('Globex Lot', (string) $body, 'another tenant\'s lot was returned');
    }

    /** Single-tenant installs must be completely unaffected. */
    public function test_single_tenant_mode_is_untouched(): void
    {
        config(['modules.multi_tenant' => false]);

        $user = User::factory()->create(['role' => 'user']);
        ParkingLot::create(['name' => 'Only Lot', 'total_slots' => 5, 'status' => 'open']);

        $response = $this->withHeader('Authorization', 'Bearer '.$user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/lots')
            ->assertOk();

        $this->assertStringContainsString('Only Lot', (string) json_encode($response->json()));
    }
}
