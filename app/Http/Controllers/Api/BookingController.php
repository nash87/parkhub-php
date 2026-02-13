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

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date);
        }

        return response()->json($query->orderBy('start_time', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'required|uuid',
            'start_time' => 'required|date',
        ]);

        // Check slot availability
        $conflict = Booking::where('slot_id', $request->slot_id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where(function ($q) use ($request) {
                $endTime = $request->end_time ?? now()->addHours(8);
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $request->start_time);
            })->exists();

        if ($conflict) {
            return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot is already booked for this time'], 409);
        }

        $lot = ParkingLot::findOrFail($request->lot_id);
        $slot = ParkingSlot::findOrFail($request->slot_id);

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'lot_id' => $request->lot_id,
            'slot_id' => $request->slot_id,
            'booking_type' => $request->booking_type ?? 'einmalig',
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'vehicle_plate' => $request->license_plate ?? $request->vehicle_plate,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time ?? now()->addHours(8),
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
        $booking->update(['status' => 'cancelled']);

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
        $request->validate(['slot_id' => 'required|uuid']);

        $slot = ParkingSlot::findOrFail($request->slot_id);
        $today = now()->startOfDay();
        $endOfDay = now()->endOfDay();

        $conflict = Booking::where('slot_id', $request->slot_id)
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

        return response()->json($booking, 201);
    }

    public function guestBooking(Request $request)
    {
        $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'required|uuid',
            'guest_name' => 'required|string',
            'end_time' => 'required|date',
        ]);

        $guest = GuestBooking::create([
            'created_by' => $request->user()->id,
            'lot_id' => $request->lot_id,
            'slot_id' => $request->slot_id,
            'guest_name' => $request->guest_name,
            'guest_code' => strtoupper(Str::random(8)),
            'start_time' => $request->start_time ?? now(),
            'end_time' => $request->end_time,
            'vehicle_plate' => $request->vehicle_plate,
            'status' => 'confirmed',
        ]);

        // Also create a regular booking for slot blocking
        Booking::create([
            'user_id' => $request->user()->id,
            'lot_id' => $request->lot_id,
            'slot_id' => $request->slot_id,
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
}
