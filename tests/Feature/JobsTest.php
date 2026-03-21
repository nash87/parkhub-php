<?php

namespace Tests\Feature;

use App\Jobs\AutoReleaseBookingsJob;
use App\Jobs\PurgeExpiredBookingsJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobsTest extends TestCase
{
    use RefreshDatabase;

    private function createLotAndSlot(): array
    {
        $lot = ParkingLot::create([
            'name' => 'Job Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'J1',
            'status' => 'available',
        ]);

        return [$lot, $slot];
    }

    public function test_purge_expired_bookings_job_removes_old_cancelled_bookings(): void
    {
        $user = User::factory()->create();
        [$lot, $slot] = $this->createLotAndSlot();

        // Create an old cancelled booking
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subDays(100),
            'end_time' => now()->subDays(100)->addHours(2),
            'booking_type' => 'single',
            'status' => 'cancelled',
        ]);

        $job = new PurgeExpiredBookingsJob(90);
        $job->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_purge_expired_bookings_job_keeps_recent_bookings(): void
    {
        $user = User::factory()->create();
        [$lot, $slot] = $this->createLotAndSlot();

        // Create a recent cancelled booking (within retention period)
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subDays(30),
            'end_time' => now()->subDays(30)->addHours(2),
            'booking_type' => 'single',
            'status' => 'cancelled',
        ]);

        $job = new PurgeExpiredBookingsJob(90);
        $job->handle();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_purge_expired_bookings_job_removes_old_completed_bookings(): void
    {
        $user = User::factory()->create();
        [$lot, $slot] = $this->createLotAndSlot();

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subDays(95),
            'end_time' => now()->subDays(95)->addHours(2),
            'booking_type' => 'single',
            'status' => 'completed',
        ]);

        $job = new PurgeExpiredBookingsJob(90);
        $job->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_purge_expired_bookings_job_keeps_active_bookings(): void
    {
        $user = User::factory()->create();
        [$lot, $slot] = $this->createLotAndSlot();

        // Active booking (not cancelled/completed) — should be kept regardless of age
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subDays(100),
            'end_time' => now()->subDays(99),
            'booking_type' => 'single',
            'status' => 'active',
        ]);

        $job = new PurgeExpiredBookingsJob(90);
        $job->handle();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_auto_release_job_does_nothing_when_disabled(): void
    {
        Setting::set('auto_release_enabled', 'false');
        $user = User::factory()->create();
        [$lot, $slot] = $this->createLotAndSlot();

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->addHour(),
            'booking_type' => 'single',
            'status' => 'confirmed',
            'checked_in_at' => null,
        ]);

        $job = new AutoReleaseBookingsJob();
        $job->handle();

        // Booking should still be confirmed
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_auto_release_job_cancels_stale_bookings_when_enabled(): void
    {
        Setting::set('auto_release_enabled', 'true');
        Setting::set('auto_release_timeout', '30');

        $user = User::factory()->create();
        [$lot, $slot] = $this->createLotAndSlot();

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subMinutes(60),
            'end_time' => now()->addHours(2),
            'booking_type' => 'single',
            'status' => 'confirmed',
            'checked_in_at' => null,
        ]);

        $job = new AutoReleaseBookingsJob();
        $job->handle();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }
}
