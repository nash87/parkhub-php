<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\NoShow\NoShowReleaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs every 5 minutes to:
 * 1. Release past-deadline un-checked-in bookings (per-lot config).
 * 2. Expire stale pending offers and promote the next FIFO waitlist entry.
 */
class NoShowReleaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NoShowReleaseService $service): void
    {
        $released = $service->releaseNoShows();
        $service->expireStaleOffers();

        Log::info('NoShowReleaseJob: complete', ['released' => $released]);
    }
}
