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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function preferences(Request $request)
    {
        return response()->json($request->user()->preferences ?? []);
    }

    public function updatePreferences(Request $request)
    {
        $request->validate([
            'language'               => 'sometimes|string|max:10',
            'theme'                  => 'sometimes|in:light,dark,system',
            'notifications_enabled'  => 'sometimes|boolean',
            'email_notifications'    => 'sometimes|boolean',
            'push_notifications'     => 'sometimes|boolean',
            'show_plate_in_calendar' => 'sometimes|boolean',
            'default_lot_id'         => 'sometimes|nullable|uuid',
            'locale'                 => 'sometimes|string|max:10',
            'timezone'               => 'sometimes|string|max:64',
        ]);

        $allowed = [
            'language', 'theme', 'notifications_enabled', 'email_notifications',
            'push_notifications', 'show_plate_in_calendar', 'default_lot_id',
            'locale', 'timezone',
        ];

        $user  = $request->user();
        $prefs = array_merge($user->preferences ?? [], $request->only($allowed));
        $user->update(['preferences' => $prefs]);
        return response()->json($prefs);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $now    = now();

        // Calculate average booking duration in minutes
        $bookingsWithDuration = Booking::where('user_id', $userId)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get(['start_time', 'end_time']);

        $avgMinutes = 0;
        if ($bookingsWithDuration->count() > 0) {
            $totalMinutes = $bookingsWithDuration->sum(function ($b) {
                return (strtotime($b->end_time) - strtotime($b->start_time)) / 60;
            });
            $avgMinutes = (int) round($totalMinutes / $bookingsWithDuration->count());
        }

        return response()->json([
            'total_bookings'             => Booking::where('user_id', $userId)->count(),
            'bookings_this_month'        => Booking::where('user_id', $userId)
                ->whereMonth('start_time', $now->month)
                ->whereYear('start_time', $now->year)->count(),
            'homeoffice_days_this_month' => Absence::where('user_id', $userId)
                ->where('absence_type', 'homeoffice')
                ->whereMonth('start_date', $now->month)
                ->whereYear('start_date', $now->year)->count(),
            'avg_duration_minutes'       => $avgMinutes,
            'favorite_slot'              => Booking::where('user_id', $userId)
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
            ->whereIn('status', ['active', 'confirmed'])
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

    public function markAllNotificationsRead(\Illuminate\Http\Request $request)
    {
        Notification::where('user_id', $request->user()->id)->update(['read' => true]);
        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function pushUnsubscribe(\Illuminate\Http\Request $request)
    {
        \App\Models\PushSubscription::where('user_id', $request->user()->id)->delete();
        return response()->json(['message' => 'Unsubscribed from push notifications']);
    }

    /**
     * GDPR Art. 17 — Right to Erasure.
     * Anonymizes all personal data while preserving anonymized booking records for audit/accounting.
     * Unlike deleteAccount() which CASCADE-deletes everything, this keeps booking records
     * with PII replaced by placeholder values (required for German tax law — 7-year retention).
     */
    public function anonymizeAccount(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'INVALID_PASSWORD', 'message' => 'Password confirmation failed'], 403);
        }

        $anonymousId = 'deleted-' . substr($user->id, 0, 8);

        // Anonymize all bookings (keep for accounting, strip PII)
        \App\Models\Booking::where('user_id', $user->id)->update([
            'vehicle_plate' => '[GELÖSCHT]',
            'notes'         => null,
        ]);

        // Delete truly personal data tables
        Absence::where('user_id', $user->id)->delete();

        // Delete vehicle photos before deleting vehicle records
        $vehicles = Vehicle::where('user_id', $user->id)->get();
        foreach ($vehicles as $vehicle) {
            if (!empty($vehicle->photo_path)) {
                Storage::delete($vehicle->photo_path);
            }
        }
        Vehicle::where('user_id', $user->id)->delete();

        \App\Models\Favorite::where('user_id', $user->id)->delete();
        \App\Models\Notification::where('user_id', $user->id)->delete();
        \App\Models\PushSubscription::where('user_id', $user->id)->delete();

        // Anonymize audit logs — strip IP and identifying details
        DB::table('audit_logs')->where('user_id', $user->id)->update([
            'username'   => 'deleted-user',
            'ip_address' => '0.0.0.0',
            'details'    => null,
        ]);

        // Anonymize guest bookings created by this user
        DB::table('guest_bookings')->where('created_by', $user->id)->update([
            'guest_name' => 'Anonymous',
        ]);

        // Audit log before anonymizing (records the action for compliance)
        \App\Models\AuditLog::create([
            'user_id'    => $user->id,
            'username'   => $user->username,
            'action'     => 'gdpr_erasure',
            'details'    => ['reason' => $request->input('reason', 'User request')],
            'ip_address' => $request->ip(),
        ]);

        // Build the response BEFORE invalidating tokens so the session is still valid when serialized
        $response = response()->json(['success' => true, 'data' => ['message' => 'Account deleted successfully'], 'error' => null, 'meta' => null], 200);

        // Anonymize the user record itself (don't hard-delete — bookings still reference it)
        $user->preferences = null;
        $user->update([
            'name'        => '[Gelöschter Nutzer]',
            'email'       => $anonymousId . '@deleted.invalid',
            'username'    => $anonymousId,
            'password'    => \Illuminate\Support\Str::random(64), // unguessable
            'phone'       => null,
            'picture'     => null,
            'department'  => null,
            'preferences' => null,
            'is_active'   => false,
        ]);

        // Invalidate all tokens AFTER building the response
        $user->tokens()->delete();

        return $response;
    }
}
