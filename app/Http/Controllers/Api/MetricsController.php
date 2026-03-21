<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function index(Request $request)
    {
        $expectedToken = config('app.metrics_token');
        if ($expectedToken && $request->bearerToken() !== $expectedToken) {
            return response('Unauthorized', 401);
        }

        $lines = [];

        // Active bookings
        $activeBookings = Booking::where('status', 'confirmed')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->count();
        $lines[] = '# HELP active_bookings Number of currently active bookings';
        $lines[] = '# TYPE active_bookings gauge';
        $lines[] = "active_bookings $activeBookings";

        // Total users
        $totalUsers = User::count();
        $lines[] = '# HELP users_total Total registered users';
        $lines[] = '# TYPE users_total gauge';
        $lines[] = "users_total $totalUsers";

        // Parking lot occupancy
        $lots = ParkingLot::all();
        $lines[] = '# HELP parking_lot_total_slots Total slots per parking lot';
        $lines[] = '# TYPE parking_lot_total_slots gauge';
        $lines[] = '# HELP parking_lot_occupied_slots Occupied slots per parking lot';
        $lines[] = '# TYPE parking_lot_occupied_slots gauge';
        $lines[] = '# HELP parking_lot_occupancy_percent Occupancy percentage per parking lot';
        $lines[] = '# TYPE parking_lot_occupancy_percent gauge';

        foreach ($lots as $lot) {
            $total = $lot->total_slots;
            $occupied = Booking::where('lot_id', $lot->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->count();
            $pct = $total > 0 ? round(($occupied / $total) * 100, 1) : 0;
            $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $lot->name);

            $lines[] = "parking_lot_total_slots{lot_id=\"{$lot->id}\",lot_name=\"$name\"} $total";
            $lines[] = "parking_lot_occupied_slots{lot_id=\"{$lot->id}\",lot_name=\"$name\"} $occupied";
            $lines[] = "parking_lot_occupancy_percent{lot_id=\"{$lot->id}\",lot_name=\"$name\"} $pct";
        }

        // Bookings today
        $todayBookings = Booking::whereDate('start_time', today())->count();
        $lines[] = '# HELP bookings_today Number of bookings for today';
        $lines[] = '# TYPE bookings_today gauge';
        $lines[] = "bookings_today $todayBookings";

        // Active sessions (approximation via Sanctum tokens)
        try {
            $activeSessions = DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', now()->subHour())
                ->count();
            $lines[] = '# HELP active_sessions Approximate active sessions (tokens used in last hour)';
            $lines[] = '# TYPE active_sessions gauge';
            $lines[] = "active_sessions $activeSessions";
        } catch (\Exception $e) {
            // Skip if table doesn't exist
        }

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
