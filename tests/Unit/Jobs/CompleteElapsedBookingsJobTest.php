<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\CompleteElapsedBookingsJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A booking that has ended must reach a terminal state.
 *
 * Before this job existed nothing in the codebase ever wrote
 * `Booking::STATUS_COMPLETED`, so elapsed bookings stayed `confirmed`
 * forever. That starved every consumer of completed bookings (parking
 * history, recommendations, parking-pass invalidation) and permanently
 * consumed the per-user `max_active_bookings` allowance.
 */
class CompleteElapsedBookingsJobTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{ParkingLot, ParkingSlot, User} */
    private function fixture(): array
    {
        $lot = ParkingLot::create(['name' => 'Complete Lot', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'C1', 'status' => 'available']);
        $user = User::factory()->create();

        return [$lot, $slot, $user];
    }

    private function booking(array $overrides = []): Booking
    {
        [$lot, $slot, $user] = $this->fixture();

        return Booking::create(array_merge([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHours(4),
            'end_time' => now()->subHours(2),
            'status' => Booking::STATUS_CONFIRMED,
        ], $overrides));
    }

    public function test_marks_elapsed_confirmed_booking_completed(): void
    {
        $booking = $this->booking(['status' => Booking::STATUS_CONFIRMED]);

        (new CompleteElapsedBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_COMPLETED, $booking->fresh()->status);
    }

    public function test_marks_elapsed_active_booking_completed(): void
    {
        $booking = $this->booking(['status' => Booking::STATUS_ACTIVE]);

        (new CompleteElapsedBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_COMPLETED, $booking->fresh()->status);
    }

    public function test_leaves_ongoing_booking_untouched(): void
    {
        $booking = $this->booking([
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        (new CompleteElapsedBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_leaves_future_booking_untouched(): void
    {
        $booking = $this->booking([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ]);

        (new CompleteElapsedBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_does_not_resurrect_cancelled_bookings(): void
    {
        $booking = $this->booking(['status' => Booking::STATUS_CANCELLED]);

        (new CompleteElapsedBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_does_not_overwrite_no_show(): void
    {
        $booking = $this->booking(['status' => Booking::STATUS_NO_SHOW]);

        (new CompleteElapsedBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_NO_SHOW, $booking->fresh()->status);
    }

    /**
     * Completion is a lifecycle invariant, not an opt-in feature. It must
     * not inherit `AutoReleaseBookingsJob`'s `auto_release_enabled` gate —
     * that gate is why elapsed bookings were never reaped on default
     * installs in the first place.
     */
    public function test_runs_even_when_auto_release_is_disabled(): void
    {
        Setting::set('auto_release_enabled', 'false');
        $booking = $this->booking();

        (new CompleteElapsedBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_COMPLETED, $booking->fresh()->status);
    }
}
