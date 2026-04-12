<?php

namespace Tests\Integration;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $adminToken;

    protected string $userToken;

    protected User $adminUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'name' => 'Integration Admin',
            'email' => 'admin-integration@parkhub.test',
            'password' => bcrypt('AdminPass123'),
            'credits_balance' => 100,
            'credits_monthly_quota' => 100,
        ]);
        $this->adminToken = $this->adminUser->createToken('integration-test')->plainTextToken;

        $this->regularUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Integration User',
            'email' => 'user-integration@parkhub.test',
            'password' => bcrypt('UserPass123'),
            'credits_balance' => 50,
            'credits_monthly_quota' => 50,
        ]);
        $this->userToken = $this->regularUser->createToken('integration-test')->plainTextToken;
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->adminToken];
    }

    protected function userHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->userToken];
    }

    protected function createLotWithSlots(int $slotCount = 10, array $lotOverrides = []): ParkingLot
    {
        $lot = ParkingLot::create(array_merge([
            'name' => 'Integration Test Lot '.$this->faker->word(),
            'total_slots' => $slotCount,
            'available_slots' => $slotCount,
            'status' => 'open',
        ], $lotOverrides));

        for ($i = 1; $i <= $slotCount; $i++) {
            ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => sprintf('S%02d', $i),
                'status' => 'available',
            ]);
        }

        return $lot->fresh();
    }

    protected function createBooking(string $token, string $lotId, ?string $slotId = null, array $overrides = []): TestResponse
    {
        $lot = ParkingLot::find($lotId);
        if (! $slotId && $lot) {
            $slot = $lot->slots()->where('status', 'available')->first();
            $slotId = $slot?->id;
        }

        $payload = array_merge([
            'lot_id' => $lotId,
            'slot_id' => $slotId,
            'start_time' => now()->addDay()->setHour(9)->setMinute(0)->toISOString(),
            'end_time' => now()->addDay()->setHour(17)->setMinute(0)->toISOString(),
            'booking_type' => 'single',
        ], $overrides);

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', $payload);
    }

    protected function createTokenForUser(User $user): string
    {
        return $user->createToken('integration-test')->plainTextToken;
    }
}
