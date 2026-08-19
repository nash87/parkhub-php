<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportIcalRequest;
use App\Http\Requests\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\Setting;
use App\Services\BookingVisibility;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    /** Longest team-absence window a single request may ask for. */
    private const int MAX_TEAM_ABSENCE_WINDOW_DAYS = 366;

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

    /**
     * Absences across the team for a bounded window.
     *
     * This used to spread `$a->toArray()` into the response — the entire
     * absence row, including the free-text `note` — alongside the real name
     * and username, with `from`/`to` taken off the query string
     * unvalidated. `?from=1900-01-01&to=2999-12-31` returned every absence
     * record the instance had ever held, to any authenticated user.
     */
    public function teamAbsences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfMonth();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfMonth();

        // An unbounded window is a bulk export, not a team view.
        if ($from->diffInDays($to) > self::MAX_TEAM_ABSENCE_WINDOW_DAYS) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'WINDOW_TOO_LARGE',
                    'message' => 'Requested range exceeds '.self::MAX_TEAM_ABSENCE_WINDOW_DAYS.' days.',
                ],
                'meta' => null,
            ], 422);
        }

        $absences = Absence::with('user')
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get();

        $mode = BookingVisibility::mode();
        $isAdmin = $request->user()?->isAdmin() ?? false;

        // Explicit allow-list. The private note is never included, and the
        // reason is generalised for everyone but admins.
        return response()->json($absences->map(fn ($a) => [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'user_name' => BookingVisibility::label($a->user?->name, $a->user?->username, $mode),
            'absence_type' => BookingVisibility::absenceType($a->absence_type, $isAdmin),
            'start_date' => $a->start_date,
            'end_date' => $a->end_date,
        ])->values());
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

        // Simple iCal parser
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ical, $events);
        foreach ($events[1] as $event) {
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
                continue; // Skip events with unparseable dates
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
