<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\BookingCancelled;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBookingsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingNotesRequest;
use App\Http\Resources\BookingResource;
use App\Jobs\SendWebhookJob;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingNote;
use App\Models\CreditTransaction;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Models\WaitlistEntry;
use App\Models\Webhook;
use App\Services\Booking\BookingCreationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Core booking CRUD: list / show / create / modify / cancel plus the
 * "quick book" helper and free-form note updates.
 *
 * Specialised flows live in sibling controllers so this class stays
 * focused (T-1743):
 *   - BookingCheckInController   (checkin, extend)
 *   - BookingSwapController      (swap, swap-requests)
 *   - GuestBookingController     (guest passes)
 *   - BookingCalendarController  (calendar events + iCal feed)
 */
class BookingController extends Controller
{
    public function index(IndexBookingsRequest $request)
    {
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

    public function store(StoreBookingRequest $request, BookingCreationService $service)
    {
        $result = $service->create($request->all(), $request->user());

        if ($result->isOk()) {
            return BookingResource::make($result->booking)->response()->setStatusCode(201);
        }

        // 409 conflicts use the legacy plain {error, message} shape so
        // existing clients keep parsing the response the same way.
        if ($result->status === 409) {
            $slug = $result->errorCode === 'SLOT_UNAVAILABLE'
                ? 'SLOT_UNAVAILABLE'
                : 'NO_SLOTS_AVAILABLE';

            return response()->json([
                'error' => $slug,
                'message' => $result->errorMessage,
            ], 409);
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'error' => ['code' => $result->errorCode, 'message' => $result->errorMessage],
            'meta' => null,
        ], $result->status);
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

    public function co2Summary(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'lot_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $validator->errors()->first(),
                    'details' => $validator->errors()->toArray(),
                ],
                'meta' => null,
            ], 422);
        }

        $to = $request->query('to') ? Carbon::parse($request->query('to')) : now();
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : $to->copy()->subDays(30);

        $query = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('start_time', [$from, $to]);

        if ($request->filled('lot_id')) {
            $query->where('lot_id', $request->query('lot_id'));
        }

        $bookings = $query->get();
        $bookingsCounted = $bookings->count();
        $kmPerBooking = 24.0;
        $baselineGPerKm = 210.0;
        $totalKm = $bookingsCounted * $kmPerBooking;
        $counterfactualG = $bookingsCounted * $baselineGPerKm * $kmPerBooking;
        $vehicleTypesByPlate = $this->vehicleTypesByPlate($request->user()->id, $bookings->pluck('vehicle_plate')->filter()->all());
        $emittedG = $bookings->sum(function (Booking $booking) use ($kmPerBooking, $vehicleTypesByPlate): float {
            $plate = $booking->vehicle_plate ? strtoupper(trim($booking->vehicle_plate)) : null;
            $vehicleType = $plate ? ($vehicleTypesByPlate[$plate] ?? null) : null;

            return $this->co2EmissionFactor($vehicleType) * $kmPerBooking;
        });

        $slotTimes = [];
        foreach ($bookings as $booking) {
            $bucket = intdiv($booking->start_time->timestamp, 1800);
            $key = "{$booking->lot_id}:{$bucket}";
            $slotTimes[$key] = ($slotTimes[$key] ?? 0) + 1;
        }
        $carpoolTripsSaved = array_sum(array_map(
            fn (int $count): int => max($count - 1, 0),
            $slotTimes,
        ));
        $savedG = max($counterfactualG - $emittedG, 0.0);
        $carpoolSavedG = $carpoolTripsSaved * $baselineGPerKm * $kmPerBooking;
        $totalSavedG = $savedG + $carpoolSavedG;

        return response()->json([
            'success' => true,
            'data' => [
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
                'bookings_counted' => $bookingsCounted,
                'total_km' => $totalKm,
                'emitted_g' => $emittedG,
                'counterfactual_g' => $counterfactualG,
                'saved_g' => $savedG,
                'carpool_saved_g' => $carpoolSavedG,
                'saved_kg' => round($totalSavedG / 1000, 2),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * @param  array<int, string>  $plates
     * @return array<string, string>
     */
    private function vehicleTypesByPlate(string $userId, array $plates): array
    {
        $normalizedPlates = collect($plates)
            ->map(fn (string $plate): string => strtoupper(trim($plate)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedPlates->isEmpty()) {
            return [];
        }

        $vehicles = Vehicle::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($normalizedPlates) {
                $query->whereIn('plate', $normalizedPlates)
                    ->orWhereIn('license_plate', $normalizedPlates);
            })
            ->get();

        $types = [];
        foreach ($vehicles as $vehicle) {
            foreach ([$vehicle->plate, $vehicle->license_plate] as $plate) {
                if ($plate && $vehicle->vehicle_type) {
                    $types[strtoupper(trim($plate))] = $vehicle->vehicle_type;
                }
            }
        }

        return $types;
    }

    private function co2EmissionFactor(?string $vehicleType): float
    {
        $type = strtolower(str_replace([' ', '-'], '_', $vehicleType ?? ''));
        $suvMultiplier = str_contains($type, 'suv') || str_contains($type, 'truck') || str_contains($type, 'van')
            ? 1.30
            : 1.00;

        $base = match (true) {
            str_contains($type, 'electric'), $type === 'ev' => 50.0,
            str_contains($type, 'hydrogen') => 95.0,
            str_contains($type, 'plugin_hybrid'), str_contains($type, 'plug_in_hybrid') => 95.0,
            str_contains($type, 'hybrid') => 130.0,
            str_contains($type, 'diesel') => 180.0,
            default => 210.0,
        };

        return $base * $suvMultiplier;
    }

    public function updateNotes(UpdateBookingNotesRequest $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('updateNotes', $booking);
        $user = $request->user();

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
}
