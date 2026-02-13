<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(string $lotId)
    {
        return response()->json(Zone::where('lot_id', $lotId)->get());
    }

    public function store(Request $request, string $lotId)
    {
        $request->validate(['name' => 'required|string']);
        $zone = Zone::create(array_merge($request->only(['name', 'color', 'description']), ['lot_id' => $lotId]));
        return response()->json($zone, 201);
    }

    public function update(Request $request, string $lotId, string $id)
    {
        $zone = Zone::where('lot_id', $lotId)->findOrFail($id);
        $zone->update($request->only(['name', 'color', 'description']));
        return response()->json($zone);
    }

    public function destroy(string $lotId, string $id)
    {
        Zone::where('lot_id', $lotId)->findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
