<?php

namespace App\Http\Controllers\Api;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\GuestBookingResource;
use App\Http\Resources\SwapRequestResource;
use App\Jobs\SendBookingConfirmationJob;
use App\Jobs\SendWebhookJob;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingNote;
use App\Models\CreditTransaction;
use App\Models\GuestBooking;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\SwapRequest;
use App\Models\WaitlistEntry;
use App\Models\Webhook;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date',
            'status' => 'sometimes|string|in:active,confirmed,cancelled,completed,expired',
        ]);

        $query = Booking::with(['lot', 'slot'])
            ->where('user_id', $request->user()->id);
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date);
        }

        $perPage = min((int) $request->get('per_page', 50), 200);

        $paginated = $query->orderBy('start_time', 'desc')->paginate($perPage);

        // Return the canonical {success, data, error, meta} envelope so the
        // frontend sees Booking[] directly at `data`. Without this wrap
        // Laravel's ResourceCollection ships `{data: [...], links, meta}`
        // which breaks the `getBookings(): Booking[]` contract and causes
        // Bookings.tsx to crash on `.filter()` over an object.
        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($paginated->items())->toArray($request),
            'error' => null,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ],
        ]);
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        $startTime = Carbon::parse($request->start_time);
        if ($startTime->isPast()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'INVALID_BOOKING_TIME', 'message' => 'Booking start time must be in the future.'],
                'meta' => null,
            ], 422);
        }

        $user = $request->user();

        // Bulk-fetch all settings consumed during booking creation in one query
        Setting::preload([
            'max_bookings_per_day',
            'min_booking_duration_hours',
            'max_booking_duration_hours',
            'license_plate_mode',
            'require_vehicle',
            'credits_enabled',
            'credits_per_booking',
        ]);

        // Enforce max advance days (config-based booking policy)
        $maxAdvanceDays = (int) config('parkhub.max_advance_days', 90);
        if ($maxAdvanceDays > 0 && now()->diffInDays($startTime, false) > $maxAdvanceDays && ! $user->isAdmin()) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'BOOKING_TOO_FAR_AHEAD', 'message' => "Cannot book more than {$maxAdvanceDays} days in advance."],
                'meta' => null,
            ], 422);
        }

        // Enforce max active bookings (config-based booking policy)
        $maxActive = (int) config('parkhub.max_active_bookings', 10);
        if ($maxActive > 0 && ! $user->isAdmin()) {
            $activeCount = Booking::where('user_id', $user->id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->count();
            if ($activeCount >= $maxActive) {
                return response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'MAX_ACTIVE_BOOKINGS', 'message' => "Maximum {$maxActive} active bookings allowed."],
                    'meta' => null,
                ], 422);
            }
        }

        // Enforce max bookings per day
        $maxPerDay = (int) Setting::get('max_bookings_per_day', '0');
        if ($maxPerDay > 0 && ! $user->isAdmin()) {
            $todayCount = Booking::where('user_id', $user->id)
                ->whereDate('start_time', $startTime->toDateString())
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->count();
            if ($todayCount >= $maxPerDay) {
                return response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'MAX_BOOKINGS_REACHED', 'message' => "Maximum {$maxPerDay} bookings per day reached."],
                    'meta' => null,
                ], 422);
            }
        }

        // Enforce booking duration limits
        $endTimeParsed = $request->end_time ? Carbon::parse($request->end_time) : $startTime->copy()->addHours(8);
        $durationHours = $startTime->diffInMinutes($endTimeParsed) / 60;

        $minDuration = (float) Setting::get('min_booking_duration_hours', '0');
        if ($minDuration > 0 && $durationHours < $minDuration) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'DURATION_TOO_SHORT', 'message' => "Minimum booking duration is {$minDuration} hours."],
                'meta' => null,
            ], 422);
        }

        $maxDuration = (float) Setting::get('max_booking_duration_hours', '0');
        if ($maxDuration > 0 && $durationHours > $maxDuration) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'DURATION_TOO_LONG', 'message' => "Maximum booking duration is {$maxDuration} hours."],
                'meta' => null,
            ], 422);
        }

        // Enforce license plate mode
        $plateMode = Setting::get('license_plate_mode', 'optional');
        $plate = $request->license_plate ?? $request->vehicle_plate;
        if ($plateMode === 'required' && empty($plate) && ! $user->isAdmin()) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'PLATE_REQUIRED', 'message' => 'A license plate is required for booking.'],
                'meta' => null,
            ], 422);
        }

        // Enforce require_vehicle
        if (Setting::get('require_vehicle', 'false') === 'true' && empty($plate) && ! $user->isAdmin()) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'VEHICLE_REQUIRED', 'message' => 'A vehicle is required for booking.'],
                'meta' => null,
            ], 422);
        }

        // Validate against operating hours
        $lot = ParkingLot::find($request->lot_id);
        if ($lot && ! empty($lot->operating_hours)) {
            $endTimeParsedForHours = $request->end_time ? Carbon::parse($request->end_time) : $startTime->copy()->addHours(8);
            if (! $lot->isOpenAt($startTime) || ! $lot->isOpenAt($endTimeParsedForHours)) {
                return response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'OUTSIDE_OPERATING_HOURS', 'message' => 'Booking falls outside parking lot operating hours.'],
                    'meta' => null,
                ], 422);
            }
        }

        // Credits check — if credits system is enabled, verify balance
        $creditsEnabled = Setting::get('credits_enabled', 'false') === 'true';
        $creditsPerBooking = (int) Setting::get('credits_per_booking', '1');

        if ($creditsEnabled && ! $user->isAdmin()) {
            if ($user->credits_balance < $creditsPerBooking) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'code' => 'INSUFFICIENT_CREDITS',
                        'message' => 'Not enough credits. Required: '.$creditsPerBooking.', Available: '.$user->credits_balance,
                    ],
                    'meta' => null,
                ], 422);
            }
        }

        $endTime = $request->end_time
            ? Carbon::parse($request->end_time)->toDateTimeString()
            : now()->addHours(8)->toDateTimeString();
        $startTimeStr = $startTime->toDateTimeString();
        $slotId = $request->slot_id;

        // Auto-assign slot if not provided (outside transaction — read-only scan)
        if (! $slotId) {
            $bookedSlotIds = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTimeStr)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->whereNotIn('id', $bookedSlotIds)
                ->first();
            if (! $slot) {
                return response()->json(['error' => 'NO_SLOTS_AVAILABLE', 'message' => 'No available slots'], 409);
            }
            $slotId = $slot->id;
        }

        $booking = null;

        try {
            // Use a transaction with exclusive slot lock to prevent race conditions
            DB::transaction(function () use ($request, $slotId, $endTime, $startTimeStr, &$booking, $creditsEnabled, $creditsPerBooking) {
                // Lock the slot row for this transaction
                $slot = ParkingSlot::where('id', $slotId)->lockForUpdate()->firstOrFail();

                // Re-check conflict with locked data
                $conflict = Booking::where('slot_id', $slotId)
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTimeStr)
                    ->exists();

                if ($conflict) {
                    throw new \Exception('SLOT_CONFLICT');
                }

                $lot = ParkingLot::findOrFail($request->lot_id);

                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'booking_type' => $request->booking_type ?? 'einmalig',
                    'lot_name' => $lot->name,
                    'slot_number' => $slot->slot_number,
                    'vehicle_plate' => $request->license_plate ?? $request->vehicle_plate,
                    'start_time' => $request->start_time,
                    'end_time' => $endTime,
                    'status' => Booking::STATUS_CONFIRMED,
                    'notes' => $request->notes,
                    'recurrence' => $request->recurrence,
                ]);
                // Calculate pricing based on lot rates
                if ($lot->hourly_rate) {
                    $bookingStart = Carbon::parse($request->start_time);
                    $bookingEnd = Carbon::parse($endTime);
                    $durationHrs = $bookingStart->diffInMinutes($bookingEnd) / 60;
                    $basePrice = round($durationHrs * (float) $lot->hourly_rate, 2);

                    // Apply daily max cap if set
                    if ($lot->daily_max && $basePrice > (float) $lot->daily_max) {
                        $basePrice = round((float) $lot->daily_max, 2);
                    }

                    // German standard VAT at 19%
                    $taxAmount = round($basePrice * 0.19, 2);
                    $totalPrice = round($basePrice + $taxAmount, 2);

                    $booking->update([
                        'base_price' => $basePrice,
                        'tax_amount' => $taxAmount,
                        'total_price' => $totalPrice,
                        'currency' => $lot->currency ?? 'EUR',
                    ]);
                }

                // Deduct credits within the same transaction
                if ($creditsEnabled && ! $request->user()->isAdmin()) {
                    $request->user()->decrement('credits_balance', $creditsPerBooking);
                    CreditTransaction::create([
                        'user_id' => $request->user()->id,
                        'booking_id' => $booking->id,
                        'amount' => -$creditsPerBooking,
                        'type' => 'deduction',
                        'description' => 'Booking #'.substr($booking->id, 0, 8),
                    ]);
                }
            }, 3); // 3 retries on deadlock
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot is already booked'], 409);
            }
            throw $e;
        }

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_created',
            'details' => ['booking_id' => $booking->id, 'slot' => $booking->slot_number],
        ]);

        // Send booking confirmation email via job queue
        SendBookingConfirmationJob::dispatch($booking->id, $request->user()->id);

        // Dispatch webhook events (cached for 60s to avoid per-booking queries)
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

        // Broadcast real-time event to the booking owner's private channel
        BookingCreated::dispatch($booking);

        return BookingResource::make($booking)->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Booking not found.'], 'meta' => null], 404);
        }

        $this->authorize('view', $booking);

        return BookingResource::make($booking);
    }

    public function destroy(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('delete', $booking);

        // Mark as cancelled instead of hard-deleting — preserves audit trail
        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        // Refund credits if credits system is enabled
        $creditsEnabled = Setting::get('credits_enabled', 'false') === 'true';
        $creditsPerBooking = (int) Setting::get('credits_per_booking', '1');
        if ($creditsEnabled && ! $request->user()->isAdmin()) {
            $request->user()->increment('credits_balance', $creditsPerBooking);
            CreditTransaction::create([
                'user_id' => $request->user()->id,
                'booking_id' => $booking->id,
                'amount' => $creditsPerBooking,
                'type' => 'refund',
                'description' => 'Cancelled booking #'.substr($booking->id, 0, 8),
            ]);
        }

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_cancelled',
            'details' => ['booking_id' => $id, 'lot_id' => $booking->lot_id, 'slot_id' => $booking->slot_id],
        ]);

        // Notify waitlist users that a slot has become available in this lot
        $this->notifyWaitlist($booking->lot_id, $booking->slot_id);

        // Dispatch webhook events (cached for 60s to avoid per-booking queries)
        $activeWebhooks = Cache::remember('active_webhooks', 60, fn () => Webhook::where('active', true)->get());
        foreach ($activeWebhooks as $webhook) {
            if (in_array('booking.cancelled', $webhook->events ?? [])) {
                SendWebhookJob::dispatch($webhook->id, 'booking.cancelled', [
                    'booking_id' => $booking->id,
                ]);
            }
        }

        // Broadcast real-time event to the booking owner's private channel
        BookingCancelled::dispatch($booking);

        return response()->json(['message' => 'Booking cancelled']);
    }

    private function notifyWaitlist(string $lotId, string $slotId): void
    {
        $waiting = WaitlistEntry::where('lot_id', $lotId)
            ->whereNull('notified_at')
            ->with('user')
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        foreach ($waiting as $entry) {
            if ($entry->user && $entry->user->email) {
                $entry->update(['notified_at' => now()]);
                Notification::create([
                    'user_id' => $entry->user_id,
                    'type' => 'waitlist_slot_available',
                    'title' => 'Stellplatz verfügbar',
                    'message' => 'Ein Stellplatz in Ihrem gewünschten Parkplatz ist jetzt verfügbar. Jetzt buchen!',
                    'data' => ['lot_id' => $lotId, 'slot_id' => $slotId],
                ]);
            }
        }
    }

    public function quickBook(Request $request)
    {
        // Accepts either slot_id directly, or lot_id+date to auto-pick
        if ($request->has('slot_id')) {
            $slot = ParkingSlot::findOrFail($request->slot_id);
        } elseif ($request->has('lot_id')) {
            $date = $request->date ? now()->parse($request->date) : now();
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $taken = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $dayEnd)
                ->where('end_time', '>', $dayStart)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->where('status', 'available')
                ->whereNotIn('id', $taken)
                ->first();
            if (! $slot) {
                return response()->json(['error' => 'NO_SLOTS', 'message' => 'No slots available'], 409);
            }
        } else {
            return response()->json(['error' => 'INVALID_REQUEST', 'message' => 'Provide slot_id or lot_id'], 422);
        }

        $startTime = $request->date ? now()->parse($request->date)->startOfDay() : now();
        $endOfDay = $request->date ? now()->parse($request->date)->endOfDay() : now()->endOfDay();

        $booking = null;

        try {
            DB::transaction(function () use ($request, $slot, $startTime, $endOfDay, &$booking) {
                // Lock the slot row to prevent race conditions
                ParkingSlot::where('id', $slot->id)->lockForUpdate()->firstOrFail();

                $conflict = Booking::where('slot_id', $slot->id)
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                    ->where('start_time', '<', $endOfDay)
                    ->where('end_time', '>', $startTime)
                    ->exists();

                if ($conflict) {
                    throw new \Exception('SLOT_CONFLICT');
                }

                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'lot_id' => $slot->lot_id,
                    'slot_id' => $slot->id,
                    'booking_type' => 'einmalig',
                    'lot_name' => $slot->lot?->name,
                    'slot_number' => $slot->slot_number,
                    'vehicle_plate' => $request->license_plate,
                    'start_time' => now(),
                    'end_time' => $endOfDay,
                    'status' => Booking::STATUS_CONFIRMED,
                ]);
            }, 3);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot taken'], 409);
            }
            throw $e;
        }

        return BookingResource::make($booking)->response()->setStatusCode(200);
    }

    public function guestBooking(Request $request)
    {
        // Enforce allow_guest_bookings setting
        if (Setting::get('allow_guest_bookings', 'false') !== 'true') {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'GUEST_BOOKINGS_DISABLED', 'message' => 'Guest bookings are disabled.'],
                'meta' => null,
            ], 403);
        }

        $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'nullable|uuid',
            'guest_name' => 'required|string',
            'end_time' => 'required|date',
        ]);

        $slotId = $request->slot_id;
        if (! $slotId) {
            $startTime = $request->start_time ?? now();
            $bookedSlotIds = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $startTime)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->whereNotIn('id', $bookedSlotIds)
                ->first();
            if (! $slot) {
                return response()->json(['error' => 'NO_SLOTS_AVAILABLE'], 409);
            }
            $slotId = $slot->id;
        }

        $guest = null;

        try {
            DB::transaction(function () use ($request, $slotId, &$guest) {
                // Lock the slot row to prevent race conditions
                ParkingSlot::where('id', $slotId)->lockForUpdate()->firstOrFail();

                $guestStartTime = $request->start_time ?? now();

                // Re-check conflict with locked data
                $conflict = Booking::where('slot_id', $slotId)
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                    ->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $guestStartTime)
                    ->exists();

                if ($conflict) {
                    throw new \Exception('SLOT_CONFLICT');
                }

                $guest = GuestBooking::create([
                    'created_by' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'guest_name' => $request->guest_name,
                    'guest_code' => strtoupper(Str::random(8)),
                    'start_time' => $guestStartTime,
                    'end_time' => $request->end_time,
                    'vehicle_plate' => $request->vehicle_plate,
                    'status' => Booking::STATUS_CONFIRMED,
                ]);

                Booking::create([
                    'user_id' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'booking_type' => 'einmalig',
                    'vehicle_plate' => $request->vehicle_plate,
                    'start_time' => $guestStartTime,
                    'end_time' => $request->end_time,
                    'status' => Booking::STATUS_CONFIRMED,
                    'notes' => 'Guest: '.$request->guest_name,
                ]);
            }, 3);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot is already booked'], 409);
            }
            throw $e;
        }

        return GuestBookingResource::make($guest)->response()->setStatusCode(201);
    }

    public function swap(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|uuid',
            'target_slot_id' => 'required|uuid',
        ]);

        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($request->booking_id);

        $newSlot = ParkingSlot::findOrFail($request->target_slot_id);

        // Validate that the target slot belongs to the same lot as the booking
        if ($newSlot->lot_id !== $booking->lot_id) {
            return response()->json(['error' => 'CROSS_LOT_SWAP', 'message' => 'Target slot must belong to the same lot as the current booking'], 422);
        }

        return DB::transaction(function () use ($booking, $newSlot, $request) {
            $conflict = Booking::where('slot_id', $request->target_slot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $booking->end_time)
                ->where('end_time', '>', $booking->start_time)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                return response()->json(['error' => 'SLOT_UNAVAILABLE'], 409);
            }
            $booking->update([
                'slot_id' => $request->target_slot_id,
                'slot_number' => $newSlot->slot_number,
            ]);

            return BookingResource::make($booking->fresh());
        });
    }

    public function updateNotes(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('updateNotes', $booking);
        $user = $request->user();

        $request->validate([
            'notes' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:2000',
        ]);

        $booking->update(['notes' => $request->notes]);

        if ($request->filled('note')) {
            BookingNote::create([
                'booking_id' => $id,
                'user_id' => $user->id,
                'note' => $request->note,
            ]);
        }

        return BookingResource::make($booking->fresh());
    }

    public function checkin(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('update', $booking);
        $booking->update(['checked_in_at' => now(), 'status' => Booking::STATUS_ACTIVE]);
        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_checkin',
            'details' => ['booking_id' => $id],
        ]);

        return BookingResource::make($booking->fresh());
    }

    public function update(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('update', $booking);

        $data = $request->only(['notes', 'vehicle_plate']);

        // Only active/confirmed bookings can be modified
        if ($request->hasAny(['start_time', 'end_time', 'slot_id'])) {
            if (! in_array($booking->status, [Booking::STATUS_ACTIVE, Booking::STATUS_CONFIRMED])) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'INVALID_STATUS', 'message' => 'Only active or confirmed bookings can be modified.'],
                ], 422);
            }
        }

        $newStartTime = $request->has('start_time') ? Carbon::parse($request->start_time) : Carbon::parse($booking->start_time);
        $newEndTime = $request->has('end_time') ? Carbon::parse($request->end_time) : Carbon::parse($booking->end_time);
        $newSlotId = $request->input('slot_id', $booking->slot_id);
        $timeChanged = $request->hasAny(['start_time', 'end_time']);
        $slotChanged = $request->has('slot_id') && $newSlotId !== $booking->slot_id;

        if ($timeChanged || $slotChanged) {
            if ($newEndTime->lte($newStartTime)) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'INVALID_TIME', 'message' => 'End time must be after start time.'],
                ], 422);
            }

            // Validate against operating hours
            $lot = ParkingLot::find($booking->lot_id);
            if ($lot && ! empty($lot->operating_hours)) {
                if (! $lot->isOpenAt($newStartTime) || ! $lot->isOpenAt($newEndTime)) {
                    return response()->json([
                        'success' => false,
                        'error' => ['code' => 'OUTSIDE_OPERATING_HOURS', 'message' => 'Booking falls outside parking lot operating hours.'],
                    ], 422);
                }
            }

            // If slot changed, verify the new slot exists and belongs to the same lot
            if ($slotChanged) {
                $newSlot = ParkingSlot::where('id', $newSlotId)
                    ->where('lot_id', $booking->lot_id)
                    ->first();
                if (! $newSlot) {
                    return response()->json([
                        'success' => false,
                        'error' => ['code' => 'INVALID_SLOT', 'message' => 'Slot not found in this parking lot.'],
                    ], 422);
                }
            }

            // Check for conflicts on the target slot
            $conflict = Booking::where('slot_id', $newSlotId)
                ->where('id', '!=', $booking->id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $newEndTime)
                ->where('end_time', '>', $newStartTime)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'SLOT_CONFLICT', 'message' => 'Modification would conflict with another booking.'],
                ], 409);
            }

            if ($request->has('start_time')) {
                $data['start_time'] = $newStartTime->toDateTimeString();
            }
            if ($request->has('end_time')) {
                $data['end_time'] = $newEndTime->toDateTimeString();
            }
            if ($slotChanged) {
                $data['slot_id'] = $newSlotId;
                $data['slot_number'] = ParkingSlot::find($newSlotId)?->slot_number;
            }

            $auditDetails = ['booking_id' => $id];
            if ($request->has('start_time')) {
                $auditDetails['old_start_time'] = (string) $booking->start_time;
                $auditDetails['new_start_time'] = $data['start_time'];
            }
            if ($request->has('end_time')) {
                $auditDetails['old_end_time'] = (string) $booking->end_time;
                $auditDetails['new_end_time'] = $data['end_time'];
            }
            if ($slotChanged) {
                $auditDetails['old_slot_id'] = $booking->slot_id;
                $auditDetails['new_slot_id'] = $newSlotId;
            }

            AuditLog::log([
                'user_id' => $request->user()->id,
                'username' => $request->user()->username,
                'action' => 'booking_modified',
                'details' => $auditDetails,
            ]);

            // Notify user about the modification
            Notification::create([
                'user_id' => $request->user()->id,
                'type' => 'booking_modified',
                'title' => 'Buchung geändert',
                'message' => 'Ihre Buchung #'.substr($id, 0, 8).' wurde erfolgreich geändert.',
                'data' => $auditDetails,
            ]);
        }

        $booking->update($data);

        return BookingResource::make($booking->fresh());
    }

    public function calendarEvents(Request $request)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateTimeString();
        $to = $request->to ?? now()->endOfMonth()->toDateTimeString();
        $bookings = Booking::where('user_id', $request->user()->id)
            ->where('start_time', '>=', $from)
            ->where('end_time', '<=', $to)
            ->select(['id', 'lot_name', 'slot_number', 'start_time', 'end_time', 'status'])
            ->get();
        $events = $bookings->map(function ($b) {
            return [
                'id' => $b->id,
                'title' => $b->lot_name.' — '.$b->slot_number,
                'start' => $b->start_time,
                'end' => $b->end_time,
                'type' => 'booking',
                'status' => $b->status,
            ];
        });

        return response()->json($events->values());
    }

    /**
     * iCal feed — returns all active bookings as .ics for calendar subscription.
     */
    public function ical(Request $request)
    {
        $user = $request->user();
        $bookings = Booking::where('user_id', $user->id)
            ->whereIn('status', [Booking::STATUS_ACTIVE, Booking::STATUS_CONFIRMED])
            ->whereNotNull('start_time')
            ->get();

        $orgName = Setting::get('company_name', 'ParkHub');
        $prodId = '-//ParkHub//Bookings//EN';
        $now = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            "PRODID:{$prodId}",
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:{$orgName} Parking",
            'X-WR-TIMEZONE:Europe/Berlin',
        ];

        foreach ($bookings as $b) {
            $uid = $b->id.'@parkhub';
            $start = gmdate('Ymd\THis\Z', strtotime($b->start_time));
            $end = $b->end_time
                ? gmdate('Ymd\THis\Z', strtotime($b->end_time))
                : gmdate('Ymd\THis\Z', strtotime($b->start_time) + 3600);
            $summary = "Parking: {$b->slot_number} ({$b->lot_name})";
            $location = $b->lot_name ?? '';
            $description = $b->vehicle_plate ? "Vehicle: {$b->vehicle_plate}" : '';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$now}";
            $lines[] = "DTSTART:{$start}";
            $lines[] = "DTEND:{$end}";
            $lines[] = "SUMMARY:{$summary}";
            if ($location) {
                $lines[] = "LOCATION:{$location}";
            }
            if ($description) {
                $lines[] = "DESCRIPTION:{$description}";
            }
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        $ical = implode("\r\n", $lines)."\r\n";

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="parkhub-bookings.ics"',
        ]);
    }

    public function createSwapRequest(Request $request, string $id)
    {
        $request->validate([
            'target_booking_id' => 'required|uuid',
            'message' => 'nullable|string|max:500',
        ]);

        $booking = Booking::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($id);

        $target = Booking::where('status', 'active')->findOrFail($request->target_booking_id);

        if ($target->user_id === $request->user()->id) {
            return response()->json(['error' => 'SELF_SWAP', 'message' => 'Cannot swap with your own booking'], 422);
        }

        // Check for existing pending swap request
        $existing = SwapRequest::where('requester_booking_id', $booking->id)
            ->where('target_booking_id', $target->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['error' => 'DUPLICATE', 'message' => 'Swap request already pending'], 409);
        }

        $swap = SwapRequest::create([
            'requester_booking_id' => $booking->id,
            'target_booking_id' => $target->id,
            'requester_id' => $request->user()->id,
            'target_id' => $target->user_id,
            'status' => 'pending',
            'message' => $request->message,
        ]);

        // Notify the target user
        Notification::create([
            'user_id' => $target->user_id,
            'type' => 'swap_request',
            'title' => 'Tausch-Anfrage',
            'message' => $request->user()->name.' möchte Stellplatz '.$booking->slot_number.' gegen '.$target->slot_number.' tauschen.',
            'data' => ['swap_request_id' => $swap->id],
        ]);

        return SwapRequestResource::make($swap->load(['requesterBooking', 'targetBooking', 'requester']))->response()->setStatusCode(201);
    }

    public function respondSwapRequest(Request $request, string $id)
    {
        $request->validate([
            'accept' => 'required|boolean',
        ]);

        $swap = SwapRequest::where('target_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        if ($request->accept) {
            // Perform the actual slot swap
            $requesterBooking = Booking::findOrFail($swap->requester_booking_id);
            $targetBooking = Booking::findOrFail($swap->target_booking_id);

            DB::transaction(function () use ($requesterBooking, $targetBooking) {
                $tempSlot = $requesterBooking->slot_id;
                $tempSlotNumber = $requesterBooking->slot_number;

                $requesterBooking->update([
                    'slot_id' => $targetBooking->slot_id,
                    'slot_number' => $targetBooking->slot_number,
                ]);

                $targetBooking->update([
                    'slot_id' => $tempSlot,
                    'slot_number' => $tempSlotNumber,
                ]);
            });

            $swap->update(['status' => 'accepted']);

            // Notify requester
            Notification::create([
                'user_id' => $swap->requester_id,
                'type' => 'swap_accepted',
                'title' => 'Tausch angenommen',
                'message' => $request->user()->name.' hat Ihren Tausch angenommen. Neuer Stellplatz: '.$requesterBooking->fresh()->slot_number,
            ]);
        } else {
            $swap->update(['status' => 'declined']);

            Notification::create([
                'user_id' => $swap->requester_id,
                'type' => 'swap_declined',
                'title' => 'Tausch abgelehnt',
                'message' => $request->user()->name.' hat Ihren Tausch abgelehnt.',
            ]);
        }

        return SwapRequestResource::make($swap->fresh()->load(['requesterBooking', 'targetBooking']));
    }

    public function extend(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'new_end_time' => 'required|date|after:now',
        ]);

        $booking = Booking::where('user_id', $request->user()->id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
            ->findOrFail($id);

        // Check no conflict with new end time
        $conflict = Booking::where('slot_id', $booking->slot_id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
            ->where('start_time', '<', $validated['new_end_time'])
            ->where('end_time', '>', $booking->end_time)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'SLOT_CONFLICT'], 409);
        }

        $oldEndTime = $booking->end_time;
        $booking->update(['end_time' => $validated['new_end_time']]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_extended',
            'details' => [
                'booking_id' => $id,
                'old_end_time' => $oldEndTime,
                'new_end_time' => $validated['new_end_time'],
            ],
        ]);

        return response()->json(new BookingResource($booking->fresh()));
    }

    public function swapRequests(Request $request)
    {
        $userId = $request->user()->id;
        $perPage = min((int) $request->get('per_page', 50), 200);

        $incoming = SwapRequest::where('target_id', $userId)
            ->with(['requesterBooking', 'targetBooking', 'requester'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'incoming_page');

        $outgoing = SwapRequest::where('requester_id', $userId)
            ->with(['requesterBooking', 'targetBooking', 'target'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'outgoing_page');

        return response()->json([
            'incoming' => SwapRequestResource::collection($incoming)->response()->getData(true),
            'outgoing' => SwapRequestResource::collection($outgoing)->response()->getData(true),
        ]);
    }
}
