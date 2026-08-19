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
        $absences = Absence::with('user')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();
        $bookings = Booking::with('user')
            ->whereDate('start_time', $today)
            ->whereIn('status', ['confirmed', 'active'])
            ->get();

        return response()->json([
            'date' => $today,
            'absences' => $absences->map(fn ($a) => [
                'user_id' => $a->user_id,
                'user_name' => $a->user?->name,
                'absence_type' => $a->absence_type,
            ])->values(),
            'bookings' => $bookings->map(fn ($b) => [
                'user_id' => $b->user_id,
                'user_name' => $b->user?->name,
                'slot' => $b->slot_number,
                'lot' => $b->lot_name,
            ])->values(),
        ]);
    }
}
