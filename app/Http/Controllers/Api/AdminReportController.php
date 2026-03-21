<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    private function requireAdmin($request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    /**
     * Sanitize a value for CSV output to prevent formula injection.
     * Prefixes dangerous leading characters (=, +, -, @, TAB, CR) with a single quote.
     */
    private function csvSafe(mixed $value): string
    {
        $str = (string) $value;
        if (preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'".$str;
        }

        return $str;
    }

    public function stats(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $now = now();
        $activeBookings = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)->where('end_time', '>=', $now)->count();
        $totalSlots = ParkingSlot::count();

        return response()->json([
            'total_users' => User::count(),
            'total_lots' => ParkingLot::count(),
            'total_slots' => $totalSlots,
            'available_slots' => $totalSlots - $activeBookings,
            'total_bookings' => Booking::count(),
            'active_bookings' => $activeBookings,
            'occupancy_percent' => $totalSlots > 0 ? round(($activeBookings / $totalSlots) * 100) : 0,
            'homeoffice_today' => Absence::where('absence_type', 'homeoffice')
                ->where('start_date', '<=', $now->toDateString())
                ->where('end_date', '>=', $now->toDateString())->count(),
            'total_bookings_today' => Booking::whereDate('start_time', $now->toDateString())->count(),
        ]);
    }

    public function heatmap(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $request->validate(['days' => 'integer|min:1|max:365']);
        $days = (int) $request->get('days', 30);

        // Use DB-specific date expressions per driver
        $driver = DB::getDriverName();
        $query = Booking::where('start_time', '>=', now()->subDays($days));

        if ($driver === 'sqlite') {
            $bookings = $query
                ->selectRaw('CAST(strftime("%w", start_time) AS INTEGER) as day_of_week, CAST(strftime("%H", start_time) AS INTEGER) as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: EXTRACT(DOW ...) returns 0=Sunday...6=Saturday (same as SQLite strftime %w)
            $bookings = $query
                ->selectRaw('EXTRACT(DOW FROM start_time)::integer as day_of_week, EXTRACT(HOUR FROM start_time)::integer as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        } else {
            // MySQL / MariaDB (DAYOFWEEK returns 1=Sunday...7=Saturday, normalise to 0=Sunday...6=Saturday)
            $bookings = $query
                ->selectRaw('(DAYOFWEEK(start_time) - 1) as day_of_week, HOUR(start_time) as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        }

        return response()->json($bookings);
    }

    public function reports(Request $request): JsonResponse
    {
        $this->requireAdmin($request);
        $days = (int) $request->get('days', 30);
        $bookings = Booking::where('created_at', '>=', now()->subDays($days))->get();
        $byDay = $bookings->groupBy(fn ($b) => substr($b->start_time, 0, 10));

        return response()->json([
            'period_days' => $days,
            'total_bookings' => $bookings->count(),
            'by_day' => $byDay->map->count()->sortKeys()->all(),
            'by_status' => $bookings->groupBy('status')->map->count()->all(),
            'by_booking_type' => $bookings->groupBy('booking_type')->map->count()->all(),
            'avg_duration_hours' => $bookings->avg(function ($b) {
                return (strtotime($b->end_time) - strtotime($b->start_time)) / 3600;
            }),
        ]);
    }

    public function dashboardCharts(Request $request): JsonResponse
    {
        $this->requireAdmin($request);
        $days = (int) $request->get('days', 7);
        $startDate = now()->subDays($days - 1)->toDateString();

        // Single GROUP BY query instead of N individual count queries
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $counts = Booking::where('start_time', '>=', $startDate)
                ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');
        } else {
            $counts = Booking::where('start_time', '>=', $startDate)
                ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');
        }

        $labels = [];
        $bookingCounts = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $labels[] = $date;
            $bookingCounts[] = $counts->get($date, 0);
        }

        return response()->json([
            'booking_trend' => ['labels' => $labels, 'data' => $bookingCounts],
            'occupancy_now' => [
                'total' => ParkingSlot::count(),
                'occupied' => Booking::whereIn('status', ['confirmed', 'active'])
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now())
                    ->count(),
            ],
        ]);
    }

    public function exportBookingsCsv(Request $request): Response
    {
        $this->requireAdmin($request);

        $bookings = Booking::with('user')->orderBy('start_time', 'desc')->get();

        $headers = ['ID', 'User', 'Lot', 'Slot', 'Vehicle', 'Start', 'End', 'Status', 'Type'];
        $rows = $bookings->map(fn ($b) => [
            $b->id,
            $this->csvSafe($b->user?->name ?? 'Guest'),
            $this->csvSafe($b->lot_name),
            $this->csvSafe($b->slot_number),
            $this->csvSafe($b->vehicle_plate ?? ''),
            $b->start_time?->format('Y-m-d H:i'),
            $b->end_time?->format('Y-m-d H:i'),
            $b->status,
            $b->booking_type,
        ]);

        $output = fopen('php://output', 'w');
        ob_start();
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bookings-export.csv"',
        ]);
    }

    public function exportUsersCsv(Request $request): Response
    {
        $this->requireAdmin($request);

        $users = User::orderBy('name')->get();

        $headers = ['ID', 'Username', 'Name', 'Email', 'Role', 'Department', 'Active', 'Created'];
        $rows = $users->map(fn ($u) => [
            $u->id,
            $this->csvSafe($u->username),
            $this->csvSafe($u->name),
            $this->csvSafe($u->email),
            $u->role,
            $this->csvSafe($u->department ?? ''),
            $u->is_active ? 'yes' : 'no',
            optional($u->created_at)->format('Y-m-d'),
        ]);

        $output = fopen('php://output', 'w');
        ob_start();
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-export.csv"',
        ]);
    }
}
