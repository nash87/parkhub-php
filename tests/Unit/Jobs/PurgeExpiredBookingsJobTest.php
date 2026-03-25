<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PurgeExpiredBookingsJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeExpiredBookingsJobTest extends TestCase
{
    use RefreshDatabase;

    private function createBooking(User $user, string $status, int $daysAgo): Booking
    {
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        return Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subDays($daysAgo)->subHours(4),
            'end_time' => now()->subDays($daysAgo),
            'status' => $status,
        ]);
    }

    public function test_purges_old_cancelled_bookings(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, Booking::STATUS_CANCELLED, 100);

        (new PurgeExpiredBookingsJob(90))->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_purges_old_completed_bookings(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, Booking::STATUS_COMPLETED, 100);

        (new PurgeExpiredBookingsJob(90))->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_purges_old_no_show_bookings(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, Booking::STATUS_NO_SHOW, 100);

        (new PurgeExpiredBookingsJob(90))->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_does_not_purge_recent_cancelled_bookings(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, Booking::STATUS_CANCELLED, 30);

        (new PurgeExpiredBookingsJob(90))->handle();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_does_not_purge_confirmed_bookings(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, Booking::STATUS_CONFIRMED, 100);

        (new PurgeExpiredBookingsJob(90))->handle();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_does_not_purge_active_bookings(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, Booking::STATUS_ACTIVE, 100);

        (new PurgeExpiredBookingsJob(90))->handle();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_custom_retention_days(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, Booking::STATUS_CANCELLED, 10);

        (new PurgeExpiredBookingsJob(5))->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }
}
