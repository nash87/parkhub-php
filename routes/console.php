<?php

use App\Jobs\AggregateSystemMetricsJob;
use App\Jobs\AutoReleaseBookingsJob;
use App\Jobs\ExpandRecurringBookingsJob;
use App\Jobs\PurgeExpiredBookingsJob;
use App\Jobs\SendBookingReminderJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoReleaseBookingsJob)->everyFiveMinutes();
Schedule::job(new AggregateSystemMetricsJob)->everyFiveMinutes();
Schedule::job(new ExpandRecurringBookingsJob)->dailyAt('01:00');

// Reminders are the only path that tells a user their booking is coming up.
// The job was written, unit-tested, and never invoked from anywhere, so no
// reminder has ever been sent. It is safe on a short cadence because it
// records what it already sent (`bookings.reminder_sent_at`).
Schedule::job(new SendBookingReminderJob)->everyFifteenMinutes()->withoutOverlapping();

// The job's own docblock documents a 90-day retention window for
// cancelled/completed/no-show bookings. Without a schedule that stated
// retention control was never applied and bookings accumulated forever.
Schedule::job(new PurgeExpiredBookingsJob)->dailyAt('03:30')->withoutOverlapping();
Schedule::command('sanctum:prune-expired', ['--hours' => 168])->daily();
Schedule::command('credits:refill-monthly')->monthlyOn(1, '00:00');

// Demo auto-reset every 6 hours (only when DEMO_MODE=true)
if (env('DEMO_MODE') === 'true' || env('DEMO_MODE') === '1') {
    Schedule::call(function () {
        $prefix = 'demo_';
        Cache::put($prefix.'reset_in_progress', true, 300);
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', [
            '--class' => 'ProductionSimulationSeeder',
            '--force' => true,
        ]);
        $now = now()->timestamp;
        Cache::put($prefix.'last_reset_at', $now, 86400);
        Cache::put($prefix.'next_scheduled_reset', $now + 21600, 86400);
        Cache::forget($prefix.'reset_in_progress');
        Cache::forget($prefix.'votes');
        Cache::forget($prefix.'started_at');
        Log::info('Demo auto-reset completed');
    })->cron('0 */6 * * *')->name('demo-auto-reset');
}
