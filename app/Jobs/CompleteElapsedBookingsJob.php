<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Move bookings that have already ended into their terminal state.
 *
 * A booking is created `confirmed` and may become `active` on check-in,
 * but nothing in the codebase ever wrote `Booking::STATUS_COMPLETED`.
 * Elapsed bookings therefore stayed `confirmed` forever, which broke
 * every consumer that reads completed bookings — parking history and its
 * statistics, the recommendation engine, parking-pass invalidation — and
 * permanently consumed the per-user `max_active_bookings` allowance so
 * users were locked out of booking once they hit the cap.
 *
 * This is distinct from {@see AutoReleaseBookingsJob}, which *cancels*
 * bookings whose owner never checked in and is gated behind the optional
 * `auto_release_enabled` setting. Completion is a lifecycle invariant and
 * is deliberately not gated behind any setting.
 *
 * Bookings that already reached a terminal state (`cancelled`, `no_show`,
 * `completed`) are left alone — completion must never resurrect or
 * relabel a booking that was explicitly resolved some other way.
 */
class CompleteElapsedBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Rows updated per batch. Bounds both memory and how long a single
     * UPDATE holds row locks on large installs.
     */
    private const BATCH_SIZE = 500;

    public function handle(): void
    {
        $now = now();
        $total = 0;

        while (true) {
            $ids = Booking::query()
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('end_time', '<', $now)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $total += Booking::query()
                ->whereIn('id', $ids)
                ->update(['status' => Booking::STATUS_COMPLETED]);

            // A short batch means we drained the backlog; avoid one extra
            // round-trip just to discover an empty page.
            if ($ids->count() < self::BATCH_SIZE) {
                break;
            }
        }

        if ($total > 0) {
            Log::info("CompleteElapsedBookingsJob: completed {$total} elapsed bookings");
        }
    }
}
