<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Events\BookingCreated;
use App\Jobs\SendBookingConfirmationJob;
use App\Jobs\SendWebhookJob;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CreditTransaction;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Tax\TaxProfileRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns the booking-creation business logic extracted from
 * BookingController::store() (T-1742, pass 1).
 *
 * Pure extraction — error codes, status codes, audit rows, emitted
 * events and cached webhook dispatch are identical to the previous
 * inline controller implementation. Controllers remain responsible for
 * HTTP shaping via the returned BookingCreationResult.
 */
final class BookingCreationService
{
    /**
     * Attempt to create a booking.
     *
     * Validation rules are evaluated in the same order as the legacy
     * controller so error-code precedence is preserved.
     */
    public function create(array $input, User $user): BookingCreationResult
    {
        $startTime = Carbon::parse($input['start_time'] ?? '');
        if ($startTime->isPast()) {
            return BookingCreationResult::fail('INVALID_BOOKING_TIME', 'Booking start time must be in the future.', 422);
        }

        // Bulk-fetch all settings consumed during booking creation in one query.
        Setting::preload([
            'max_bookings_per_day',
            'min_booking_duration_hours',
            'max_booking_duration_hours',
            'license_plate_mode',
            'require_vehicle',
            'credits_enabled',
            'credits_per_booking',
        ]);

        if (($violation = $this->checkBookingPolicy($input, $user, $startTime)) !== null) {
            return $violation;
        }

        $endTimeParsed = isset($input['end_time']) && $input['end_time']
            ? Carbon::parse($input['end_time'])
            : $startTime->copy()->addHours(8);

        if (($violation = $this->checkDurationLimits($startTime, $endTimeParsed)) !== null) {
            return $violation;
        }

        $plate = $input['license_plate'] ?? $input['vehicle_plate'] ?? null;
        if (($violation = $this->checkVehicleRequirements($plate, $user)) !== null) {
            return $violation;
        }

        $lotId = $input['lot_id'] ?? null;
        $lot = $lotId ? ParkingLot::find($lotId) : null;
        if ($lot && ! empty($lot->operating_hours)) {
            if (! $lot->isOpenAt($startTime) || ! $lot->isOpenAt($endTimeParsed)) {
                return BookingCreationResult::fail('OUTSIDE_OPERATING_HOURS', 'Booking falls outside parking lot operating hours.', 422);
            }
        }

        $creditsEnabled = Setting::get('credits_enabled', 'false') === 'true';
        $creditsPerBooking = (int) Setting::get('credits_per_booking', '1');
        if ($creditsEnabled && ! $user->isAdmin() && $user->credits_balance < $creditsPerBooking) {
            return BookingCreationResult::fail(
                'INSUFFICIENT_CREDITS',
                'Not enough credits. Required: '.$creditsPerBooking.', Available: '.$user->credits_balance,
                422,
            );
        }

        $endTime = $endTimeParsed->toDateTimeString();
        $startTimeStr = $startTime->toDateTimeString();
        $slotId = $input['slot_id'] ?? null;

        if (! $slotId) {
            $slotId = $this->autoAssignSlot($lotId, $startTimeStr, $endTime);
            if ($slotId === null) {
                // Legacy payload shape preserved: plain {error, message} body.
                return BookingCreationResult::fail('NO_SLOTS_AVAILABLE', 'No available slots', 409);
            }
        }

        try {
            $booking = $this->persistBooking($input, $user, $slotId, $startTimeStr, $endTime, $creditsEnabled, $creditsPerBooking);
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return BookingCreationResult::fail('SLOT_UNAVAILABLE', 'Slot is already booked', 409);
            }
            throw $e;
        }

        $this->emitPostCreationEvents($booking, $user);

        return BookingCreationResult::ok($booking);
    }

    /**
     * Policy caps: max_advance_days, max_active_bookings, max_bookings_per_day.
     */
    private function checkBookingPolicy(array $input, User $user, Carbon $startTime): ?BookingCreationResult
    {
        $maxAdvanceDays = (int) config('parkhub.max_advance_days', 90);
        if ($maxAdvanceDays > 0 && now()->diffInDays($startTime, false) > $maxAdvanceDays && ! $user->isAdmin()) {
            return BookingCreationResult::fail(
                'BOOKING_TOO_FAR_AHEAD',
                "Cannot book more than {$maxAdvanceDays} days in advance.",
                422,
            );
        }

        $maxActive = (int) config('parkhub.max_active_bookings', 10);
        if ($maxActive > 0 && ! $user->isAdmin()) {
            // Only bookings that have not ended yet occupy the allowance.
            // Filtering on status alone counted elapsed bookings forever
            // and permanently locked the user out once they hit the cap
            // (nash87/parkhub-php#586). The `end_time` bound keeps the cap
            // correct even on installs where the scheduler that runs
            // CompleteElapsedBookingsJob has never fired.
            $activeCount = Booking::where('user_id', $user->id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('end_time', '>', now())
                ->count();
            if ($activeCount >= $maxActive) {
                return BookingCreationResult::fail('MAX_ACTIVE_BOOKINGS', "Maximum {$maxActive} active bookings allowed.", 422);
            }
        }

        $maxPerDay = (int) Setting::get('max_bookings_per_day', '0');
        if ($maxPerDay > 0 && ! $user->isAdmin()) {
            // A fulfilled booking must keep consuming its day's quota, so
            // COMPLETED counts here. Without it, CompleteElapsedBookingsJob
            // would hand the quota back as soon as a booking elapsed and a
            // user could rebook the same day indefinitely. Only CANCELLED
            // and NO_SHOW release the allowance.
            $todayCount = Booking::where('user_id', $user->id)
                ->whereDate('start_time', $startTime->toDateString())
                ->whereIn('status', [
                    Booking::STATUS_CONFIRMED,
                    Booking::STATUS_ACTIVE,
                    Booking::STATUS_COMPLETED,
                ])
                ->count();
            if ($todayCount >= $maxPerDay) {
                return BookingCreationResult::fail('MAX_BOOKINGS_REACHED', "Maximum {$maxPerDay} bookings per day reached.", 422);
            }
        }

        return null;
    }

    private function checkDurationLimits(Carbon $start, Carbon $end): ?BookingCreationResult
    {
        $durationHours = $start->diffInMinutes($end) / 60;

        $minDuration = (float) Setting::get('min_booking_duration_hours', '0');
        if ($minDuration > 0 && $durationHours < $minDuration) {
            return BookingCreationResult::fail('DURATION_TOO_SHORT', "Minimum booking duration is {$minDuration} hours.", 422);
        }

        $maxDuration = (float) Setting::get('max_booking_duration_hours', '0');
        if ($maxDuration > 0 && $durationHours > $maxDuration) {
            return BookingCreationResult::fail('DURATION_TOO_LONG', "Maximum booking duration is {$maxDuration} hours.", 422);
        }

        return null;
    }

    private function checkVehicleRequirements(?string $plate, User $user): ?BookingCreationResult
    {
        $plateMode = Setting::get('license_plate_mode', 'optional');
        if ($plateMode === 'required' && empty($plate) && ! $user->isAdmin()) {
            return BookingCreationResult::fail('PLATE_REQUIRED', 'A license plate is required for booking.', 422);
        }

        if (Setting::get('require_vehicle', 'false') === 'true' && empty($plate) && ! $user->isAdmin()) {
            return BookingCreationResult::fail('VEHICLE_REQUIRED', 'A vehicle is required for booking.', 422);
        }

        return null;
    }

    /**
     * Read-only scan for a free slot in the requested lot.
     */
    private function autoAssignSlot(?string $lotId, string $startTimeStr, string $endTime): ?string
    {
        if (! $lotId) {
            return null;
        }

        $bookedSlotIds = Booking::where('lot_id', $lotId)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTimeStr)
            ->pluck('slot_id');

        $slot = ParkingSlot::where('lot_id', $lotId)
            ->whereNotIn('id', $bookedSlotIds)
            ->first();

        return $slot?->id;
    }

    /**
     * Transactional insert with slot row lock, conflict recheck, pricing
     * and (optional) credit deduction. Retries up to 3x on deadlock.
     */
    private function persistBooking(
        array $input,
        User $user,
        string $slotId,
        string $startTimeStr,
        string $endTime,
        bool $creditsEnabled,
        int $creditsPerBooking,
    ): Booking {
        $booking = null;

        DB::transaction(function () use (
            $input,
            $user,
            $slotId,
            $startTimeStr,
            $endTime,
            $creditsEnabled,
            $creditsPerBooking,
            &$booking,
        ) {
            $slot = ParkingSlot::where('id', $slotId)->lockForUpdate()->firstOrFail();

            $conflict = Booking::where('slot_id', $slotId)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTimeStr)
                ->exists();

            if ($conflict) {
                throw new \RuntimeException('SLOT_CONFLICT');
            }

            $lot = ParkingLot::findOrFail($input['lot_id']);

            $booking = Booking::create([
                'user_id' => $user->id,
                'lot_id' => $input['lot_id'],
                'slot_id' => $slotId,
                'booking_type' => $input['booking_type'] ?? 'einmalig',
                'lot_name' => $lot->name,
                'slot_number' => $slot->slot_number,
                'vehicle_plate' => $input['license_plate'] ?? $input['vehicle_plate'] ?? null,
                'start_time' => $input['start_time'],
                'end_time' => $endTime,
                'status' => Booking::STATUS_CONFIRMED,
                'notes' => $input['notes'] ?? null,
                'recurrence' => $input['recurrence'] ?? null,
            ]);

            $this->applyPricing($booking, $lot);

            if ($creditsEnabled && ! $user->isAdmin()) {
                $user->decrement('credits_balance', $creditsPerBooking);
                CreditTransaction::create([
                    'user_id' => $user->id,
                    'booking_id' => $booking->id,
                    'amount' => -$creditsPerBooking,
                    'type' => 'deduction',
                    'description' => 'Booking #'.substr($booking->id, 0, 8),
                ]);
            }
        }, 3);

        /** @var Booking $booking */
        return $booking;
    }

    /**
     * Apply lot hourly/daily pricing using the seller-country VAT profile.
     *
     * The rate is resolved through {@see TaxProfileRegistry} so the
     * booking's persisted `tax_amount` matches whatever jurisdiction the
     * operator configured via `tax_seller_country` / `impressum_country`.
     * Reverse-charge is *not* applied at booking-creation time — it only
     * engages later at invoice-render when the buyer's country and VAT
     * ID are known and may differ from the seller.
     */
    private function applyPricing(Booking $booking, ParkingLot $lot): void
    {
        if (! $lot->hourly_rate) {
            return;
        }

        $bookingStart = Carbon::parse($booking->start_time);
        $bookingEnd = Carbon::parse($booking->end_time);
        $durationHrs = $bookingStart->diffInMinutes($bookingEnd) / 60;
        $basePrice = round($durationHrs * (float) $lot->hourly_rate, 2);

        if ($lot->daily_max && $basePrice > (float) $lot->daily_max) {
            $basePrice = round((float) $lot->daily_max, 2);
        }

        $sellerCountry = TaxProfileRegistry::resolveSellerCountryFromSettings();
        $vatRate = TaxProfileRegistry::resolveProfile($sellerCountry)->standardRate;
        $taxAmount = round($basePrice * $vatRate, 2);
        $totalPrice = round($basePrice + $taxAmount, 2);

        $booking->update([
            'base_price' => $basePrice,
            'tax_amount' => $taxAmount,
            'total_price' => $totalPrice,
            'currency' => $lot->currency ?? 'EUR',
        ]);
    }

    /**
     * Audit log, confirmation email, webhooks, and realtime event.
     * Identical to the legacy controller's post-transaction block.
     */
    private function emitPostCreationEvents(Booking $booking, User $user): void
    {
        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'booking_created',
            'details' => ['booking_id' => $booking->id, 'slot' => $booking->slot_number],
        ]);

        SendBookingConfirmationJob::dispatch($booking->id, $user->id);

        $activeWebhooks = Cache::remember('active_webhooks', 60, fn () => Webhook::where('active', true)->get());
        foreach ($activeWebhooks as $webhook) {
            if (in_array('booking.created', $webhook->events ?? [])) {
                SendWebhookJob::dispatch($webhook->id, 'booking.created', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'slot_id' => $booking->slot_id,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                ]);
            }
        }

        BookingCreated::dispatch($booking);
    }
}
