<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingVisibility;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $now = now();
        $users = User::where('is_active', true)
            ->select(['id', 'name', 'username', 'department'])
            ->get();
        $privacyMode = Setting::get('booking_visibility', 'full');

        // Batch-load all absences for today, keyed by user_id
        $absencesByUser = Absence::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get()
            ->keyBy('user_id');

        // Batch-load all active bookings for right now, keyed by user_id
        $bookingsByUser = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->get()
            ->keyBy('user_id');

        $team = $users->map(function ($user) use ($absencesByUser, $bookingsByUser, $privacyMode) {
            $absence = $absencesByUser->get($user->id);
            $booking = $bookingsByUser->get($user->id);

            $displayName = BookingVisibility::label($user->name, $user->username, $privacyMode);

            return [
                'id' => $user->id,
                'name' => $displayName,
                'status' => $absence ? $absence->absence_type : ($booking ? 'parked' : 'not_scheduled'),
                'slot' => $booking?->slot_number,
                'department' => $user->department,
            ];
        });

        return response()->json($team);
    }

    public function today(Request $request)
    {
        $today = now()->toDateString();
        $absences = Absence::query()
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();
        $bookings = Booking::query()
            ->whereDate('start_time', $today)
            ->whereIn('status', ['confirmed', 'active'])
            ->get();

        // Resolve display identities in one query. `User` soft-deletes, so
        // an owner genuinely may not resolve; `Collection::get()` is honest
        // about that where the relation's PHPDoc is not, and a vanished
        // user degrades to the anonymous label rather than a raw name.
        $people = User::query()
            ->whereIn('id', $absences->pluck('user_id')->merge($bookings->pluck('user_id'))->unique()->all())
            ->get(['id', 'name', 'username'])
            ->keyBy('id');

        // The same disclosure policy the team roster uses. This endpoint
        // returns the same population and ignored it entirely, so an
        // operator who set `booking_visibility` and verified it on the
        // roster was still publishing real names here.
        $mode = BookingVisibility::mode();
        $isAdmin = $request->user()?->isAdmin() ?? false;

        return response()->json([
            'date' => $today,
            'absences' => $absences->map(fn ($a) => [
                'user_id' => $a->user_id,
                'user_name' => BookingVisibility::label(
                    $people->get($a->user_id)?->name,
                    $people->get($a->user_id)?->username,
                    $mode,
                ),
                // *That* somebody is away is scheduling information; *why*
                // is health data when the answer is `sick`, and the roster
                // does not need it. Admins keep the detail.
                'absence_type' => BookingVisibility::absenceType($a->absence_type, $isAdmin),
            ])->values(),
            'bookings' => $bookings->map(fn ($b) => [
                'user_id' => $b->user_id,
                'user_name' => BookingVisibility::label(
                    $people->get($b->user_id)?->name,
                    $people->get($b->user_id)?->username,
                    $mode,
                ),
                'slot' => $b->slot_number,
                'lot' => $b->lot_name,
            ])->values(),
        ]);
    }
}
