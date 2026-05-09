<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ICalController extends Controller
{
    /**
     * GET /api/v1/calendar/ical — authenticated iCal feed for the current user.
     */
    public function feed(Request $request): Response
    {
        $user = $request->user();
        $bookings = Booking::where('user_id', $user->id)
            ->where('start_time', '>=', now()->subMonths(3))
            ->orderBy('start_time')
            ->get();

        return $this->buildIcal($bookings, $user->name ?? $user->username);
    }

    /**
     * GET /api/v1/calendar/ical/{token} — public iCal feed via token (no auth).
     */
    public function publicFeed(string $token, Request $request): Response
    {
        $user = User::where('ical_token', $token)->first();

        if (! $user) {
            // Audit log even on miss so brute-force token guessing leaves a trail.
            AuditLog::log([
                'user_id' => null,
                'username' => null,
                'action' => 'ical.public.fetch.miss',
                'ip_address' => $request->ip(),
            ]);

            return response('Not Found', 404);
        }

        // Audit-log every successful fetch (audit M-3): a leaked iCal token
        // would otherwise be polled for months with no detection signal. The
        // entry costs one DB row per request, throttled by the route-level
        // `throttle:api` middleware so it can't be amplified into a write DoS.
        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'ical.public.fetch',
            'ip_address' => $request->ip(),
        ]);

        $bookings = Booking::where('user_id', $user->id)
            ->where('start_time', '>=', now()->subMonths(3))
            ->orderBy('start_time')
            ->get();

        $response = $this->buildIcal($bookings, $user->name ?? $user->username);

        // `private, max-age=60` lets the user's own calendar app cache for a
        // minute while preventing shared / CDN caches from holding the feed
        // (which would otherwise stale stress on a refresh storm and leak
        // booking data to any cache layer in front of us).
        $response->headers->set('Cache-Control', 'private, max-age=60');

        return $response;
    }

    /**
     * POST /api/v1/calendar/token — generate or regenerate the user's iCal token.
     */
    public function generateToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = Str::random(48);

        $user->update(['ical_token' => $token]);

        $url = url("/api/v1/calendar/ical/{$token}");

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'url' => $url,
            ],
        ]);
    }

    /**
     * Build an iCalendar response from bookings.
     */
    private function buildIcal($bookings, string $calName): Response
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ParkHub//ParkHub Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeIcal($calName.' - ParkHub'),
        ];

        foreach ($bookings as $booking) {
            $uid = $booking->id.'@parkhub';
            $start = $this->formatIcalDate($booking->start_time);
            $end = $this->formatIcalDate($booking->end_time);
            $summary = 'Parking: '.($booking->lot_name ?? 'Lot');
            $description = 'Slot: '.($booking->slot_number ?? '-').', Status: '.($booking->status ?? 'confirmed');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid;
            $lines[] = 'DTSTART:'.$start;
            $lines[] = 'DTEND:'.$end;
            $lines[] = 'SUMMARY:'.$this->escapeIcal($summary);
            $lines[] = 'DESCRIPTION:'.$this->escapeIcal($description);
            $lines[] = 'STATUS:'.strtoupper($booking->status ?? 'CONFIRMED');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines);

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="parkhub.ics"',
        ]);
    }

    private function formatIcalDate(mixed $date): string
    {
        return Carbon::parse($date)->utc()->format('Ymd\THis\Z');
    }

    private function escapeIcal(string $text): string
    {
        return str_replace(["\n", "\r", ',', ';'], ['\\n', '', '\\,', '\\;'], $text);
    }
}
