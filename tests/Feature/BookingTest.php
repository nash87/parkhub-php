<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
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
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
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
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/bookings');

        $response->assertStatus(200);
    }

    public function test_user_can_delete_booking(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/bookings/'.$booking->id);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_quick_booking_works(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', [
                'lot_id' => $lot->id,
                'date' => now()->addDay()->format('Y-m-d'),
                'booking_type' => 'full_day',
            ]);

        $response->assertStatus(200);
    }

    public function test_guest_booking_works(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        // Enable guest bookings for this test
        Setting::set('allow_guest_bookings', 'true');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/guest', [
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
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/bookings/'.$booking->id.'/notes', [
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
        [$user, $lot, $slot] = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);

        $start = now()->addHour()->toISOString();
        $end = now()->addHours(3)->toISOString();

        // First booking succeeds
        $token1 = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $start,
                'end_time' => $end,
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        // Second booking for the exact same slot and time window must be rejected with 409
        $token2 = $user2->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $start,
                'end_time' => $end,
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
        [$user, $lot, $slot] = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);

        // Use tomorrow to avoid "start time must be in the future" validation
        $tomorrow = now()->addDay();

        // First booking: 10:00–14:00
        $token1 = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $tomorrow->copy()->setHour(10)->setMinute(0)->toISOString(),
                'end_time' => $tomorrow->copy()->setHour(14)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        // Second booking: 12:00–16:00 overlaps the first — must fail
        $token2 = $user2->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $tomorrow->copy()->setHour(12)->setMinute(0)->toISOString(),
                'end_time' => $tomorrow->copy()->setHour(16)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(409);
    }

    /**
     * Non-overlapping bookings for the same slot on the same day must be allowed.
     */
    public function test_non_overlapping_bookings_same_slot_are_allowed(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);

        // Use tomorrow to avoid "start time must be in the future" validation
        $tomorrow = now()->addDay();

        // First booking: 08:00–12:00
        $token1 = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $tomorrow->copy()->setHour(8)->setMinute(0)->toISOString(),
                'end_time' => $tomorrow->copy()->setHour(12)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        // Second booking: 13:00–17:00 — no overlap, must succeed
        $token2 = $user2->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $tomorrow->copy()->setHour(13)->setMinute(0)->toISOString(),
                'end_time' => $tomorrow->copy()->setHour(17)->setMinute(0)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('bookings', 2);
    }

    /**
     * Simulates concurrent booking attempts: only the first succeeds, the second is rejected.
     * Verifies the DB-level lock (lockForUpdate) prevents double-booking under race conditions.
     */
    public function test_concurrent_bookings_dont_double_book(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);

        $start = now()->addHours(2)->toISOString();
        $end = now()->addHours(4)->toISOString();

        $token1 = $user->createToken('test')->plainTextToken;
        $token2 = $user2->createToken('test')->plainTextToken;

        $payload = [
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => $start,
            'end_time' => $end,
            'booking_type' => 'single',
        ];

        // Simulate two requests arriving "simultaneously" — sequential in test,
        // but the controller's DB transaction with lockForUpdate must prevent both succeeding.
        $r1 = $this->withHeader('Authorization', 'Bearer '.$token1)->postJson('/api/v1/bookings', $payload);
        $r2 = $this->withHeader('Authorization', 'Bearer '.$token2)->postJson('/api/v1/bookings', $payload);

        $statuses = [$r1->getStatusCode(), $r2->getStatusCode()];
        sort($statuses);

        // Exactly one must succeed (201) and one must be rejected (409)
        $this->assertEquals([201, 409], $statuses);
        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * A user must not be able to update notes on another user's booking.
     */
    public function test_user_cannot_update_notes_on_another_users_booking(): void
    {
        [$owner, $lot, $slot] = $this->createUserAndLot();
        $attacker = User::factory()->create(['role' => 'user']);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$attackerToken)
            ->putJson('/api/v1/bookings/'.$booking->id.'/notes', ['notes' => 'Hacked'])
            ->assertStatus(403);
    }
}
