<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\ParkingSlot;
use App\Models\WaitlistEntry;
use App\Models\Notification;
use Illuminate\Console\Command;

class AutoReleaseBookings extends Command
{
    protected $signature   = 'bookings:auto-release';
    protected $description = 'Release overdue unchecked-in bookings based on admin settings';

    public function handle(): int
    {
        $minutes = (int) Setting::get('auto_release_minutes', 0);

        if ($minutes <= 0) {
            $this->line('Auto-release disabled (auto_release_minutes = 0).');
            return 0;
        }

        $cutoff = now()->subMinutes($minutes);

        $overdue = Booking::where('status', 'active')
            ->whereNull('checked_in_at')
            ->where('start_time', '<', $cutoff)
            ->get();

        if ($overdue->isEmpty()) {
            return 0;
        }

        foreach ($overdue as $booking) {
            $booking->update(['status' => 'cancelled']);

            // Free the slot
            ParkingSlot::where('id', $booking->slot_id)
                ->update(['status' => 'available']);

            // Notify first waitlist entry for this lot
            $waiter = WaitlistEntry::where('lot_id', $booking->lot_id)
                ->whereNull('notified_at')
                ->orderBy('created_at')
                ->first();

            if ($waiter) {
                Notification::create([
                    'user_id' => $waiter->user_id,
                    'type'    => 'waitlist',
                    'message' => "A spot opened up in {$booking->lot_name}. Book now before it's taken.",
                    'data'    => json_encode(['lot_id' => $booking->lot_id]),
                ]);
                $waiter->update(['notified_at' => now()]);
            }
        }

        $this->info("Released {$overdue->count()} overdue booking(s).");
        return 0;
    }
}
