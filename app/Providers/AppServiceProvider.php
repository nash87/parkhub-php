<?php

namespace App\Providers;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Listeners\PushSseBookingEvent;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Policies\AbsencePolicy;
use App\Policies\BookingPolicy;
use App\Policies\ParkingLotPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable the default {data: ...} wrapping on JsonResource — the ApiResponseWrapper
        // middleware already wraps all API responses in {success, data, error, meta}.
        JsonResource::withoutWrapping();

        // SSE real-time event listeners — push booking events to cache queue
        Event::listen(BookingCreated::class, [PushSseBookingEvent::class, 'handleCreated']);
        Event::listen(BookingCancelled::class, [PushSseBookingEvent::class, 'handleCancelled']);

        // Register model policies
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(ParkingLot::class, ParkingLotPolicy::class);
        Gate::policy(Absence::class, AbsencePolicy::class);

        $this->configureRateLimiting();
    }

    /**
     * Define named rate limiters for sensitive endpoints.
     *
     * When config('app.disable_rate_limits') is true (E2E test runs only,
     * never production) every limiter is raised to 100_000 per minute so
     * a full Playwright suite — which funnels every test through the
     * loginViaUi helper from the same localhost IP — doesn't cascade into
     * 429s after the first 5 tests.
     */
    private function configureRateLimiting(): void
    {
        $disabled = (bool) config('app.disable_rate_limits', false);
        $limit = static fn (int $normal): int => $disabled ? 100_000 : $normal;

        // Auth endpoints: 5 attempts per minute per IP (brute-force protection)
        RateLimiter::for('auth', function (Request $request) use ($limit) {
            return Limit::perMinute($limit(5))->by($request->ip());
        });

        // Password reset: 3 attempts per 15 minutes per IP
        RateLimiter::for('password-reset', function (Request $request) use ($disabled) {
            return $disabled
                ? Limit::perMinute(100_000)->by($request->ip())
                : Limit::perMinutes(15, 3)->by($request->ip());
        });

        // Demo reset: 2 per minute per IP (heavy DB operation)
        RateLimiter::for('demo-action', function (Request $request) use ($limit) {
            return Limit::perMinute($limit(2))->by($request->ip());
        });

        // Setup mutations: 3 per minute per IP
        RateLimiter::for('setup', function (Request $request) use ($limit) {
            return Limit::perMinute($limit(3))->by($request->ip());
        });

        // Payment endpoints: 10 per minute per user (prevent abuse)
        RateLimiter::for('payments', function (Request $request) use ($limit) {
            return Limit::perMinute($limit(10))->by($request->user()?->id ?: $request->ip());
        });

        // Lobby display: 10 per minute per IP (public kiosk polling)
        RateLimiter::for('lobby-display', function (Request $request) use ($limit) {
            return Limit::perMinute($limit(10))->by($request->ip());
        });

        // Authenticated API: 120 per minute per user (or IP if unauthenticated)
        RateLimiter::for('api', function (Request $request) use ($limit) {
            return Limit::perMinute($limit(120))->by($request->user()?->id ?: $request->ip());
        });
    }
}
