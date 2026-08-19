<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AutoReleaseBookingsJob;
use App\Jobs\ExpandRecurringBookingsJob;
use App\Jobs\PurgeExpiredBookingsJob;
use App\Jobs\SendBookingReminderJob;
use App\Models\Booking;
use App\Models\CreditTransaction;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\RecurringBooking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The booking lifecycle is driven by scheduled jobs. Several of them were
 * either never invoked, or invoked with a predicate that rewrote data they
 * had no business touching.
 */
class BookingLifecycleJobsTest extends TestCase
{
    use RefreshDatabase;

    private ParkingLot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lot = ParkingLot::create([
            'name' => 'Job Lot', 'total_slots' => 10, 'available_slots' => 10, 'status' => 'open',
        ]);
    }

    private function slot(string $n): ParkingSlot
    {
        return ParkingSlot::create(['lot_id' => $this->lot->id, 'slot_number' => $n, 'status' => 'available']);
    }

    private function booking(User $user, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $user->id,
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot('S'.random_int(1000, 9999))->id,
            'lot_name' => $this->lot->name,
            'slot_number' => 'S1',
            'start_time' => now()->subHours(4),
            'end_time' => now()->subHours(2),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'standard',
        ], $overrides));
    }

    // ── AutoReleaseBookingsJob ────────────────────────────────────────────

    /**
     * Auto-release exists to free a slot the holder failed to claim *while
     * it was still theirs*. Its predicate had no `end_time` bound, so every
     * booking that simply ran to completion without a check-in — and the
     * entire historical backlog the first time an operator enables the
     * feature — was rewritten to `cancelled`. Fulfilled parking sessions
     * were then permanently recorded as cancellations.
     */
    public function test_auto_release_does_not_cancel_bookings_that_have_already_ended(): void
    {
        Setting::set('auto_release_enabled', 'true');
        Setting::set('auto_release_timeout', '30');

        $user = User::factory()->create(['role' => 'user']);
        $finished = $this->booking($user, [
            'start_time' => now()->subHours(5),
            'end_time' => now()->subHours(3),
        ]);

        (new AutoReleaseBookingsJob)->handle();

        $this->assertSame(
            Booking::STATUS_CONFIRMED,
            $finished->fresh()->status,
            'a booking that had already ended was auto-released as cancelled',
        );
    }

    public function test_auto_release_still_cancels_a_booking_inside_its_window(): void
    {
        Setting::set('auto_release_enabled', 'true');
        Setting::set('auto_release_timeout', '30');

        $user = User::factory()->create(['role' => 'user']);
        $running = $this->booking($user, [
            'start_time' => now()->subHours(2),
            'end_time' => now()->addHours(2),
        ]);

        (new AutoReleaseBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_CANCELLED, $running->fresh()->status);
    }

    /**
     * A cancellation the user did not ask for must return the credit. The
     * user-initiated path refunds; this one did not, so a user whose slot
     * was auto-released simply lost a credit.
     */
    public function test_auto_release_refunds_the_credit_it_takes_back(): void
    {
        Setting::set('auto_release_enabled', 'true');
        Setting::set('auto_release_timeout', '30');
        Setting::set('credits_enabled', 'true');
        Setting::set('credits_per_booking', '1');

        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 4]);
        $booking = $this->booking($user, [
            'start_time' => now()->subHours(2),
            'end_time' => now()->addHours(2),
        ]);
        CreditTransaction::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'amount' => -1,
            'type' => 'deduction',
            'description' => 'Booking',
        ]);

        (new AutoReleaseBookingsJob)->handle();

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(5, $user->fresh()->credits_balance, 'auto-release kept the user\'s credit');
    }

    // ── ExpandRecurringBookingsJob ────────────────────────────────────────

    private function recurring(User $user, ParkingSlot $slot, ?string $endDate): RecurringBooking
    {
        return RecurringBooking::create([
            'user_id' => $user->id,
            'lot_id' => $this->lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'start_time' => '09:00',
            'end_time' => '17:00',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => $endDate,
            'active' => true,
        ]);
    }

    /** A series that ends today must not produce bookings for next week. */
    public function test_recurring_expansion_stops_at_the_series_end_date(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $slot = $this->slot('R1');
        $this->recurring($user, $slot, now()->toDateString());

        (new ExpandRecurringBookingsJob)->handle();

        $beyond = Booking::where('user_id', $user->id)
            ->where('start_time', '>', now()->endOfDay())
            ->count();

        $this->assertSame(0, $beyond, 'bookings were created past the end of the series');
    }

    /** The constructor promises N days ahead; it expanded N + 1. */
    public function test_recurring_expansion_covers_exactly_the_requested_horizon(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $slot = $this->slot('R2');
        $this->recurring($user, $slot, null);

        (new ExpandRecurringBookingsJob(3))->handle();

        $this->assertSame(
            3,
            Booking::where('user_id', $user->id)->count(),
            'the expansion horizon is off by one',
        );
    }

    // ── SendBookingReminderJob ────────────────────────────────────────────

    /**
     * The job is queued, so it retries; and once scheduled it runs on a
     * cadence shorter than its own look-ahead window. Without a record of
     * what it already sent, the same booking is reminded repeatedly.
     */
    public function test_booking_reminders_are_sent_at_most_once(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => 'user']);
        $this->booking($user, [
            'start_time' => now()->addMinutes(30),
            'end_time' => now()->addHours(2),
        ]);

        (new SendBookingReminderJob)->handle();
        (new SendBookingReminderJob)->handle();
        (new SendBookingReminderJob)->handle();

        Mail::assertSentCount(1);
    }

    // ── Scheduling: a job nobody invokes is not a feature ─────────────────

    /**
     * @return list<string>
     */
    private function scheduledDescriptions(): array
    {
        return collect($this->app->make(Schedule::class)->events())
            ->map(fn ($e) => (string) $e->description.' '.(string) $e->getSummaryForDisplay())
            ->all();
    }

    public function test_booking_reminders_are_actually_scheduled(): void
    {
        $this->assertTrue(
            collect($this->scheduledDescriptions())->contains(fn ($d) => str_contains($d, SendBookingReminderJob::class)),
            'SendBookingReminderJob is not scheduled, so reminders are never sent.',
        );
    }

    public function test_expired_booking_purge_is_actually_scheduled(): void
    {
        $this->assertTrue(
            collect($this->scheduledDescriptions())->contains(fn ($d) => str_contains($d, PurgeExpiredBookingsJob::class)),
            'PurgeExpiredBookingsJob is not scheduled, so the documented retention window is never applied.',
        );
    }
}
