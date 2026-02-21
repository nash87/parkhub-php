<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function preferences(Request $request)
    {
        return response()->json($request->user()->preferences ?? []);
    }

    public function updatePreferences(Request $request)
    {
        $user  = $request->user();
        $prefs = array_merge($user->preferences ?? [], $request->all());
        $user->update(['preferences' => $prefs]);
        return response()->json($prefs);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $now    = now();

        return response()->json([
            'total_bookings'  => Booking::where('user_id', $userId)->count(),
            'this_month'      => Booking::where('user_id', $userId)
                ->whereMonth('start_time', $now->month)
                ->whereYear('start_time', $now->year)->count(),
            'homeoffice_days' => Absence::where('user_id', $userId)
                ->where('absence_type', 'homeoffice')->count(),
            'favorite_slot'   => Booking::where('user_id', $userId)
                ->selectRaw('slot_number, COUNT(*) as cnt')
                ->groupBy('slot_number')
                ->orderByDesc('cnt')
                ->first()?->slot_number,
        ]);
    }

    public function favorites(Request $request)
    {
        return response()->json(
            Favorite::where('user_id', $request->user()->id)->with('slot')->get()
        );
    }

    public function addFavorite(Request $request)
    {
        $request->validate(['slot_id' => 'required|uuid']);
        $fav = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'slot_id' => $request->slot_id,
        ]);
        return response()->json($fav, 201);
    }

    public function removeFavorite(Request $request, string $slotId)
    {
        Favorite::where('user_id', $request->user()->id)->where('slot_id', $slotId)->delete();
        return response()->json(['message' => 'Removed']);
    }

    public function notifications(Request $request)
    {
        return response()->json(
            Notification::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
        );
    }

    public function markNotificationRead(Request $request, string $id)
    {
        $notif = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notif->update(['read' => true]);
        return response()->json($notif);
    }

    // iCal export — bookings as calendar feed
    public function calendarExport(Request $request)
    {
        $user     = $request->user();
        $bookings = Booking::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('start_time')
            ->get();

        $orgName  = Setting::get('company_name', 'ParkHub');
        $prodId   = '-//ParkHub//Calendar//EN';
        $now      = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            "PRODID:{$prodId}",
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:{$orgName} Parking",
        ];

        foreach ($bookings as $b) {
            $uid   = $b->id . '@parkhub';
            $start = gmdate('Ymd\THis\Z', strtotime($b->start_time));
            $end   = $b->end_time
                ? gmdate('Ymd\THis\Z', strtotime($b->end_time))
                : gmdate('Ymd\THis\Z', strtotime($b->start_time) + 3600);

            $summary  = "Parking: {$b->slot_number} ({$b->lot_name})";
            $location = $b->lot_name;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$now}";
            $lines[] = "DTSTART:{$start}";
            $lines[] = "DTEND:{$end}";
            $lines[] = "SUMMARY:{$summary}";
            $lines[] = "LOCATION:{$location}";
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $ical = implode("\r\n", $lines) . "\r\n";

        return response($ical, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="parkhub.ics"',
        ]);
    }

    // GDPR data export — everything about this user
    public function exportData(Request $request)
    {
        $user = $request->user();

        $data = [
            'exported_at' => now()->toISOString(),
            'profile'     => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'department' => $user->department,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'bookings'    => Booking::where('user_id', $user->id)
                ->orderBy('start_time', 'desc')
                ->get(['id', 'lot_name', 'slot_number', 'vehicle_plate', 'start_time', 'end_time', 'status', 'booking_type']),
            'absences'    => Absence::where('user_id', $user->id)
                ->orderBy('start_date', 'desc')
                ->get(['id', 'absence_type', 'start_date', 'end_date', 'note']),
            'vehicles'    => Vehicle::where('user_id', $user->id)
                ->get(['id', 'plate', 'make', 'model', 'color', 'is_default']),
            'preferences' => $user->preferences ?? [],
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="my-parkhub-data.json"',
        ]);
    }
}
