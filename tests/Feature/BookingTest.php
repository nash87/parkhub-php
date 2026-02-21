<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);
        return [$user, $lot, $slot];
    }

    public function test_user_can_create_booking(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', ['user_id' => $user->id]);
    }

    public function test_user_can_list_bookings(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/bookings');

        $response->assertStatus(200);
    }

    public function test_user_can_delete_booking(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/bookings/' . $booking->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_quick_booking_works(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/bookings/quick', [
                'lot_id' => $lot->id,
                'date' => now()->addDay()->format('Y-m-d'),
                'booking_type' => 'full_day',
            ]);

        $response->assertStatus(200);
    }

    public function test_guest_booking_works(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/bookings/guest', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(2)->toISOString(),
                'guest_name' => 'Guest User',
                'guest_email' => 'guest@example.com',
            ]);

        $response->assertStatus(201);
    }

    public function test_user_can_update_booking_notes(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/bookings/' . $booking->id . '/notes', [
                'notes' => 'Test note',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', ['notes' => 'Test note']);
    }
}
