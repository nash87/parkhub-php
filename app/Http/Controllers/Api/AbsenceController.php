<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportIcalRequest;
use App\Http\Requests\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $absences = Absence::where('user_id', $request->user()->id)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn ($a) => array_merge($a->toArray(), ['type' => $a->absence_type]));

        return response()->json($absences);
    }

    public function store(StoreAbsenceRequest $request): JsonResponse
    {
        $absence = Absence::create(array_merge(
            $request->only(['absence_type', 'start_date', 'end_date', 'note']),
            ['user_id' => $request->user()->id, 'source' => $request->input('source', 'manual')]
        ));

        return response()->json(
            array_merge($absence->toArray(), ['type' => $absence->absence_type]),
            201
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $absence = Absence::findOrFail($id);
        $this->authorize('update', $absence);

        $request->merge([
            'absence_type' => $request->input('absence_type', $request->input('type')),
        ]);

        $absence->update($request->only(['absence_type', 'start_date', 'end_date', 'note']));

        return response()->json(array_merge($absence->toArray(), ['type' => $absence->absence_type]));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $absence = Absence::findOrFail($id);
        $this->authorize('delete', $absence);
        $absence->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function teamAbsences(Request $request): JsonResponse
    {
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to = $request->to ?? now()->endOfMonth()->toDateString();
        $absences = Absence::with('user')
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get();

        return response()->json($absences->map(function ($a) {
            return array_merge($a->toArray(), [
                'user_name' => $a->user?->name,
                'username' => $a->user?->username,
            ]);
        })->values());
    }

    public function getPattern(Request $request): JsonResponse
    {
        // The frontend expects an array of AbsencePattern objects
        // ([{absence_type, weekdays}, ...]) so it can .find() the homeoffice
        // entry. Returning {pattern: [...]} instead — as we used to —
        // crashed the Absences page with "j.find is not a function".
        $raw = Setting::get('homeoffice_pattern_'.$request->user()->id, null);
        $weekdays = $raw ? json_decode($raw, true) : [];

        return response()->json(
            $weekdays ? [['absence_type' => 'homeoffice', 'weekdays' => $weekdays]] : []
        );
    }

    public function setPattern(Request $request): JsonResponse
    {
        // Accept the canonical {absence_type, weekdays} payload the Rust
        // backend uses. Legacy clients sending {pattern: [...]} still work
        // because we fall back to the old key.
        $weekdays = $request->input('weekdays', $request->input('pattern', []));
        Setting::set('homeoffice_pattern_'.$request->user()->id, json_encode($weekdays));

        return response()->json([
            'absence_type' => 'homeoffice',
            'weekdays' => $weekdays,
        ]);
    }

    public function importIcal(ImportIcalRequest $request): JsonResponse
    {
        // Accept either a file upload (multipart) or a raw 'ical' string body.
        // ImportIcalRequest applies the right rules based on which shape arrived.
        if ($request->hasFile('file')) {
            $ical = $request->file('file')->get();
        } else {
            $ical = $request->input('ical');
        }
        $user = $request->user();
        $created = 0;

        // Simple iCal parser. Audit M-2: cap event count at 1000/request to
        // bound backtracking risk in the lazy `(.*?)` quantifier and to
        // prevent a single import call from creating millions of rows.
        // ImportIcalRequest enforces the matching 256 KiB payload cap.
        // Long-term, replace with sabre/vobject for proper streaming parse;
        // tracked as a follow-up since it adds a runtime dep.
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ical, $events);
        $maxEventsPerImport = 1000;
        foreach ($events[1] as $event) {
            if ($created >= $maxEventsPerImport) {
                break;
            }
            preg_match('/DTSTART[^:]*:(\S+)/', $event, $start);
            preg_match('/DTEND[^:]*:(\S+)/', $event, $end);
            preg_match('/SUMMARY:(.+)/m', $event, $summary);
            if (empty($start[1])) {
                continue;
            }
            $startDate = substr($start[1], 0, 8);
            $endDate = ! empty($end[1]) ? substr($end[1], 0, 8) : $startDate;
            $title = mb_substr(trim($summary[1] ?? ''), 0, 255);
            $type = str_contains(strtolower($title), 'vacation') || str_contains(strtolower($title), 'urlaub')
                ? 'vacation' : 'other';

            try {
                $parsedStart = Carbon::createFromFormat('Ymd', $startDate);
                $parsedEnd = Carbon::createFromFormat('Ymd', $endDate);
            } catch (\Exception $e) {
                continue; // Skip events with unparsable dates
            }

            $allowedTypes = ['homeoffice', 'vacation', 'sick', 'training', 'other'];
            $requestedType = $request->input('type');
            $resolvedType = ($requestedType && in_array($requestedType, $allowedTypes))
                ? $requestedType
                : $type;

            Absence::create([
                'user_id' => $user->id,
                'absence_type' => $resolvedType,
                'start_date' => $parsedStart->toDateString(),
                'end_date' => $parsedEnd->toDateString(),
                'note' => $title,
                'source' => 'import',
            ]);
            $created++;
        }

        return response()->json(['created' => $created, 'message' => "$created absence(s) imported"]);
    }
}
