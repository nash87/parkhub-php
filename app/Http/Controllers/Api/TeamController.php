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
}
