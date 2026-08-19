<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BookingReminderMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * Send reminders for bookings starting within $minutesBefore minutes.
     */
    public function __construct(
        private int $minutesBefore = 60,
    ) {}

    public function handle(): void
    {
        $windowStart = now();
        $windowEnd = now()->addMinutes($this->minutesBefore);

        // Only bookings that have not already been reminded. This job is
        // queued (so it retries) and scheduled on a cadence shorter than
        // its own look-ahead window, so without this filter the same
        // booking is mailed on every pass.
        $bookings = Booking::whereIn('status', ['confirmed'])
            ->whereBetween('start_time', [$windowStart, $windowEnd])
            ->whereNull('checked_in_at')
            ->whereNull('reminder_sent_at')
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            $user = $booking->user;
            if (! $user || ! $user->email) {
                continue;
            }

            Mail::to($user->email)->send(new BookingReminderMail($booking, $user));
            // Stamp after the send so a delivery failure is retried rather
            // than silently marked as reminded.
            $booking->forceFill(['reminder_sent_at' => now()])->save();
            $sent++;
        }

        Log::info("SendBookingReminderJob: sent {$sent} reminders for bookings starting within {$this->minutesBefore}min");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendBookingReminderJob: permanently failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
