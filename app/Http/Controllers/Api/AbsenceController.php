<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Absence::where('user_id', $request->user()->id)->orderBy('start_date', 'desc')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'absence_type' => 'required|in:homeoffice,vacation,sick,training,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $absence = Absence::create(array_merge(
            $request->only(['absence_type', 'start_date', 'end_date', 'note']),
            ['user_id' => $request->user()->id, 'source' => $request->source ?? 'manual']
        ));

        return response()->json($absence, 201);
    }

    public function update(Request $request, string $id)
    {
        $absence = Absence::where('user_id', $request->user()->id)->findOrFail($id);
        $absence->update($request->only(['absence_type', 'start_date', 'end_date', 'note']));
        return response()->json($absence);
    }

    public function destroy(Request $request, string $id)
    {
        Absence::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
