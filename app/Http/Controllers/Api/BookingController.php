<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingSlot;
use App\Models\ParkingLot;
use App\Models\GuestBooking;
use App\Models\BookingNote;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::where('user_id', $request->user()->id);
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('from_date')) $query->where('start_time', '>=', $request->from_date);
        if ($request->has('to_date')) $query->where('end_time', '<=', $request->to_date);
        return response()->json($query->orderBy('start_time', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'nullable|uuid',
            'start_time' => 'required|date',
        ]);

        $endTime = $request->end_time ?? now()->addHours(8)->toDateTimeString();

        // Auto-assign slot if not provided
        $slotId = $request->slot_id;
        if (!$slotId) {
            $bookedSlotIds = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $request->start_time)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->whereNotIn('id', $bookedSlotIds)
                ->first();
            if (!$slot) {
                return response()->json(['error' => 'NO_SLOTS_AVAILABLE', 'message' => 'No available slots'], 409);
            }
            $slotId = $slot->id;
        }

        // Check conflict
        $conflict = Booking::where('slot_id', $slotId)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $request->start_time)
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot is already booked'], 409);
        }

        $lot = ParkingLot::findOrFail($request->lot_id);
        $slot = ParkingSlot::findOrFail($slotId);

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
            'status' => 'confirmed',
            'notes' => $request->notes,
            'recurrence' => $request->recurrence,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_created',
            'details' => ['booking_id' => $booking->id, 'slot' => $slot->slot_number],
        ]);

        return response()->json($booking, 201);
    }

    public function destroy(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        $booking->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_cancelled',
            'details' => ['booking_id' => $id],
        ]);

        return response()->json(['message' => 'Booking cancelled']);
    }

    public function quickBook(Request $request)
    {
        // Accepts either slot_id directly, or lot_id+date to auto-pick
        if ($request->has('slot_id')) {
            $slot = ParkingSlot::findOrFail($request->slot_id);
        } elseif ($request->has('lot_id')) {
            $date       = $request->date ? now()->parse($request->date) : now();
            $dayStart   = $date->copy()->startOfDay();
            $dayEnd     = $date->copy()->endOfDay();
            $taken      = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<', $dayEnd)
                ->where('end_time', '>', $dayStart)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->where('status', 'available')
                ->whereNotIn('id', $taken)
                ->first();
            if (!$slot) {
                return response()->json(['error' => 'NO_SLOTS', 'message' => 'No slots available'], 409);
            }
        } else {
            return response()->json(['error' => 'INVALID_REQUEST', 'message' => 'Provide slot_id or lot_id'], 422);
        }

        $startTime = $request->date ? now()->parse($request->date)->startOfDay() : now();
        $endTime   = $request->date ? now()->parse($request->date)->endOfDay()   : now()->endOfDay();

        $today      = $startTime;
        $endOfDay   = $endTime;
        $request->slot_id = $slot->id;

        $conflict = Booking::where('slot_id', $slot->id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<', $endOfDay)
            ->where('end_time', '>', $today)
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot taken'], 409);
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
            'status' => 'confirmed',
        ]);

        return response()->json($booking, 200);
    }

    public function guestBooking(Request $request)
    {
        $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'nullable|uuid',
            'guest_name' => 'required|string',
            'end_time' => 'required|date',
        ]);

        $slotId = $request->slot_id;
        if (!$slotId) {
            $startTime = $request->start_time ?? now();
            $bookedSlotIds = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $startTime)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->whereNotIn('id', $bookedSlotIds)
                ->first();
            if (!$slot) {
                return response()->json(['error' => 'NO_SLOTS_AVAILABLE'], 409);
            }
            $slotId = $slot->id;
        }

        $guest = GuestBooking::create([
            'created_by' => $request->user()->id,
            'lot_id' => $request->lot_id,
            'slot_id' => $slotId,
            'guest_name' => $request->guest_name,
            'guest_code' => strtoupper(Str::random(8)),
            'start_time' => $request->start_time ?? now(),
            'end_time' => $request->end_time,
            'vehicle_plate' => $request->vehicle_plate,
            'status' => 'confirmed',
        ]);

        Booking::create([
            'user_id' => $request->user()->id,
            'lot_id' => $request->lot_id,
            'slot_id' => $slotId,
            'booking_type' => 'einmalig',
            'vehicle_plate' => $request->vehicle_plate,
            'start_time' => $request->start_time ?? now(),
            'end_time' => $request->end_time,
            'status' => 'confirmed',
            'notes' => 'Guest: ' . $request->guest_name,
        ]);

        return response()->json($guest, 201);
    }

    public function swap(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|uuid',
            'target_slot_id' => 'required|uuid',
        ]);

        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($request->booking_id);

        $conflict = Booking::where('slot_id', $request->target_slot_id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<', $booking->end_time)
            ->where('end_time', '>', $booking->start_time)
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'SLOT_UNAVAILABLE'], 409);
        }

        $newSlot = ParkingSlot::findOrFail($request->target_slot_id);
        $booking->update([
            'slot_id' => $request->target_slot_id,
            'slot_number' => $newSlot->slot_number,
        ]);

        return response()->json($booking->fresh());
    }

    public function updateNotes(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $booking->update(['notes' => $request->notes]);

        if ($request->note) {
            BookingNote::create([
                'booking_id' => $id,
                'user_id' => $request->user()->id,
                'note' => $request->note,
            ]);
        }

        return response()->json($booking->fresh());
    }

    public function checkin(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        $booking->update(['checked_in_at' => now(), 'status' => 'active']);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_checkin',
            'details' => ['booking_id' => $id],
        ]);
        return response()->json($booking->fresh());
    }

    public function update(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->only(['notes', 'vehicle_plate', 'status']);
        $booking->update($data);
        return response()->json($booking->fresh());
    }

    public function calendarEvents(Request $request)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateTimeString();
        $to   = $request->to   ?? now()->endOfMonth()->toDateTimeString();
        $bookings = Booking::where('user_id', $request->user()->id)
            ->where('start_time', '>=', $from)
            ->where('end_time', '<=', $to)
            ->get();
        $events = $bookings->map(function($b) {
            return [
                'id'    => $b->id,
                'title' => $b->lot_name . ' — ' . $b->slot_number,
                'start' => $b->start_time,
                'end'   => $b->end_time,
                'type'  => 'booking',
                'status'=> $b->status,
            ];
        });
        return response()->json($events->values());
    }

    public function createSwapRequest(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        $target  = Booking::findOrFail($request->target_booking_id);
        return response()->json([
            'id'             => \Illuminate\Support\Str::uuid(),
            'booking_id'     => $booking->id,
            'target_booking_id' => $target->id,
            'status'         => 'pending',
            'created_at'     => now()->toISOString(),
        ], 201);
    }

    public function respondSwapRequest(Request $request, string $id)
    {
        // Simplified swap implementation
        $accept = $request->input('accept', false);
        return response()->json(['id' => $id, 'status' => $accept ? 'accepted' : 'declined']);
    }
}
