<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingSlot;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function store(Request $request, string $lotId)
    {
        $request->validate(['slot_number' => 'required|string']);
        $slot = ParkingSlot::create(array_merge(
            $request->only(['slot_number', 'status', 'reserved_for_department', 'zone_id']),
            ['lot_id' => $lotId]
        ));
        return response()->json($slot, 201);
    }

    public function update(Request $request, string $lotId, string $slotId)
    {
        $slot = ParkingSlot::where('lot_id', $lotId)->findOrFail($slotId);
        $slot->update($request->only(['slot_number', 'status', 'reserved_for_department', 'zone_id']));
        return response()->json($slot);
    }

    public function destroy(string $lotId, string $slotId)
    {
        ParkingSlot::where('lot_id', $lotId)->findOrFail($slotId)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
