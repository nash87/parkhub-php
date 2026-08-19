<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function setupLotAndSlot(): array
    {
        $lot = ParkingLot::create(['name' => 'Policy Lot', 'total_slots' => 5, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'P1', 'status' => 'available']);

        return [$lot, $slot];
    }

    public function test_booking_too_far_in_advance_rejected(): void
    {
        config(['parkhub.max_advance_days' => 7]);

        [$lot, $slot] = $this->setupLotAndSlot();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays(30)->toDateTimeString(),
                'end_time' => now()->addDays(30)->addHours(2)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BOOKING_TOO_FAR_AHEAD');
    }

    public function test_max_active_bookings_enforced(): void
    {
        config(['parkhub.max_active_bookings' => 2]);

        [$lot, $slot] = $this->setupLotAndSlot();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create 2 active bookings
        for ($i = 0; $i < 2; $i++) {
            $s = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'X'.$i, 'status' => 'available']);
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $s->id,
                'lot_name' => $lot->name,
                'slot_number' => 'X'.$i,
                'start_time' => now()->addHours($i + 1),
                'end_time' => now()->addHours($i + 3),
                'status' => 'confirmed',
                'booking_type' => 'standard',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addHours(10)->toDateTimeString(),
                'end_time' => now()->addHours(12)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'MAX_ACTIVE_BOOKINGS');
    }

    /**
     * Regression for nash87/parkhub-php#586.
     *
     * `max_active_bookings` counted every `confirmed`/`active` row for the
     * user regardless of whether the booking had already ended. Because
     * nothing ever transitioned an elapsed booking out of `confirmed`, the
     * allowance was consumed permanently and the user could never book
     * again. The cap must only count bookings that have not yet ended.
     */
    public function test_elapsed_bookings_do_not_consume_the_active_allowance(): void
    {
        config(['parkhub.max_active_bookings' => 2]);

        [$lot, $slot] = $this->setupLotAndSlot();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Two bookings that are still `confirmed` but finished in the past.
        for ($i = 0; $i < 2; $i++) {
            $s = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'E'.$i, 'status' => 'available']);
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $s->id,
                'lot_name' => $lot->name,
                'slot_number' => 'E'.$i,
                'start_time' => now()->subDays($i + 2),
                'end_time' => now()->subDays($i + 2)->addHours(2),
                'status' => 'confirmed',
                'booking_type' => 'standard',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addHours(10)->toDateTimeString(),
                'end_time' => now()->addHours(12)->toDateTimeString(),
            ]);

        $response->assertStatus(201);
    }

    /**
     * Guards the daily quota against the fix for #586.
     *
     * `CompleteElapsedBookingsJob` moves elapsed bookings to `completed`.
     * If the per-day cap only counted `confirmed`/`active`, a user could
     * exhaust the quota in the morning, wait for those bookings to elapse
     * and be completed, and then book the same day all over again. A
     * fulfilled booking must keep consuming its day's quota; only
     * `cancelled` / `no_show` release it.
     */
    public function test_completed_bookings_still_consume_the_daily_quota(): void
    {
        Setting::set('max_bookings_per_day', '1');

        [$lot, $slot] = $this->setupLotAndSlot();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $target = now()->addDay()->setTime(9, 0);

        $s = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'Q1', 'status' => 'available']);
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $s->id,
            'lot_name' => $lot->name,
            'slot_number' => 'Q1',
            'start_time' => $target,
            'end_time' => (clone $target)->addHours(2),
            'status' => 'completed',
            'booking_type' => 'standard',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => (clone $target)->addHours(5)->toDateTimeString(),
                'end_time' => (clone $target)->addHours(7)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'MAX_BOOKINGS_REACHED');
    }

    public function test_admin_bypasses_booking_policies(): void
    {
        config(['parkhub.max_advance_days' => 7]);

        [$lot, $slot] = $this->setupLotAndSlot();
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays(30)->toDateTimeString(),
                'end_time' => now()->addDays(30)->addHours(2)->toDateTimeString(),
            ]);

        // Should not be rejected for advance days (admin bypass)
        $this->assertNotEquals(422, $response->status());
    }
}
