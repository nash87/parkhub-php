<?php

use App\Jobs\AggregateSystemMetricsJob;
use App\Jobs\AutoReleaseBookingsJob;
use App\Jobs\ExpandRecurringBookingsJob;
use App\Jobs\NoShowReleaseJob;
use App\Services\Retention\RetentionEngine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoReleaseBookingsJob)->everyFiveMinutes();
Schedule::job(new AggregateSystemMetricsJob)->everyFiveMinutes();

// No-show auto-release + waitlist FIFO auto-promotion (P1-1/P1-2).
// Only when the module is enabled. ~5 min cadence matches the check-in deadline
// granularity; withoutOverlapping prevents pile-up under DB pressure.
if (module_enabled('noshow_waitlist')) {
    Schedule::job(new NoShowReleaseJob)
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->onOneServer();
}
Schedule::job(new ExpandRecurringBookingsJob)->dailyAt('01:00');
Schedule::command('sanctum:prune-expired', ['--hours' => 168])->daily();
Schedule::command('credits:refill-monthly')->monthlyOn(1, '00:00');

// GDPR retention purge — only when the retention module is enabled.
// Daily 03:30 UTC: after sanctum-prune (03:00) and audit-log prune (03:15).
if (module_enabled('retention')) {
    Schedule::call(function () {
        try {
            $results = app(RetentionEngine::class)->purge(dryRun: false);
            $deleted = array_sum(array_column($results, 'record_count'));
            Log::info('retention:purge complete', ['total_deleted' => $deleted]);
        } catch (Throwable $e) {
            Log::error('retention:purge failed', ['error' => $e->getMessage()]);
        }
    })
        ->name('retention-purge')
        ->dailyAt('03:30')
        ->timezone('UTC')
        ->onOneServer()
        ->withoutOverlapping();
}

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
