<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        $absences = Absence::where('user_id', $request->user()->id)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn($a) => array_merge($a->toArray(), ['type' => $a->absence_type]));

        return response()->json($absences);
    }

    public function store(Request $request)
    {
        // Accept both 'type' (Rust API parity) and 'absence_type'
        $request->merge([
            'absence_type' => $request->input('absence_type', $request->input('type')),
        ]);

        $request->validate([
            'absence_type' => 'required|in:homeoffice,vacation,sick,training,other',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date',
        ]);

        $absence = Absence::create(array_merge(
            $request->only(['absence_type', 'start_date', 'end_date', 'note']),
            ['user_id' => $request->user()->id, 'source' => $request->input('source', 'manual')]
        ));

        return response()->json(
            array_merge($absence->toArray(), ['type' => $absence->absence_type]),
            201
        );
    }

    public function update(Request $request, string $id)
    {
        $absence = Absence::where('user_id', $request->user()->id)->findOrFail($id);

        $request->merge([
            'absence_type' => $request->input('absence_type', $request->input('type')),
        ]);

        $absence->update($request->only(['absence_type', 'start_date', 'end_date', 'note']));

        return response()->json(array_merge($absence->toArray(), ['type' => $absence->absence_type]));
    }

    public function destroy(Request $request, string $id)
    {
        Absence::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function teamAbsences(Request $request)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to   = $request->to   ?? now()->endOfMonth()->toDateString();
        $absences = \App\Models\Absence::with('user')
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get();
        return response()->json($absences->map(function($a) {
            return array_merge($a->toArray(), [
                'user_name' => $a->user?->name,
                'username'  => $a->user?->username,
            ]);
        })->values());
    }

    public function getPattern(Request $request)
    {
        $pattern = \App\Models\Setting::get('homeoffice_pattern_' . $request->user()->id, null);
        return response()->json(['pattern' => $pattern ? json_decode($pattern, true) : []]);
    }

    public function setPattern(Request $request)
    {
        \App\Models\Setting::set('homeoffice_pattern_' . $request->user()->id, json_encode($request->input('pattern', [])));
        return response()->json(['message' => 'Pattern saved', 'pattern' => $request->input('pattern', [])]);
    }

    public function importIcal(Request $request)
    {
        // Accept either a file upload (multipart) or a raw 'ical' string body
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|mimes:ics,txt,calendar|max:2048']);
            $ical = $request->file('file')->get();
        } else {
            $request->validate(['ical' => 'required|string|max:1048576']);
            $ical = $request->input('ical');
        }
        $user = $request->user();
        $created = 0;

        // Simple iCal parser
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ical, $events);
        foreach ($events[1] as $event) {
            preg_match('/DTSTART[^:]*:(\S+)/', $event, $start);
            preg_match('/DTEND[^:]*:(\S+)/', $event, $end);
            preg_match('/SUMMARY:(.+)/m', $event, $summary);
            if (empty($start[1])) continue;
            $startDate = substr($start[1], 0, 8);
            $endDate   = $end[1] ? substr($end[1], 0, 8) : $startDate;
            $title     = trim($summary[1] ?? '');
            $type = str_contains(strtolower($title), 'vacation') || str_contains(strtolower($title), 'urlaub')
                ? 'vacation' : 'other';
            \App\Models\Absence::create([
                'user_id'      => $user->id,
                'absence_type' => $request->input('type', $type),
                'start_date'   => \Carbon\Carbon::createFromFormat('Ymd', $startDate)->toDateString(),
                'end_date'     => \Carbon\Carbon::createFromFormat('Ymd', $endDate)->toDateString(),
                'note'         => $title,
                'source'       => 'import',
            ]);
            $created++;
        }
        return response()->json(['created' => $created, 'message' => "$created absence(s) imported"]);
    }
}
