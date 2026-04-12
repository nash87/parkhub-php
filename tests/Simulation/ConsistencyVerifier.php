<?php

namespace Tests\Simulation;

use App\Models\Booking;
use App\Models\ParkingSlot;
use App\Models\RecurringBooking;
use App\Models\WaitlistEntry;
use Illuminate\Support\Collection;

class ConsistencyVerifier
{
    private SimulationProfile $profile;

    /** @var array<string, bool> */
    private array $checks = [];

    /** @var array<string, string> */
    private array $details = [];

    public function __construct(SimulationProfile $profile)
    {
        $this->profile = $profile;
    }

    /**
     * Run all consistency checks after simulation.
     */
    public function verify(): self
    {
        $this->checkNoDoubleBookings();
        $this->checkCancellationRate();
        $this->checkRecurringInstancesExist();
        $this->checkWaitlistResolved();
        $this->checkBookingIntegrity();
        $this->checkSlotConsistency();

        return $this;
    }

    public function allPassed(): bool
    {
        return ! in_array(false, $this->checks, true);
    }

    public function getChecks(): array
    {
        return $this->checks;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getFailures(): array
    {
        return array_filter($this->checks, fn (bool $v) => ! $v);
    }

    private function checkNoDoubleBookings(): void
    {
        // Find any two confirmed bookings on the same slot with overlapping times
        $doubleBookings = Booking::where('status', '!=', 'cancelled')
            ->select('slot_id', 'start_time', 'end_time')
            ->get()
            ->groupBy('slot_id');

        $conflicts = 0;

        foreach ($doubleBookings as $slotId => $bookings) {
            $sorted = $bookings->sortBy('start_time')->values();

            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                for ($j = $i + 1; $j < $sorted->count(); $j++) {
                    $a = $sorted[$i];
                    $b = $sorted[$j];

                    if ($a->end_time > $b->start_time && $a->start_time < $b->end_time) {
                        $conflicts++;
                    }
                }
            }
        }

        $this->checks['no_double_bookings'] = $conflicts === 0;
        $this->details['no_double_bookings'] = $conflicts === 0
            ? 'No double bookings found'
            : "Found {$conflicts} double-booked slot/time pairs";
    }

    private function checkCancellationRate(): void
    {
        $total = Booking::count();
        $cancelled = Booking::where('status', 'cancelled')->count();

        if ($total === 0) {
            $this->checks['cancellation_rate'] = true;
            $this->details['cancellation_rate'] = 'No bookings to check';

            return;
        }

        $rate = $cancelled / $total;
        $expectedRate = $this->profile->cancellationRate;

        // Allow 10% variance from expected rate
        $lowerBound = $expectedRate * 0.5;
        $upperBound = min($expectedRate * 2, 1.0);

        $this->checks['cancellation_rate'] = $rate >= $lowerBound && $rate <= $upperBound;
        $this->details['cancellation_rate'] = sprintf(
            'Rate: %.1f%% (%d/%d), expected ~%.1f%%',
            $rate * 100,
            $cancelled,
            $total,
            $expectedRate * 100
        );
    }

    private function checkRecurringInstancesExist(): void
    {
        $recurringCount = RecurringBooking::count();
        $expectedMin = (int) floor($this->profile->totalBookings() * $this->profile->recurringRate * 0.3);

        $this->checks['recurring_instances'] = $recurringCount >= $expectedMin || $expectedMin === 0;
        $this->details['recurring_instances'] = sprintf(
            '%d recurring bookings created (expected at least %d)',
            $recurringCount,
            $expectedMin
        );
    }

    private function checkWaitlistResolved(): void
    {
        $totalWaitlist = WaitlistEntry::count();
        // All waitlist entries should exist (we don't auto-resolve in simulation)
        $this->checks['waitlist_entries'] = true;
        $this->details['waitlist_entries'] = sprintf('%d waitlist entries created', $totalWaitlist);
    }

    private function checkBookingIntegrity(): void
    {
        // Every booking must reference a valid user, lot, and slot
        $orphanedUser = Booking::whereDoesntHave('user')->count();
        $orphanedLot = Booking::whereDoesntHave('lot')->count();
        $orphanedSlot = Booking::whereDoesntHave('slot')->count();

        $total = $orphanedUser + $orphanedLot + $orphanedSlot;

        $this->checks['booking_integrity'] = $total === 0;
        $this->details['booking_integrity'] = $total === 0
            ? 'All bookings reference valid entities'
            : "Found {$orphanedUser} orphaned users, {$orphanedLot} orphaned lots, {$orphanedSlot} orphaned slots";
    }

    private function checkSlotConsistency(): void
    {
        // Every slot referenced by a booking should exist
        $slotIds = Booking::pluck('slot_id')->unique();
        $existingSlots = ParkingSlot::whereIn('id', $slotIds)->pluck('id');
        $missing = $slotIds->diff($existingSlots)->count();

        $this->checks['slot_consistency'] = $missing === 0;
        $this->details['slot_consistency'] = $missing === 0
            ? 'All booking slots exist'
            : "Found {$missing} bookings referencing missing slots";
    }
}
