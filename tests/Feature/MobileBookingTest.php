<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot as Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config(['parkhub.modules.mobile' => true]);
    }

    public function test_unauthenticated_cannot_access_mobile_endpoints(): void
    {
        $this->getJson('/api/v1/mobile/nearby-lots?lat=48.8&lng=9.1')
            ->assertUnauthorized();
    }

    public function test_nearby_lots_requires_coordinates(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/nearby-lots')
            ->assertStatus(422);
    }

    public function test_nearby_lots_validates_lat_range(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/nearby-lots?lat=100&lng=9.1')
            ->assertStatus(422);
    }

    public function test_nearby_lots_returns_lots_within_radius(): void
    {
        Lot::factory()->create([
            'name' => 'Close Lot',
            'latitude' => 48.7758,
            'longitude' => 9.1829,
            'total_slots' => 50,
        ]);
        Lot::factory()->create([
            'name' => 'Far Lot',
            'latitude' => 49.5,
            'longitude' => 10.0,
            'total_slots' => 30,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/nearby-lots?lat=48.7760&lng=9.1830&radius=5000')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Close Lot', $data[0]['name']);
        $this->assertArrayHasKey('distance_meters', $data[0]);
    }

    public function test_nearby_lots_default_radius(): void
    {
        Lot::factory()->create([
            'latitude' => 48.7758,
            'longitude' => 9.1829,
            'total_slots' => 10,
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/nearby-lots?lat=48.7760&lng=9.1830')
            ->assertOk()
            ->assertJsonPath('meta.radius', 1000);
    }

    public function test_quick_book_returns_lots_with_availability(): void
    {
        Lot::factory()->create(['name' => 'Full Lot', 'total_slots' => 0]);
        Lot::factory()->create(['name' => 'Available Lot', 'total_slots' => 10]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/quick-book')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $names = collect($data)->pluck('name')->all();
        $this->assertContains('Available Lot', $names);
        $this->assertNotContains('Full Lot', $names);
    }

    public function test_nearby_lots_reflects_active_booking_counts(): void
    {
        $lot = Lot::factory()->create([
            'name' => 'Partially Filled Lot',
            'latitude' => 48.7758,
            'longitude' => 9.1829,
            'total_slots' => 5,
        ]);

        // 3 active confirmed bookings that reduce available slots
        Booking::factory()->count(3)->create([
            'lot_id' => $lot->id,
            'status' => 'confirmed',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        // Expired booking — should not be counted
        Booking::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'confirmed',
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/nearby-lots?lat=48.7760&lng=9.1830&radius=5000')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(2, $data[0]['available_slots']);
        $this->assertEquals(5, $data[0]['total_slots']);
        $this->assertEquals(60.0, $data[0]['occupancy_percent']);
    }

    public function test_quick_book_excludes_fully_booked_lots(): void
    {
        $fullLot = Lot::factory()->create(['name' => 'Full Lot', 'total_slots' => 2]);
        $availableLot = Lot::factory()->create(['name' => 'Available Lot', 'total_slots' => 3]);

        // Fill all 2 slots in the full lot
        Booking::factory()->count(2)->create([
            'lot_id' => $fullLot->id,
            'status' => 'confirmed',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        // 1 slot filled in the available lot (2 slots remain)
        Booking::factory()->create([
            'lot_id' => $availableLot->id,
            'status' => 'confirmed',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/quick-book')
            ->assertOk();

        $data = $response->json('data');
        $names = collect($data)->pluck('name')->all();
        $this->assertNotContains('Full Lot', $names);
        $this->assertContains('Available Lot', $names);

        $availableEntry = collect($data)->firstWhere('name', 'Available Lot');
        $this->assertEquals(2, $availableEntry['available_slots']);
    }

    public function test_active_booking_returns_null_when_none(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/active-booking')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_active_booking_returns_current_booking(): void
    {
        $lot = Lot::factory()->create(['name' => 'Test Lot']);
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'lot_id' => $lot->id,
            'status' => 'confirmed',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/active-booking')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals('Test Lot', $data['lot_name']);
        $this->assertGreaterThan(0, $data['remaining_seconds']);
        $this->assertLessThan(100, $data['progress_percent']);
    }

    public function test_active_booking_ignores_expired(): void
    {
        $lot = Lot::factory()->create();
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'lot_id' => $lot->id,
            'status' => 'confirmed',
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/active-booking')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_active_booking_ignores_cancelled(): void
    {
        $lot = Lot::factory()->create();
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'lot_id' => $lot->id,
            'status' => 'cancelled',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/active-booking')
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}
