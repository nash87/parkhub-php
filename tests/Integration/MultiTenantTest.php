<?php

namespace Tests\Integration;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Tenant;
use App\Models\User;

class MultiTenantTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.multi_tenant' => true]);
    }

    // ── Full tenant isolation test ───────────────────────────────────────

    public function test_full_tenant_isolation(): void
    {
        // 1. Create Tenant A
        $tenantAResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/tenants', [
                'name' => 'Acme Corp',
                'domain' => 'acme.example.com',
                'branding' => ['primary_color' => '#FF0000'],
            ]);
        $tenantAResponse->assertStatus(201);
        $tenantAId = $tenantAResponse->json('data.id');

        // 2. Create Tenant B
        $tenantBResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/tenants', [
                'name' => 'Globex Inc',
                'domain' => 'globex.example.com',
                'branding' => ['primary_color' => '#0000FF'],
            ]);
        $tenantBResponse->assertStatus(201);
        $tenantBId = $tenantBResponse->json('data.id');

        // 3. Create users for each tenant
        $userA = User::factory()->create([
            'tenant_id' => $tenantAId,
            'role' => 'user',
            'name' => 'Acme Employee',
        ]);
        $tokenA = $this->createTokenForUser($userA);

        $userB = User::factory()->create([
            'tenant_id' => $tenantBId,
            'role' => 'user',
            'name' => 'Globex Employee',
        ]);
        $tokenB = $this->createTokenForUser($userB);

        // 4. Create lots for each tenant
        $lotA = ParkingLot::create([
            'name' => 'Acme Garage',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
            'tenant_id' => $tenantAId,
        ]);
        ParkingSlot::create([
            'lot_id' => $lotA->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);

        $lotB = ParkingLot::create([
            'name' => 'Globex Parking',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
            'tenant_id' => $tenantBId,
        ]);
        ParkingSlot::create([
            'lot_id' => $lotB->id,
            'slot_number' => 'B1',
            'status' => 'available',
        ]);

        // 5. Verify tenant list shows both tenants with correct counts
        $tenantList = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/tenants');
        $tenantList->assertStatus(200);

        $tenants = collect($tenantList->json('data'));
        $acme = $tenants->firstWhere('name', 'Acme Corp');
        $globex = $tenants->firstWhere('name', 'Globex Inc');

        $this->assertNotNull($acme);
        $this->assertNotNull($globex);
        $this->assertEquals(1, $acme['user_count']);
        $this->assertEquals(1, $acme['lot_count']);
        $this->assertEquals(1, $globex['user_count']);
        $this->assertEquals(1, $globex['lot_count']);

        // 6. Verify user belongs to correct tenant
        $this->assertEquals($tenantAId, $userA->fresh()->tenant_id);
        $this->assertEquals($tenantBId, $userB->fresh()->tenant_id);
        $this->assertInstanceOf(Tenant::class, $userA->fresh()->tenant);
    }

    // ── Admin can see all tenants ─────────────────────────────────────────

    public function test_admin_can_list_all_tenants(): void
    {
        Tenant::create(['id' => fake()->uuid(), 'name' => 'Tenant One']);
        Tenant::create(['id' => fake()->uuid(), 'name' => 'Tenant Two']);
        Tenant::create(['id' => fake()->uuid(), 'name' => 'Tenant Three']);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/tenants');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    // ── Tenant CRUD ──────────────────────────────────────────────────────

    public function test_tenant_create_update_lifecycle(): void
    {
        // Create
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/tenants', [
                'name' => 'Original Name',
                'domain' => 'original.example.com',
            ]);
        $createResponse->assertStatus(201);
        $tenantId = $createResponse->json('data.id');

        // Update
        $updateResponse = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v1/admin/tenants/{$tenantId}", [
                'name' => 'Updated Name',
                'domain' => 'updated.example.com',
            ]);
        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('data.name', 'Updated Name');
        $updateResponse->assertJsonPath('data.domain', 'updated.example.com');
    }

    // ── Regular users cannot access tenant management ─────────────────────

    public function test_regular_user_cannot_manage_tenants(): void
    {
        $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/admin/tenants')
            ->assertStatus(403);

        $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/admin/tenants', ['name' => 'Hack Corp'])
            ->assertStatus(403);
    }

    // ── Unauthenticated access blocked ───────────────────────────────────

    public function test_unauthenticated_cannot_access_tenants(): void
    {
        $this->getJson('/api/v1/admin/tenants')
            ->assertStatus(401);
    }

    // ── Tenant validation ─────────────────────────────────────────────────

    public function test_tenant_create_requires_name(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/tenants', []);

        $response->assertStatus(422);
    }

    public function test_update_nonexistent_tenant_returns_404(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/v1/admin/tenants/nonexistent-uuid', ['name' => 'Test'])
            ->assertStatus(404);
    }

    // ── Module disabled returns 404 ──────────────────────────────────────

    public function test_disabled_module_returns_404(): void
    {
        config(['modules.multi_tenant' => false]);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/tenants')
            ->assertStatus(404);
    }

    // ── Tenant branding ───────────────────────────────────────────────────

    public function test_tenant_branding_stored_correctly(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/tenants', [
                'name' => 'Branded Corp',
                'branding' => [
                    'primary_color' => '#FF5733',
                    'logo_url' => 'https://example.com/logo.png',
                    'company_tagline' => 'Park Smart',
                ],
            ]);

        $response->assertStatus(201);
        $branding = $response->json('data.branding');
        $this->assertEquals('#FF5733', $branding['primary_color']);
    }
}
