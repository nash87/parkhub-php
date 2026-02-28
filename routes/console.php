<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:auto-release')->everyFiveMinutes();

Schedule::job(new \App\Jobs\AutoReleaseBookingsJob())->everyFiveMinutes();
Schedule::job(new \App\Jobs\ExpandRecurringBookingsJob())->dailyAt('01:00');
Schedule::command('sanctum:prune-expired', ['--hours' => 168])->daily();
