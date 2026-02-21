<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Booking;
use Illuminate\Http\Request;

class LotController extends Controller
{
    public function index()
    {
        $lots = ParkingLot::all()->map(function ($lot) {
            $lot->available_slots = $this->calculateAvailable($lot);
            return $lot;
        });
        return response()->json($lots);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $lot = ParkingLot::create($request->only(['name', 'address', 'total_slots', 'layout', 'status']));
        return response()->json($lot, 201);
    }

    public function show(string $id)
    {
        $lot = ParkingLot::findOrFail($id);
        $lot->available_slots = $this->calculateAvailable($lot);

        // Auto-generate layout from slots if not set (Rust frontend requires layout)
        if (!$lot->layout) {
            $slots = $lot->slots()->get();
            $activeBookings = Booking::where('lot_id', $id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->get()
                ->keyBy('slot_id');

            $slotConfigs = $slots->map(function ($slot) use ($activeBookings) {
                $booking = $activeBookings->get($slot->id);
                return [
                    'id' => $slot->id,
                    'number' => $slot->slot_number,
                    'status' => $booking ? 'occupied' : 'available',
                    'vehiclePlate' => $booking?->vehicle_plate,
                    'bookedBy' => $booking?->user?->name,
                ];
            })->values()->toArray();

            // Split into rows of max 10
            $chunks = array_chunk($slotConfigs, 10);
            $rows = [];
            foreach ($chunks as $i => $chunk) {
                $rows[] = [
                    'id' => 'row-' . ($i + 1),
                    'side' => $i % 2 === 0 ? 'top' : 'bottom',
                    'slots' => $chunk,
                    'label' => 'Row ' . ($i + 1),
                ];
            }
            $lot->layout = ['rows' => $rows, 'roadLabel' => 'Main Road'];
        }

        return response()->json($lot);
    }

    public function update(Request $request, string $id)
    {
        $lot = ParkingLot::findOrFail($id);
        $lot->update($request->only(['name', 'address', 'total_slots', 'layout', 'status']));
        return response()->json($lot);
    }

    public function destroy(string $id)
    {
        ParkingLot::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function slots(string $id)
    {
        $lot = ParkingLot::findOrFail($id);
        $slots = $lot->slots()->get()->map(function ($slot) {
            $activeBooking = Booking::where('slot_id', $slot->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->first();

            $slot->current_booking = $activeBooking ? [
                'booking_id' => $activeBooking->id,
                'user_id' => $activeBooking->user_id,
                'license_plate' => $activeBooking->vehicle_plate,
                'start_time' => $activeBooking->start_time->toISOString(),
                'end_time' => $activeBooking->end_time->toISOString(),
            ] : null;

            return $slot;
        });
        return response()->json($slots);
    }

    public function occupancy(string $id)
    {
        $lot = ParkingLot::findOrFail($id);
        $totalSlots = $lot->slots()->count();
        $occupied = Booking::where('lot_id', $id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->count();

        return response()->json([
            'lot_id' => $id,
            'lot_name' => $lot->name,
            'total' => $totalSlots,
            'occupied' => $occupied,
            'available' => $totalSlots - $occupied,
            'percentage' => $totalSlots > 0 ? round(($occupied / $totalSlots) * 100) : 0,
        ]);
    }

    private function calculateAvailable(ParkingLot $lot): int
    {
        $totalSlots = $lot->slots()->count();
        $occupied = Booking::where('lot_id', $lot->id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->count();
        return max(0, $totalSlots - $occupied);
    }

    public function qrCode(\Illuminate\Http\Request $request, string $id)
    {
        $lot = \App\Models\ParkingLot::findOrFail($id);
        $data = urlencode(url('/') . '/book?lot=' . $id);
        // Return QR code as SVG via free API
        return response()->json([
            'lot_id'  => $id,
            'lot_name'=> $lot->name,
            'qr_url'  => "https://api.qrserver.com/v1/create-qr-code/?data={$data}&size=256x256",
            'data'    => urldecode($data),
        ]);
    }

    public function slotQrCode(\Illuminate\Http\Request $request, string $lotId, string $slotId)
    {
        $slot = \App\Models\ParkingSlot::where('lot_id', $lotId)->findOrFail($slotId);
        $data = urlencode(url('/') . '/book?lot=' . $lotId . '&slot=' . $slotId);
        return response()->json([
            'lot_id'     => $lotId,
            'slot_id'    => $slotId,
            'slot_number'=> $slot->slot_number,
            'qr_url'     => "https://api.qrserver.com/v1/create-qr-code/?data={$data}&size=256x256",
            'data'       => urldecode($data),
        ]);
    }
}
