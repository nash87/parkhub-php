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

    /**
     * Core safety test: two bookings for the same slot at overlapping times must be rejected.
     */
    public function test_double_booking_same_slot_is_rejected(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);

        $start = now()->addHour()->toISOString();
        $end   = now()->addHours(3)->toISOString();

        // First booking succeeds
        $token1 = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/bookings', [
                'lot_id'       => $lot->id,
                'slot_id'      => $slot->id,
                'start_time'   => $start,
                'end_time'     => $end,
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        // Second booking for the exact same slot and time window must be rejected with 409
        $token2 = $user2->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/bookings', [
                'lot_id'       => $lot->id,
                'slot_id'      => $slot->id,
                'start_time'   => $start,
                'end_time'     => $end,
                'booking_type' => 'single',
            ])
            ->assertStatus(409);

        // Only one booking should exist in the database
        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * Partially overlapping booking for the same slot must also be rejected.
     */
    public function test_partial_overlap_booking_is_rejected(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);

        // First booking: 10:00–14:00
        $token1 = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/bookings', [
                'lot_id'       => $lot->id,
                'slot_id'      => $slot->id,
                'start_time'   => now()->setHour(10)->setMinute(0)->toISOString(),
                'end_time'     => now()->setHour(14)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        // Second booking: 12:00–16:00 overlaps the first — must fail
        $token2 = $user2->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/bookings', [
                'lot_id'       => $lot->id,
                'slot_id'      => $slot->id,
                'start_time'   => now()->setHour(12)->setMinute(0)->toISOString(),
                'end_time'     => now()->setHour(16)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(409);
    }

    /**
     * Non-overlapping bookings for the same slot on the same day must be allowed.
     */
    public function test_non_overlapping_bookings_same_slot_are_allowed(): void
    {
        list($user, $lot, $slot) = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);

        // First booking: 08:00–12:00
        $token1 = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/bookings', [
                'lot_id'       => $lot->id,
                'slot_id'      => $slot->id,
                'start_time'   => now()->setHour(8)->setMinute(0)->toISOString(),
                'end_time'     => now()->setHour(12)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        // Second booking: 13:00–17:00 — no overlap, must succeed
        $token2 = $user2->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/bookings', [
                'lot_id'       => $lot->id,
                'slot_id'      => $slot->id,
                'start_time'   => now()->setHour(13)->setMinute(0)->toISOString(),
                'end_time'     => now()->setHour(17)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('bookings', 2);
    }

    /**
     * A user must not be able to update notes on another user's booking.
     */
    public function test_user_cannot_update_notes_on_another_users_booking(): void
    {
        list($owner, $lot, $slot) = $this->createUserAndLot();
        $attacker = User::factory()->create(['role' => 'user']);

        $booking = Booking::create([
            'user_id'      => $owner->id,
            'lot_id'       => $lot->id,
            'slot_id'      => $slot->id,
            'start_time'   => now()->addHour(),
            'end_time'     => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $attackerToken)
            ->putJson('/api/bookings/' . $booking->id . '/notes', ['notes' => 'Hacked'])
            ->assertStatus(404); // findOrFail scoped to user_id should return 404
    }
}
