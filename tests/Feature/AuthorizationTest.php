<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_other_users_booking(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user1->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(8),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($user2)->getJson("/api/bookings/{$booking->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_cancel_other_users_booking(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user1->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(8),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($user2)->deleteJson("/api/bookings/{$booking->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_other_users_vehicle(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id' => $user1->id,
            'plate' => 'AB-CD-1234',
        ]);

        $this->actingAs($user2)->deleteJson("/api/vehicles/{$vehicle->id}")
            ->assertStatus(404);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create();
        $user->role = 'user';
        $user->save();

        $this->actingAs($user)->getJson('/api/admin/stats')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/admin/users')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/admin/settings')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/admin/audit-log')->assertStatus(403);
    }

    public function test_admin_can_access_admin_endpoints(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        $this->actingAs($admin)->getJson('/api/admin/stats')->assertStatus(200);
        $this->actingAs($admin)->getJson('/api/admin/users')->assertStatus(200);
        $this->actingAs($admin)->getJson('/api/admin/settings')->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->getJson('/api/bookings')->assertStatus(401);
        $this->getJson('/api/vehicles')->assertStatus(401);
        $this->getJson('/api/lots')->assertStatus(401);
    }

    public function test_user_can_only_see_own_bookings(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);
        $slot2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '002', 'status' => 'available']);

        Booking::create([
            'user_id' => $user1->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot1->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(8),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Booking::create([
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot2->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(8),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($user1)->getJson('/api/bookings');
        $response->assertStatus(200);
        // The response is paginated and wrapped; check that we see exactly 1 booking
        $data = $response->json('data.data') ?? $response->json('data');
        // Ensure only user1's booking is returned (not user2's)
        $this->assertNotEmpty($data);
        $bookingUserIds = collect($data)->pluck('user_id')->unique()->values()->all();
        $this->assertEquals([$user1->id], $bookingUserIds);
    }

    public function test_user_cannot_update_other_users_vehicle(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id' => $user1->id,
            'plate' => 'AB-CD-1234',
        ]);

        $this->actingAs($user2)->putJson("/api/vehicles/{$vehicle->id}", [
            'plate' => 'STOLEN',
        ])->assertStatus(404);
    }
}
