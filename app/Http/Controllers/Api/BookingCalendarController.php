<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingVisibility;
use Illuminate\Http\Request;

/**
 * Calendar views backed by Booking data: JSON events + iCal feed.
 *
 * Split out of BookingController (T-1743). Method bodies are moved
 * verbatim — behavioural refactors happen in a follow-up pass.
 */
class BookingCalendarController extends Controller
{
    /**
     * Calendar events for the requested window.
     *
     * `scope=all` widens the view to every user's bookings so people can
     * see when a slot is actually free (#571) instead of guessing. Other
     * users' entries are reduced to occupancy: slot, time and an owner
     * label governed by the `booking_visibility` setting. Vehicle plate and
     * notes are never included for a booking the caller does not own.
     */
    public function calendarEvents(Request $request)
    {
        // The SPA sends `start`/`end`; earlier clients and the iCal paths
        // use `from`/`to`. The controller previously read only `from`/`to`,
        // so every month the SPA navigated to silently returned the current
        // month instead. Accept both spellings.
        $from = $request->query('start') ?? $request->query('from') ?? now()->startOfMonth()->toDateTimeString();
        $to = $request->query('end') ?? $request->query('to') ?? now()->endOfMonth()->toDateTimeString();

        $userId = $request->user()->id;
        $shared = $request->query('scope') === 'all';

        $query = Booking::query()
            ->whereIn('status', [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_ACTIVE,
                Booking::STATUS_COMPLETED,
            ])
            // Overlap, not containment. A booking that starts before the
            // window and ends after it occupies every day in view, yet the
            // previous `start >= from AND end <= to` filter dropped it.
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from);

        if (! $shared) {
            $query->where('user_id', $userId);
        }

        $bookings = $query
            ->select(['id', 'user_id', 'lot_name', 'slot_number', 'start_time', 'end_time', 'status'])
            ->orderBy('start_time')
            ->get();

        $mode = BookingVisibility::mode();

        // Resolve owner names in one query rather than per booking. A user
        // who has since been soft-deleted simply resolves to null and falls
        // back to the anonymous label, which is also the right disclosure.
        $owners = $shared
            ? User::query()
                ->whereIn('id', $bookings->pluck('user_id')->unique()->all())
                ->get(['id', 'name', 'username'])
                ->keyBy('id')
            : collect();

        $events = $bookings->map(function ($b) use ($userId, $mode, $owners) {
            $mine = $b->user_id === $userId;
            $owner = $owners->get($b->user_id);

            return [
                'id' => $b->id,
                'title' => $b->lot_name.' — '.$b->slot_number,
                'start' => $b->start_time,
                'end' => $b->end_time,
                'type' => 'booking',
                'status' => $b->status,
                'mine' => $mine,
                'slot_number' => $b->slot_number,
                'owner' => $mine
                    ? null
                    : BookingVisibility::label($owner?->name, $owner?->username, $mode, 'Occupied'),
            ];
        });

        return response()->json($events->values());
    }

    /**
     * iCal feed — returns all active bookings as .ics for calendar subscription.
     */
    public function ical(Request $request)
    {
        $user = $request->user();
        $bookings = Booking::where('user_id', $user->id)
            ->whereIn('status', [Booking::STATUS_ACTIVE, Booking::STATUS_CONFIRMED])
            ->whereNotNull('start_time')
            ->get();

        $orgName = Setting::get('company_name', 'ParkHub');
        $prodId = '-//ParkHub//Bookings//EN';
        $now = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            "PRODID:{$prodId}",
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:{$orgName} Parking",
            'X-WR-TIMEZONE:Europe/Berlin',
        ];

        foreach ($bookings as $b) {
            $uid = $b->id.'@parkhub';
            // start_time / end_time are Carbon casts → use ->timestamp
            // instead of stringifying through strtotime(), both for
            // strict_types correctness and to dodge the double-timezone
            // roundtrip that the parse-string-back detour used to incur.
            $start = gmdate('Ymd\THis\Z', $b->start_time->timestamp);
            $end = $b->end_time
                ? gmdate('Ymd\THis\Z', $b->end_time->timestamp)
                : gmdate('Ymd\THis\Z', $b->start_time->timestamp + 3600);
            $summary = "Parking: {$b->slot_number} ({$b->lot_name})";
            $location = $b->lot_name ?? '';
            $description = $b->vehicle_plate ? "Vehicle: {$b->vehicle_plate}" : '';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$now}";
            $lines[] = "DTSTART:{$start}";
            $lines[] = "DTEND:{$end}";
            $lines[] = "SUMMARY:{$summary}";
            if ($location) {
                $lines[] = "LOCATION:{$location}";
            }
            if ($description) {
                $lines[] = "DESCRIPTION:{$description}";
            }
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        $ical = implode("\r\n", $lines)."\r\n";

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="parkhub-bookings.ics"',
        ]);
    }
}
