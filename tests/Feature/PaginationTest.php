<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 100,
            'available_slots' => 100,
            'status' => 'open',
        ]);

        return [$user, $lot];
    }

    private function createSlot(string $lotId, string $number): ParkingSlot
    {
        return ParkingSlot::create([
            'lot_id' => $lotId,
            'slot_number' => $number,
            'status' => 'available',
        ]);
    }

    public function test_booking_index_returns_paginated_results(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $slot = $this->createSlot($lot->id, 'A1');
        $token = $user->createToken('test')->plainTextToken;

        // Create 5 bookings
        for ($i = 1; $i <= 5; $i++) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays($i),
                'end_time' => now()->addDays($i)->addHours(2),
                'booking_type' => 'einmalig',
                'status' => 'confirmed',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/bookings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'meta' => ['pagination' => ['current_page', 'last_page', 'per_page', 'total']],
        ]);

        $this->assertSame(5, $response->json('meta.pagination.total'));
        $this->assertIsArray($response->json('data'));
    }

    public function test_booking_index_respects_per_page_param(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $slot = $this->createSlot($lot->id, 'A1');
        $token = $user->createToken('test')->plainTextToken;

        for ($i = 1; $i <= 10; $i++) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays($i),
                'end_time' => now()->addDays($i)->addHours(2),
                'booking_type' => 'einmalig',
                'status' => 'confirmed',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/bookings?per_page=3');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(3, $response->json('meta.pagination.per_page'));
        $this->assertEquals(10, $response->json('meta.pagination.total'));
    }

    public function test_booking_index_page_2_returns_correct_results(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $slot = $this->createSlot($lot->id, 'A1');
        $token = $user->createToken('test')->plainTextToken;

        for ($i = 1; $i <= 5; $i++) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays($i),
                'end_time' => now()->addDays($i)->addHours(2),
                'booking_type' => 'einmalig',
                'status' => 'confirmed',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/bookings?per_page=3&page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.pagination.current_page'));
    }

    public function test_booking_index_per_page_capped_at_200(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/bookings?per_page=9999');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(200, $response->json('meta.pagination.per_page'));
    }

    public function test_swap_requests_returns_paginated_incoming_and_outgoing(): void
    {
        [$requester, $lot] = $this->createUserAndLot();
        [$target] = $this->createUserAndLot();
        $token = $requester->createToken('test')->plainTextToken;

        $requesterSlot = $this->createSlot($lot->id, 'B1');
        $targetSlot = $this->createSlot($lot->id, 'B2');

        $requesterBooking = Booking::create([
            'user_id' => $requester->id,
            'lot_id' => $lot->id,
            'slot_id' => $requesterSlot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'booking_type' => 'einmalig',
            'status' => 'active',
        ]);

        $targetBooking = Booking::create([
            'user_id' => $target->id,
            'lot_id' => $lot->id,
            'slot_id' => $targetSlot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'booking_type' => 'einmalig',
            'status' => 'active',
        ]);

        SwapRequest::create([
            'requester_booking_id' => $requesterBooking->id,
            'target_booking_id' => $targetBooking->id,
            'requester_id' => $requester->id,
            'target_id' => $target->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/swap-requests');

        // swapRequests() now returns a flat SwapRequest[] (matching the
        // frontend's typed expectation) instead of the old paginated
        // {incoming, outgoing} envelope — direction is derivable from
        // requester_id vs the current user.
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($requester->id, $response->json('data.0.requester_id'));
    }
}
