<?php

namespace Tests\Simulation;

use App\Models\Booking;
use App\Models\RecurringBooking;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Tests\TestCase;

class ApiInjector
{
    private TestCase $test;

    private DataGenerator $generator;

    private SimulationProfile $profile;

    private array $stats = [
        'bookings_created' => 0,
        'bookings_cancelled' => 0,
        'bookings_conflicted' => 0,
        'recurring_created' => 0,
        'waitlist_created' => 0,
        'errors' => 0,
    ];

    public function __construct(TestCase $test, DataGenerator $generator, SimulationProfile $profile)
    {
        $this->test = $test;
        $this->generator = $generator;
        $this->profile = $profile;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Run the full simulation over $days simulated days.
     */
    public function simulate(): void
    {
        $baseDate = now()->addDay();

        for ($day = 0; $day < $this->profile->days; $day++) {
            $currentDay = $baseDate->copy()->addDays($day);
            $this->simulateDay($currentDay, $day);
        }
    }

    private function simulateDay(Carbon $day, int $dayIndex): void
    {
        $dayOfWeek = (int) $day->format('N');
        $isWeekend = $dayOfWeek >= 6;

        // Weekend gets ~30% of normal traffic
        $dailyBookings = $isWeekend
            ? (int) ceil($this->profile->bookingsPerDay * 0.3)
            : $this->profile->bookingsPerDay;

        for ($i = 0; $i < $dailyBookings; $i++) {
            $roll = rand(1, 100) / 100.0;

            if ($roll < $this->profile->recurringRate) {
                $this->createRecurringBooking($day);
            } elseif ($roll < $this->profile->recurringRate + $this->profile->waitlistRate) {
                $this->createWaitlistEntry($day);
            } elseif ($roll < $this->profile->recurringRate + $this->profile->waitlistRate + $this->profile->conflictRate) {
                $this->attemptConflictBooking($day);
            } else {
                $this->createSingleBooking($day);
            }
        }

        // Apply cancellations for this day's bookings
        $this->applyCancellations($day);
    }

    private function createSingleBooking(Carbon $day): void
    {
        $user = $this->generator->getRandomUser();
        $lot = $this->generator->getRandomLot();
        $slot = $this->generator->getRandomSlot($lot->id);
        [$start, $end] = $this->generator->generateBookingTimes($day);

        // Use direct model creation for speed (in-process, no HTTP overhead)
        try {
            // Check for conflicts before inserting
            $conflict = Booking::where('slot_id', $slot->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($start, $end) {
                    $q->where(function ($inner) use ($start, $end) {
                        $inner->where('start_time', '<', $end)
                            ->where('end_time', '>', $start);
                    });
                })
                ->exists();

            if ($conflict) {
                $this->stats['bookings_conflicted']++;

                return;
            }

            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'lot_name' => $lot->name,
                'slot_number' => $slot->slot_number,
                'start_time' => $start,
                'end_time' => $end,
                'booking_type' => 'single',
                'status' => 'confirmed',
                'vehicle_plate' => $this->generator->generatePlate(),
            ]);

            $this->stats['bookings_created']++;
        } catch (\Throwable $e) {
            $this->stats['errors']++;
        }
    }

    private function createRecurringBooking(Carbon $day): void
    {
        $user = $this->generator->getRandomUser();
        $lot = $this->generator->getRandomLot();
        $slot = $this->generator->getRandomSlot($lot->id);

        $daysOfWeek = array_slice([1, 2, 3, 4, 5], 0, rand(1, 5));

        try {
            RecurringBooking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => $daysOfWeek,
                'start_date' => $day->format('Y-m-d'),
                'end_date' => $day->copy()->addDays(30)->format('Y-m-d'),
                'start_time' => sprintf('%02d:00', rand(7, 9)),
                'end_time' => sprintf('%02d:00', rand(16, 18)),
                'vehicle_plate' => $this->generator->generatePlate(),
                'active' => true,
            ]);

            $this->stats['recurring_created']++;
        } catch (\Throwable $e) {
            $this->stats['errors']++;
        }
    }

    private function createWaitlistEntry(Carbon $day): void
    {
        $user = $this->generator->getRandomUser();
        $lot = $this->generator->getRandomLot();

        try {
            WaitlistEntry::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'status' => 'waiting',
                'priority' => rand(1, 10),
            ]);

            $this->stats['waitlist_created']++;
        } catch (\Throwable $e) {
            $this->stats['errors']++;
        }
    }

    private function attemptConflictBooking(Carbon $day): void
    {
        // Find an existing booking and try to book the same slot/time
        $existingBooking = Booking::where('status', 'confirmed')
            ->whereDate('start_time', '>=', $day)
            ->first();

        if (! $existingBooking) {
            $this->createSingleBooking($day);

            return;
        }

        $user = $this->generator->getRandomUser();

        $conflict = Booking::where('slot_id', $existingBooking->slot_id)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $existingBooking->end_time)
            ->where('end_time', '>', $existingBooking->start_time)
            ->exists();

        if ($conflict) {
            $this->stats['bookings_conflicted']++;
        } else {
            // If somehow no conflict, create a normal booking instead
            $this->createSingleBooking($day);
        }
    }

    private function applyCancellations(Carbon $day): void
    {
        $dayBookings = Booking::where('status', 'confirmed')
            ->whereDate('start_time', $day)
            ->get();

        $cancelCount = (int) ceil($dayBookings->count() * $this->profile->cancellationRate);

        foreach ($dayBookings->random(min($cancelCount, $dayBookings->count())) as $booking) {
            $booking->update(['status' => 'cancelled']);
            $this->stats['bookings_cancelled']++;
        }
    }
}
