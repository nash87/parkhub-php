<?php

namespace Tests\Unit\Jobs;

use App\Jobs\NotifyLotClosureJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyLotClosureJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_notification_for_user(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Main Parking', 'total_slots' => 10]);

        $job = new NotifyLotClosureJob($user->id, $lot->id, 'Maintenance work');
        $job->handle();

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $user->id,
            'type' => 'lot_closure',
        ]);
    }

    public function test_cancels_future_confirmed_bookings(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Main Parking', 'total_slots' => 10]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $job = new NotifyLotClosureJob($user->id, $lot->id, 'Maintenance');
        $job->handle();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_does_not_cancel_past_bookings(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Main Parking', 'total_slots' => 10]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $job = new NotifyLotClosureJob($user->id, $lot->id, 'Maintenance');
        $job->handle();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_does_nothing_when_user_not_found(): void
    {
        $lot = ParkingLot::create(['name' => 'Main Parking', 'total_slots' => 10]);

        $job = new NotifyLotClosureJob('nonexistent-user-id', $lot->id, 'Maintenance');
        $job->handle();

        $this->assertDatabaseCount('notifications_custom', 0);
    }

    public function test_does_nothing_when_lot_not_found(): void
    {
        $user = User::factory()->create();

        $job = new NotifyLotClosureJob($user->id, 'nonexistent-lot-id', 'Maintenance');
        $job->handle();

        $this->assertDatabaseCount('notifications_custom', 0);
    }
}
