<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Vehicle::where('user_id', $request->user()->id)->get());
    }

    public function store(Request $request)
    {
        $request->validate(['plate' => 'required|string']);
        $vehicle = Vehicle::create(array_merge(
            $request->only(['plate', 'make', 'model', 'color', 'is_default']),
            ['user_id' => $request->user()->id]
        ));
        return response()->json($vehicle, 201);
    }

    public function update(Request $request, string $id)
    {
        $vehicle = Vehicle::where('user_id', $request->user()->id)->findOrFail($id);
        $vehicle->update($request->only(['plate', 'make', 'model', 'color', 'is_default']));
        return response()->json($vehicle);
    }

    public function destroy(Request $request, string $id)
    {
        Vehicle::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
