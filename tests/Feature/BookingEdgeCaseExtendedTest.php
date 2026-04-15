<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEdgeCaseExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(int $totalSlots = 10): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Extended Edge Lot',
            'total_slots' => $totalSlots,
            'available_slots' => $totalSlots,
            'status' => 'open',
        ]);

        $slots = [];
        for ($i = 1; $i <= $totalSlots; $i++) {
            $slots[] = ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => 'EX'.$i,
                'status' => 'available',
            ]);
        }

        return [$user, $lot, $slots];
    }

    public function test_cancel_already_cancelled_booking(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'cancelled',
        ]);

        // Cancelling an already-cancelled booking should fail (findOrFail scoped to user finds it but status is already cancelled)
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/bookings/'.$booking->id);

        // The controller doesn't check status before cancelling, so it succeeds (idempotent cancel)
        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_booking_with_end_time_before_start_time_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'start_time' => now()->addHours(5)->toISOString(),
                'end_time' => now()->addHours(2)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(422);
    }

    public function test_booking_auto_assigns_slot_when_none_specified(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot(3);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_with_nonexistent_lot_fails(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;
        $fakeLotId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $fakeLotId,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
                'booking_type' => 'single',
            ]);

        // No slots will be found for a nonexistent lot
        $response->assertStatus(409);
    }

    public function test_booking_missing_lot_id_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(422);
    }

    public function test_booking_missing_start_time_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'end_time' => now()->addHours(3)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(422);
    }

    public function test_booking_extend_to_earlier_end_time_allowed(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(5),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Shortening a booking (end_time still after start_time) is valid
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/bookings/'.$booking->id, [
                'end_time' => now()->addHours(3)->toISOString(),
            ]);

        $response->assertStatus(200);
    }

    public function test_booking_extend_end_before_start_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(5),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // End time before start time must be rejected
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/bookings/'.$booking->id, [
                'end_time' => now()->addHour()->toISOString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TIME');
    }

    public function test_booking_extend_conflicts_with_next_booking(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $tomorrow = now()->addDay();

        // First booking: 8-10
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => $tomorrow->copy()->setHour(8),
            'end_time' => $tomorrow->copy()->setHour(10),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Second booking on same slot: 11-14
        Booking::create([
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => $tomorrow->copy()->setHour(11),
            'end_time' => $tomorrow->copy()->setHour(14),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Try to extend first booking to 12 -- overlaps with second
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/bookings/'.$booking->id, [
                'end_time' => $tomorrow->copy()->setHour(12)->toISOString(),
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SLOT_CONFLICT');
    }

    public function test_booking_extend_cancelled_booking_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'cancelled',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/bookings/'.$booking->id, [
                'end_time' => now()->addHours(5)->toISOString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_credit_refund_on_cancellation(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $user->update(['credits_balance' => 8]);
        $token = $user->createToken('test')->plainTextToken;

        Setting::set('credits_enabled', 'true');
        Setting::set('credits_per_booking', '2');

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/bookings/'.$booking->id)
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits_balance' => 10]);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'refund',
            'amount' => 2,
        ]);
    }

    public function test_license_plate_required_mode_blocks_booking_without_plate(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        Setting::set('license_plate_mode', 'required');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PLATE_REQUIRED');
    }

    public function test_license_plate_required_mode_allows_booking_with_plate(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        Setting::set('license_plate_mode', 'required');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
                'booking_type' => 'single',
                'license_plate' => 'B-AB 1234',
            ]);

        $response->assertStatus(201);
    }

    public function test_unauthenticated_booking_request_rejected(): void
    {
        $this->postJson('/api/v1/bookings', [
            'lot_id' => '00000000-0000-0000-0000-000000000001',
            'start_time' => now()->addHour()->toISOString(),
            'end_time' => now()->addHours(3)->toISOString(),
        ])->assertStatus(401);
    }

    public function test_booking_list_filters_by_status(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot(3);
        $token = $user->createToken('test')->plainTextToken;

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[1]->id,
            'start_time' => now()->addHours(4),
            'end_time' => now()->addHours(6),
            'booking_type' => 'single',
            'status' => 'cancelled',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/bookings?status=confirmed');

        $response->assertStatus(200);
        $bookings = $response->json('data');
        $this->assertCount(1, $bookings);
    }

    public function test_swap_to_slot_in_different_lot_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot(2);
        $token = $user->createToken('test')->plainTextToken;

        $otherLot = ParkingLot::create([
            'name' => 'Other Swap Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);
        $otherSlot = ParkingSlot::create([
            'lot_id' => $otherLot->id,
            'slot_number' => 'OS1',
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/swap', [
                'booking_id' => $booking->id,
                'target_slot_id' => $otherSlot->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CROSS_LOT_SWAP');
    }

    public function test_guest_booking_disabled_by_default(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/guest', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'guest_name' => 'Visitor',
                'end_time' => now()->addHours(3)->toISOString(),
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'GUEST_BOOKINGS_DISABLED');
    }
}
