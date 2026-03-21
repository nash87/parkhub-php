<?php

use App\Jobs\AggregateSystemMetricsJob;
use App\Jobs\AutoReleaseBookingsJob;
use App\Jobs\ExpandRecurringBookingsJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoReleaseBookingsJob)->everyFiveMinutes();
Schedule::job(new AggregateSystemMetricsJob)->everyFiveMinutes();
Schedule::job(new ExpandRecurringBookingsJob)->dailyAt('01:00');
Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily();
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
