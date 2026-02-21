<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Announcement;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{


    private function requireAdmin($request)
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            abort(403, 'Admin access required');
        }
    }

    public function stats(Request $request)
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

    public function heatmap(Request $request)
    {
        $this->requireAdmin($request);

        $days = $request->get('days', 30);
        $bookings = Booking::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('CAST(strftime("%w", start_time) AS INTEGER) as day_of_week, CAST(strftime("%H", start_time) AS INTEGER) as hour, COUNT(*) as count')
            ->groupBy('day_of_week', 'hour')
            ->get();

        return response()->json($bookings);
    }

    public function auditLog(Request $request)
    {
        $this->requireAdmin($request);

        $query = AuditLog::orderBy('created_at', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('username', 'like', '%' . $request->search . '%')
                  ->orWhere('action', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json($query->paginate($request->get('per_page', 50)));
    }

    public function announcements()
    {
        return response()->json(Announcement::orderBy('created_at', 'desc')->get());
    }

    public function createAnnouncement(Request $request)
    {
        $this->requireAdmin($request);

        $request->validate(['title' => 'required|string', 'message' => 'required|string']);
        $ann = Announcement::create(array_merge(
            $request->only(['title', 'message', 'severity']),
            ['created_by' => $request->user()->id, 'active' => true]
        ));
        return response()->json($ann, 201);
    }

    public function updateAnnouncement(Request $request, string $id)
    {
        $ann = Announcement::findOrFail($id);
        $ann->update($request->only(['title', 'message', 'severity', 'active']));
        return response()->json($ann);
    }

    public function deleteAnnouncement(string $id)
    {
        Announcement::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function importUsers(Request $request)
    {
        $this->requireAdmin($request);

        $request->validate(['users' => 'required|array']);
        $imported = 0;

        foreach ($request->users as $userData) {
            if (User::where('username', $userData['username'] ?? '')->orWhere('email', $userData['email'] ?? '')->exists()) {
                continue;
            }
            User::create([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password'] ?? 'changeme123'),
                'name' => $userData['name'] ?? $userData['username'],
                'role' => $userData['role'] ?? 'user',
                'is_active' => true,
                'department' => $userData['department'] ?? null,
                'preferences' => ['language' => 'en', 'theme' => 'system'],
            ]);
            $imported++;
        }

        return response()->json(['imported' => $imported]);
    }

public function getSettings()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $defaults = [
            'company_name' => 'ParkHub',
            'use_case' => 'corporate',
            'self_registration' => 'true',
            'license_plate_mode' => 'optional',
            'display_name_format' => 'first_name',
            'max_bookings_per_day' => '3',
            'allow_guest_bookings' => 'false',
            'auto_release_minutes' => '30',
            'require_vehicle' => 'false',
            'primary_color' => '#d97706',
            'secondary_color' => '#475569',
        ];
        return response()->json(array_merge($defaults, $settings));
    }

    public function updateSettings(Request $request)
    {
        $this->requireAdmin($request);

        foreach ($request->all() as $key => $value) {
            Setting::set($key, is_array($value) ? json_encode($value) : $value);
        }
        return response()->json(['message' => 'Settings updated']);
    }

    public function users()
    {
        return response()->json(User::all()->map(fn ($u) => collect($u)->except(['password'])));
    }

    public function updateUser(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $data = $request->only(['name', 'email', 'role', 'is_active', 'department']);
        if ($request->has('password') && $request->password) {
            $data['password'] = Hash::make($request->password);
        }
        $user->update($data);
        return response()->json($user->fresh());
    }

    public function exportBookingsCsv()
    {
        $bookings = \App\Models\Booking::with('user')->orderBy('start_time', 'desc')->get();

        $headers = ['ID', 'User', 'Lot', 'Slot', 'Vehicle', 'Start', 'End', 'Status', 'Type'];
        $rows    = $bookings->map(fn ($b) => [
            $b->id,
            $b->user?->name ?? 'Guest',
            $b->lot_name,
            $b->slot_number,
            $b->vehicle_plate ?? '',
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
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bookings-export.csv"',
        ]);
    }
}
