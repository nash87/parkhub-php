<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Calendar views backed by Booking data: JSON events + iCal feed.
 *
 * Split out of BookingController (T-1743). Method bodies are moved
 * verbatim — behavioural refactors happen in a follow-up pass.
 *
 * Tier-2 item 9 adds the single-booking {id}.ics variant alongside the
 * existing user-scoped feed so "Zum Kalender hinzufügen" on a Buchungen
 * row can download one VEVENT without subscribing the whole calendar.
 */
class BookingCalendarController extends Controller
{
    public function calendarEvents(Request $request)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateTimeString();
        $to = $request->to ?? now()->endOfMonth()->toDateTimeString();
        $bookings = Booking::where('user_id', $request->user()->id)
            ->where('start_time', '>=', $from)
            ->where('end_time', '<=', $to)
            ->select(['id', 'lot_name', 'slot_number', 'start_time', 'end_time', 'status'])
            ->get();
        $events = $bookings->map(function ($b) {
            return [
                'id' => $b->id,
                'title' => $b->lot_name.' — '.$b->slot_number,
                'start' => $b->start_time,
                'end' => $b->end_time,
                'type' => 'booking',
                'status' => $b->status,
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
            // end_time is declared non-null on the bookings table (see
            // Booking @property and the initial migration), so no fallback
            // ternary — larastan/phpstan flags the dead branch at level 5.
            $start = gmdate('Ymd\THis\Z', $b->start_time->timestamp);
            $end = gmdate('Ymd\THis\Z', $b->end_time->timestamp);
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

    /**
     * Tier-2 item 9 — single-booking .ics download.
     * GET /api/v1/bookings/{id}.ics → RFC5545 VCALENDAR with one VEVENT.
     */
    public function icalSingle(Request $request, string $id)
    {
        $user = $request->user();
        $booking = Booking::where('user_id', $user->id)->findOrFail($id);

        $orgName = Setting::get('company_name', 'ParkHub');
        $prodId = '-//ParkHub//Bookings//EN';
        $now = gmdate('Ymd\THis\Z');
        $uid = $booking->id.'@parkhub';
        // end_time is non-null on bookings — same reasoning as ical() above.
        $start = gmdate('Ymd\THis\Z', $booking->start_time->timestamp);
        $end = gmdate('Ymd\THis\Z', $booking->end_time->timestamp);
        $summary = "Parking: {$booking->slot_number} ({$booking->lot_name})";
        $location = $booking->lot_name ?? '';
        $description = $booking->vehicle_plate ? "Vehicle: {$booking->vehicle_plate}" : '';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            "PRODID:{$prodId}",
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:{$orgName} Parking",
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$now}",
            "DTSTART:{$start}",
            "DTEND:{$end}",
            "SUMMARY:{$summary}",
        ];
        if ($location) {
            $lines[] = "LOCATION:{$location}";
        }
        if ($description) {
            $lines[] = "DESCRIPTION:{$description}";
        }
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        $ical = implode("\r\n", $lines)."\r\n";
        $filename = "parkhub-booking-{$booking->id}.ics";

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
