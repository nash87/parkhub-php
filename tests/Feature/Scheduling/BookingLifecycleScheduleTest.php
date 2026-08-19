<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Jobs\CompleteElapsedBookingsJob;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * A job that is written, tested and never invoked is still a broken
 * feature. `CompleteElapsedBookingsJob` only fixes #586 if the scheduler
 * actually runs it, so the registration itself is under test.
 */
class BookingLifecycleScheduleTest extends TestCase
{
    public function test_complete_elapsed_bookings_job_is_scheduled(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $descriptions = collect($schedule->events())
            ->map(fn ($event) => $event instanceof CallbackEvent
                ? (string) $event->getSummaryForDisplay()
                : (string) $event->description.' '.(string) $event->getSummaryForDisplay())
            ->implode("\n");

        $this->assertStringContainsString(
            CompleteElapsedBookingsJob::class,
            $descriptions,
            'CompleteElapsedBookingsJob is not registered with the scheduler; elapsed bookings would never be completed.',
        );
    }

    public function test_complete_elapsed_bookings_job_runs_at_least_hourly(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())->first(
            fn ($e) => str_contains((string) $e->description.' '.(string) $e->getSummaryForDisplay(), CompleteElapsedBookingsJob::class)
        );

        $this->assertNotNull($event, 'CompleteElapsedBookingsJob is not scheduled.');

        // Anything less frequent than hourly leaves users sitting against
        // the max_active_bookings cap for an unacceptably long time.
        $this->assertContains(
            $event->expression,
            ['* * * * *', '*/5 * * * *', '*/10 * * * *', '*/15 * * * *', '*/30 * * * *', '0 * * * *'],
            "Unexpected cron expression [{$event->expression}] for CompleteElapsedBookingsJob.",
        );
    }
}
