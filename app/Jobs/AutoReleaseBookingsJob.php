<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WaitlistSlotAvailableMail;
use App\Models\Booking;
use App\Models\CreditTransaction;
use App\Models\ParkingLot;
use App\Models\Setting;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        // Bookings still inside their own window whose holder never checked
        // in. The `end_time` bound matters: without it this predicate also
        // matches every booking that simply ran to completion without a
        // check-in — and, the first time an operator enables auto-release,
        // the entire historical backlog. Those would be rewritten to
        // `cancelled`, permanently recording fulfilled parking sessions as
        // cancellations and skewing every status-keyed report.
        $staleBookings = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $cutoff)
            ->where('end_time', '>', now())
            ->whereNull('checked_in_at')
            ->get();

        foreach ($staleBookings as $booking) {
            $booking->status = 'cancelled';
            $booking->save();
            $this->refundAutoReleasedBooking($booking);
            Log::info("Auto-released booking {$booking->id} (no check-in after {$timeoutMinutes}min)");

            // Notify first waitlist entry
            $waitlist = WaitlistEntry::where('lot_id', $booking->lot_id)
                ->whereNotNull('user_id')
                ->whereNull('notified_at')
                ->orderBy('created_at')
                ->first();
            if ($waitlist) {
                $waitlist->update(['notified_at' => now()]);
                $user = $waitlist->user;
                $lot = $booking->lot ?? ParkingLot::find($booking->lot_id);
                if ($user && $lot) {
                    Mail::to($user->email)->queue(new WaitlistSlotAvailableMail($user, $lot));
                }
            }
        }

        Log::info("AutoReleaseBookingsJob: released {$staleBookings->count()} stale bookings");
    }

    /**
     * Return the credit a booking cost when the system, not the user,
     * cancels it.
     *
     * The user-initiated cancellation path refunds; this one did not, so a
     * user who parked but forgot to check in — or whose booking the system
     * reclaimed — silently lost a credit. A refund still requires proof the
     * booking was paid for, and is written at most once.
     */
    private function refundAutoReleasedBooking(Booking $booking): void
    {
        if (Setting::get('credits_enabled', 'false') !== 'true') {
            return;
        }

        // Look the owner up rather than using the relation: `User` soft-
        // deletes, so the owner of an old booking genuinely may not resolve,
        // and `find()` is honest about that where the relation's PHPDoc is
        // not.
        $user = User::find($booking->user_id);
        if (! $user || $user->isAdmin()) {
            return;
        }

        $paid = CreditTransaction::where('booking_id', $booking->id)
            ->where('type', 'deduction')
            ->exists();

        $alreadyRefunded = CreditTransaction::where('booking_id', $booking->id)
            ->where('type', 'refund')
            ->exists();

        if (! $paid || $alreadyRefunded) {
            return;
        }

        $amount = (int) Setting::get('credits_per_booking', '1');

        CreditTransaction::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'amount' => $amount,
            'type' => 'refund',
            'description' => 'Auto-released booking #'.substr($booking->id, 0, 8),
        ]);

        $user->increment('credits_balance', $amount);
    }
}
