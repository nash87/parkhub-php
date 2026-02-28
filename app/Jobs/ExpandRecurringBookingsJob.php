<?php
namespace App\Jobs;

use App\Models\RecurringBooking;
use App\Models\Booking;
use App\Models\ParkingSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpandRecurringBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Expand recurring bookings for the next N days ahead (default: 7).
     * Idempotent — skips days that already have a booking for the same slot+user.
     */
    public function __construct(private int $daysAhead = 7) {}

    public function handle(): void
    {
        $created = 0;
        $active = RecurringBooking::where('is_active', true)
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->with('user')
            ->get();

        foreach ($active as $recurring) {
            $daysOfWeek = $recurring->days_of_week; // e.g. [1, 2, 3, 4, 5] = Mon-Fri

            for ($i = 0; $i <= $this->daysAhead; $i++) {
                $date = now()->addDays($i);
                $dayOfWeek = (int) $date->format('N'); // 1=Mon…7=Sun

                if (!in_array($dayOfWeek, $daysOfWeek)) {
                    continue;
                }

                $dateStr = $date->toDateString();
                $startTime = $dateStr . ' ' . $recurring->start_time;
                $endTime   = $dateStr . ' ' . $recurring->end_time;

                // Idempotency: skip if booking already exists for this user+slot+day
                $exists = Booking::where('user_id', $recurring->user_id)
                    ->where('slot_id', $recurring->slot_id)
                    ->whereDate('start_time', $dateStr)
                    ->whereNotIn('status', ['cancelled'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Check slot is still available
                $conflict = Booking::where('slot_id', $recurring->slot_id)
                    ->whereNotIn('status', ['cancelled'])
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->exists();

                if ($conflict) {
                    Log::info("ExpandRecurring: slot {$recurring->slot_id} conflict on {$dateStr}, skipping");
                    continue;
                }

                $slot = ParkingSlot::find($recurring->slot_id);
                Booking::create([
                    'id'           => Str::uuid(),
                    'user_id'      => $recurring->user_id,
                    'lot_id'       => $slot?->lot_id,
                    'slot_id'      => $recurring->slot_id,
                    'slot_number'  => $slot?->slot_number ?? '?',
                    'lot_name'     => optional($slot?->lot)->name ?? 'Unknown',
                    'start_time'   => $startTime,
                    'end_time'     => $endTime,
                    'status'       => 'confirmed',
                    'booking_type' => 'recurring',
                    'vehicle_plate'=> $recurring->vehicle_plate ?? null,
                ]);
                $created++;
            }
        }

        Log::info("ExpandRecurringBookingsJob: created {$created} bookings for next {$this->daysAhead} days");
    }
}
