<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\Absence;
use App\Models\Setting;

class TeamController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $users = User::where('is_active', true)->get();
        $privacyMode = Setting::get('booking_visibility', 'full');

        $team = $users->map(function ($user) use ($today, $privacyMode) {
            $absence = Absence::where('user_id', $user->id)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $booking = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->first();

            $displayName = match ($privacyMode) {
                'firstName' => explode(' ', $user->name)[0] ?? $user->username,
                'initials' => collect(explode(' ', $user->name))->map(fn($n) => strtoupper(substr($n, 0, 1)))->join('.'),
                'occupied' => 'User',
                default => $user->name,
            };

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

    public function today(\Illuminate\Http\Request $request)
    {
        $today = now()->toDateString();
        $absences = \App\Models\Absence::with('user')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();
        $bookings = \App\Models\Booking::with('user')
            ->whereDate('start_time', $today)
            ->whereIn('status', ['confirmed', 'active'])
            ->get();
        return response()->json([
            'date'     => $today,
            'absences' => $absences->map(fn($a) => [
                'user_id'      => $a->user_id,
                'user_name'    => $a->user?->name,
                'absence_type' => $a->absence_type,
            ])->values(),
            'bookings' => $bookings->map(fn($b) => [
                'user_id'   => $b->user_id,
                'user_name' => $b->user?->name,
                'slot'      => $b->slot_number,
                'lot'       => $b->lot_name,
            ])->values(),
        ]);
    }
}
