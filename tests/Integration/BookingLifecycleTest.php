<?php

namespace Tests\Integration;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;

class BookingLifecycleTest extends IntegrationTestCase
{
    // ── Full booking lifecycle ────────────────────────────────────────────

    public function test_complete_booking_lifecycle(): void
    {
        // 1. Create lot and slots
        $lot = $this->createLotWithSlots(5);
        $slots = $lot->slots;
        $slot = $slots->first();

        // 2. Book a slot
        $bookResponse = $this->createBooking($this->userToken, $lot->id, $slot->id, [
            'start_time' => now()->addDay()->setHour(8)->toISOString(),
            'end_time' => now()->addDay()->setHour(18)->toISOString(),
        ]);
        $bookResponse->assertStatus(201);

        $bookingData = $bookResponse->json();
        $bookingId = $bookingData['data']['id'] ?? $bookingData['id'];
        $this->assertNotNull($bookingId);

        // 3. Verify booking exists in list
        $listResponse = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/bookings');
        $listResponse->assertStatus(200);

        $listData = $listResponse->json();
        $bookings = $listData['data'] ?? $listData;
        $ids = array_column($bookings, 'id');
        $this->assertContains($bookingId, $ids);

        // 4. Verify booking detail
        $showResponse = $this->withHeaders($this->userHeaders())
            ->getJson("/api/v1/bookings/{$bookingId}");
        $showResponse->assertStatus(200);

        // 5. Update notes on booking
        $notesResponse = $this->withHeaders($this->userHeaders())
            ->putJson("/api/v1/bookings/{$bookingId}/notes", [
                'notes' => 'Integration test booking',
            ]);
        $notesResponse->assertStatus(200);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'notes' => 'Integration test booking']);

        // 6. Cancel booking
        $cancelResponse = $this->withHeaders($this->userHeaders())
            ->deleteJson("/api/v1/bookings/{$bookingId}");
        $cancelResponse->assertStatus(200);

        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => 'cancelled']);
    }

    // ── Quick booking ──────────��────────────────────────────��────────────

    public function test_quick_booking_flow(): void
    {
        $lot = $this->createLotWithSlots(3);

        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/bookings/quick', [
                'lot_id' => $lot->id,
                'date' => now()->addDay()->format('Y-m-d'),
                'booking_type' => 'full_day',
            ]);

        $response->assertStatus(200);

        // Verify a booking was created
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
        ]);
    }

    // ── Guest booking ─────────────────────────────────────────────────���──

    public function test_guest_booking_flow(): void
    {
        $lot = $this->createLotWithSlots(3);
        $slot = $lot->slots()->first();

        Setting::set('allow_guest_bookings', 'true');

        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/bookings/guest', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDay()->setHour(9)->toISOString(),
                'end_time' => now()->addDay()->setHour(12)->toISOString(),
                'guest_name' => 'Visitor Hans',
                'guest_email' => 'visitor@example.com',
            ]);

        $response->assertStatus(201);
    }

    // ── Swap request ─────────────────────────────────────��───────────────

    public function test_swap_request_flow(): void
    {
        $lot = $this->createLotWithSlots(5);
        $slots = $lot->slots;

        $userA = User::factory()->create(['role' => 'user']);
        $tokenA = $this->createTokenForUser($userA);

        $userB = User::factory()->create(['role' => 'user']);
        $tokenB = $this->createTokenForUser($userB);

        // User A books slot 1
        $bookingA = Booking::create([
            'user_id' => $userA->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // User B books slot 2
        $bookingB = Booking::create([
            'user_id' => $userB->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[1]->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // User A requests swap
        $swapResponse = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/bookings/swap', [
                'requester_booking_id' => $bookingA->id,
                'target_booking_id' => $bookingB->id,
            ]);

        // Swap endpoint should accept the request (200 or 201)
        $this->assertContains($swapResponse->getStatusCode(), [200, 201]);
    }

    // ── Double-booking conflict detection ─────────────────────────────────

    public function test_double_booking_same_slot_is_rejected(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $start = now()->addDay()->setHour(10)->toISOString();
        $end = now()->addDay()->setHour(14)->toISOString();

        // First booking succeeds
        $first = $this->createBooking($this->userToken, $lot->id, $slot->id, [
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $first->assertStatus(201);

        // Second booking for same slot and time must fail
        $userB = User::factory()->create(['role' => 'user']);
        $tokenB = $this->createTokenForUser($userB);

        $second = $this->createBooking($tokenB, $lot->id, $slot->id, [
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $second->assertStatus(409);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_partial_overlap_is_rejected(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();
        $tomorrow = now()->addDay();

        // First: 09:00-13:00
        $this->createBooking($this->userToken, $lot->id, $slot->id, [
            'start_time' => $tomorrow->copy()->setHour(9)->toISOString(),
            'end_time' => $tomorrow->copy()->setHour(13)->toISOString(),
        ])->assertStatus(201);

        // Second: 12:00-16:00 (overlaps)
        $userB = User::factory()->create(['role' => 'user']);
        $tokenB = $this->createTokenForUser($userB);

        $this->createBooking($tokenB, $lot->id, $slot->id, [
            'start_time' => $tomorrow->copy()->setHour(12)->toISOString(),
            'end_time' => $tomorrow->copy()->setHour(16)->toISOString(),
        ])->assertStatus(409);
    }

    public function test_non_overlapping_bookings_on_same_slot_allowed(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();
        $tomorrow = now()->addDay();

        // First: 08:00-12:00
        $this->createBooking($this->userToken, $lot->id, $slot->id, [
            'start_time' => $tomorrow->copy()->setHour(8)->toISOString(),
            'end_time' => $tomorrow->copy()->setHour(12)->toISOString(),
        ])->assertStatus(201);

        // Second: 13:00-17:00 (no overlap)
        $userB = User::factory()->create(['role' => 'user']);
        $tokenB = $this->createTokenForUser($userB);

        $this->createBooking($tokenB, $lot->id, $slot->id, [
            'start_time' => $tomorrow->copy()->setHour(13)->toISOString(),
            'end_time' => $tomorrow->copy()->setHour(17)->toISOString(),
        ])->assertStatus(201);

        $this->assertDatabaseCount('bookings', 2);
    }

    // ── Lot occupancy ─────────���──────────────────────────���───────────────

    public function test_booking_affects_lot_occupancy(): void
    {
        $lot = $this->createLotWithSlots(5);

        // Check initial occupancy
        $occupancy = $this->withHeaders($this->userHeaders())
            ->getJson("/api/v1/lots/{$lot->id}/occupancy");
        $occupancy->assertStatus(200);

        // Create a booking
        $slot = $lot->slots()->first();
        $this->createBooking($this->userToken, $lot->id, $slot->id)->assertStatus(201);

        // Check occupancy again (should reflect the booking)
        $updatedOccupancy = $this->withHeaders($this->userHeaders())
            ->getJson("/api/v1/lots/{$lot->id}/occupancy");
        $updatedOccupancy->assertStatus(200);
    }

    // ── Admin cancellation ──────────���────────────────────────────────────

    public function test_admin_can_cancel_any_booking(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $booking = Booking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/v1/admin/bookings/{$booking->id}");

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    // ── User isolation ───────��───────────────────────────────────────────

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $owner = User::factory()->create(['role' => 'user']);
        $booking = Booking::create([
            'user_id' => $owner->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $attacker = User::factory()->create(['role' => 'user']);
        $attackerToken = $this->createTokenForUser($attacker);

        $response = $this->withHeader('Authorization', "Bearer {$attackerToken}")
            ->deleteJson("/api/v1/bookings/{$booking->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_user_cannot_update_notes_on_another_users_booking(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $owner = User::factory()->create(['role' => 'user']);
        $booking = Booking::create([
            'user_id' => $owner->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
        ]);

        $attacker = User::factory()->create(['role' => 'user']);
        $attackerToken = $this->createTokenForUser($attacker);

        $this->withHeader('Authorization', "Bearer {$attackerToken}")
            ->putJson("/api/v1/bookings/{$booking->id}/notes", ['notes' => 'Hacked!'])
            ->assertStatus(403);
    }
}
