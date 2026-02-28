<?php
namespace App\Jobs;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoReleaseBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        if (Setting::get('auto_release_enabled', 'false') !== 'true') {
            return;
        }

        $timeoutMinutes = (int) Setting::get('auto_release_timeout', 30);
        $cutoff = now()->subMinutes($timeoutMinutes);

        // Find bookings that started more than $timeoutMinutes ago, still active, but no check-in
        $staleBookings = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $cutoff)
            ->whereNull('check_in_at')
            ->get();

        foreach ($staleBookings as $booking) {
            $booking->update(['status' => 'cancelled', 'cancellation_reason' => 'auto_release']);
            Log::info("Auto-released booking {$booking->id} (no check-in after {$timeoutMinutes}min)");

            // Notify first waitlist entry
            $waitlist = WaitlistEntry::where('slot_id', $booking->slot_id)
                ->where('status', 'waiting')
                ->orderBy('created_at')
                ->first();
            if ($waitlist) {
                $waitlist->update(['status' => 'notified', 'notified_at' => now()]);
                // TODO: Send notification email/push to waitlist user
            }
        }

        Log::info("AutoReleaseBookingsJob: released {$staleBookings->count()} stale bookings");
    }
}
