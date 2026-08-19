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
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\Webhook;
use App\Services\Booking\BookingCreationService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        // A booking that is already in a terminal state has nothing left to
        // cancel. Capturing this before the write is what makes the refund
        // below idempotent: the endpoint used to credit the caller on every
        // call, so repeating DELETE minted credits.
        $wasLive = in_array($booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE], true);

        // Mark as cancelled instead of hard-deleting — preserves audit trail
        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        if ($wasLive) {
            $this->refundCancelledBooking($request->user(), $booking);
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

    /**
     * Quick-book: pick a slot and book it for the rest of the day.
     *
     * This used to re-implement creation inline, which meant it skipped
     * every rule the primary creator enforces — the active-booking cap,
     * per-day quota, duration limits, advance-days, operating hours,
     * vehicle requirements, pricing, and the credit ledger. Rows landed
     * with null prices and no debit, and combined with the cancellation
     * refund that was a way to mint credits.
     *
     * It now resolves a slot and a window, then delegates to the same
     * BookingCreationService the standard route uses. The convenience is
     * the slot auto-pick; it was never meant to be a second set of rules.
     */
    public function quickBook(Request $request, BookingCreationService $service)
    {
        [$startTime, $endTime] = $this->quickBookWindow($request->input('date'));

        if ($request->has('slot_id')) {
            $slot = ParkingSlot::findOrFail($request->slot_id);
        } elseif ($request->has('lot_id')) {
            // Auto-pick using the window we are actually going to book, not
            // a different one — the previous code selected against the
            // requested day and then persisted `start_time => now()`, so
            // quick-booking tomorrow produced a booking that started
            // immediately and occupied today as well.
            $taken = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
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

        $result = $service->create([
            'lot_id' => $slot->lot_id,
            'slot_id' => $slot->id,
            'booking_type' => 'einmalig',
            'start_time' => $startTime->toDateTimeString(),
            'end_time' => $endTime->toDateTimeString(),
            'license_plate' => $request->input('license_plate'),
        ], $request->user());

        if ($result->isOk()) {
            // 200 rather than 201: this route has always answered 200 and
            // clients parse it that way.
            return BookingResource::make($result->booking)->response()->setStatusCode(200);
        }

        if ($result->status === 409) {
            return response()->json([
                'error' => $result->errorCode === 'SLOT_UNAVAILABLE' ? 'SLOT_UNAVAILABLE' : 'NO_SLOTS',
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

    /**
     * The window a quick booking occupies: from now (or the start of a
     * future day) until that day ends.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    /**
     * Return the credit a cancelled booking actually cost, at most once.
     *
     * Two conditions, both necessary. A refund requires *proof of payment*
     * — a matching `deduction` row — because bookings can be created by
     * paths that never touched the ledger, and refunding those is minting.
     * And it must happen at most once per booking; the unique index on
     * `credit_transactions (booking_id, type)` is the hard backstop, so the
     * ledger row is written *before* the balance moves and a duplicate is
     * rejected by the database rather than by a check that can race.
     */
    private function refundCancelledBooking(User $user, Booking $booking): void
    {
        if (Setting::get('credits_enabled', 'false') !== 'true' || $user->isAdmin()) {
            return;
        }

        $paid = CreditTransaction::where('booking_id', $booking->id)
            ->where('type', 'deduction')
            ->exists();

        if (! $paid) {
            return;
        }

        $creditsPerBooking = (int) Setting::get('credits_per_booking', '1');

        try {
            DB::transaction(function () use ($user, $booking, $creditsPerBooking) {
                CreditTransaction::create([
                    'user_id' => $user->id,
                    'booking_id' => $booking->id,
                    'amount' => $creditsPerBooking,
                    'type' => 'refund',
                    'description' => 'Cancelled booking #'.substr($booking->id, 0, 8),
                ]);

                $user->increment('credits_balance', $creditsPerBooking);
            });
        } catch (UniqueConstraintViolationException) {
            // Already refunded. Nothing to do — and importantly the balance
            // was not touched, because the ledger row is written first.
        }
    }

    private function quickBookWindow(?string $date): array
    {
        if ($date === null || $date === '') {
            return [now(), now()->endOfDay()];
        }

        $day = now()->parse($date);
        $start = $day->isToday() ? now() : $day->copy()->startOfDay();

        return [$start, $day->copy()->endOfDay()];
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
