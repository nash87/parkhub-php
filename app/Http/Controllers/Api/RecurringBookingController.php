<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecurringBooking;
use Illuminate\Http\Request;

class RecurringBookingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            RecurringBooking::where('user_id', $request->user()->id)->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'required|uuid',
            'days_of_week' => 'required|array',
            'start_date' => 'required|date',
        ]);

        $recurring = RecurringBooking::create(array_merge(
            $request->only(['lot_id', 'slot_id', 'days_of_week', 'start_date', 'end_date', 'start_time', 'end_time', 'vehicle_plate']),
            ['user_id' => $request->user()->id, 'active' => true]
        ));

        return response()->json($recurring, 201);
    }

    public function update(Request $request, string $id)
    {
        $recurring = RecurringBooking::where('user_id', $request->user()->id)->findOrFail($id);
        $recurring->update($request->only(['days_of_week', 'start_date', 'end_date', 'start_time', 'end_time', 'vehicle_plate', 'active']));
        return response()->json($recurring);
    }

    public function destroy(Request $request, string $id)
    {
        RecurringBooking::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
