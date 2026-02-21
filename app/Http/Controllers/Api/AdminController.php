<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
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

    public function reports(Request $request)
    {
        $this->requireAdmin($request);
        $days = (int)$request->get('days', 30);
        $bookings = Booking::where('created_at', '>=', now()->subDays($days))->get();
        $byDay = $bookings->groupBy(fn($b) => substr($b->start_time, 0, 10));
        return response()->json([
            'period_days' => $days,
            'total_bookings' => $bookings->count(),
            'by_day' => $byDay->map->count()->sortKeys()->all(),
            'by_status' => $bookings->groupBy('status')->map->count()->all(),
            'by_booking_type' => $bookings->groupBy('booking_type')->map->count()->all(),
            'avg_duration_hours' => $bookings->avg(function($b) {
                return (strtotime($b->end_time) - strtotime($b->start_time)) / 3600;
            }),
        ]);
    }

    public function dashboardCharts(Request $request)
    {
        $this->requireAdmin($request);
        $days = (int)$request->get('days', 7);
        $labels = [];
        $bookingCounts = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $labels[] = $date;
            $bookingCounts[] = Booking::whereDate('start_time', $date)->count();
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

    public function getBranding(Request $request)
    {
        $this->requireAdmin($request);
        return response()->json([
            'company_name' => Setting::get('company_name', 'ParkHub'),
            'primary_color' => Setting::get('brand_primary_color', '#3b82f6'),
            'logo_url' => Setting::get('logo_url', null),
            'use_case' => Setting::get('use_case', 'corporate'),
        ]);
    }

    public function updateBranding(Request $request)
    {
        $this->requireAdmin($request);
        foreach (['company_name', 'primary_color', 'logo_url', 'use_case'] as $key) {
            if ($request->has($key)) Setting::set('brand_' . $key, $request->input($key));
        }
        if ($request->has('company_name')) Setting::set('company_name', $request->input('company_name'));
        if ($request->has('use_case')) Setting::set('use_case', $request->input('use_case'));
        return response()->json(['message' => 'Branding updated']);
    }

    public function uploadBrandingLogo(Request $request)
    {
        $this->requireAdmin($request);
        $request->validate(['logo' => 'required|image|max:2048']);
        $path = $request->file('logo')->store('branding', 'public');
        Setting::set('logo_url', '/storage/' . $path);
        return response()->json(['logo_url' => '/storage/' . $path]);
    }

    public function serveBrandingLogo(Request $request)
    {
        $logoUrl = Setting::get('logo_url', null);
        if (!$logoUrl) {
            // Return default SVG icon
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#3b82f6"><circle cx="32" cy="32" r="30"/><text x="32" y="42" text-anchor="middle" font-size="32" fill="white" font-family="Arial" font-weight="bold">P</text></svg>';
            return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
        }
        if (str_starts_with($logoUrl, '/storage/')) {
            $filePath = storage_path('app/public' . substr($logoUrl, 8));
            if (file_exists($filePath)) {
                return response()->file($filePath, ['Cache-Control' => 'public, max-age=86400']);
            }
        }
        return redirect($logoUrl);
    }

    public function getPrivacy(Request $request)
    {
        $this->requireAdmin($request);
        return response()->json([
            'policy_text' => Setting::get('privacy_policy', ''),
            'data_retention_days' => (int)Setting::get('data_retention_days', 365),
            'gdpr_enabled' => Setting::get('gdpr_enabled', 'true') === 'true',
        ]);
    }

    public function updatePrivacy(Request $request)
    {
        $this->requireAdmin($request);
        foreach (['policy_text', 'data_retention_days', 'gdpr_enabled'] as $key) {
            if ($request->has($key)) Setting::set('privacy_' . str_replace('_text', '_policy', $key), (string)$request->input($key));
        }
        if ($request->has('policy_text')) Setting::set('privacy_policy', $request->input('policy_text'));
        if ($request->has('data_retention_days')) Setting::set('data_retention_days', $request->input('data_retention_days'));
        if ($request->has('gdpr_enabled')) Setting::set('gdpr_enabled', $request->boolean('gdpr_enabled') ? 'true' : 'false');
        return response()->json(['message' => 'Privacy settings updated']);
    }

    public function resetDatabase(Request $request)
    {
        $this->requireAdmin($request);
        $request->validate(['confirm' => 'required|in:RESET']);
        // Delete all user data but keep admin account
        $admin = $request->user();
        Booking::query()->delete();
        \App\Models\Absence::query()->delete();
        \App\Models\Vehicle::query()->delete();
        \App\Models\ParkingSlot::query()->update(['status' => 'available']);
        User::where('id', '!=', $admin->id)->delete();
        AuditLog::create([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'action' => 'database_reset',
        ]);
        return response()->json(['message' => 'Database reset. All user data deleted.']);
    }

    public function getAutoReleaseSettings(Request $request)
    {
        $this->requireAdmin($request);
        return response()->json([
            'enabled' => Setting::get('auto_release_enabled', 'false') === 'true',
            'timeout_minutes' => (int)Setting::get('auto_release_timeout', 30),
        ]);
    }

    public function updateAutoReleaseSettings(Request $request)
    {
        $this->requireAdmin($request);
        if ($request->has('enabled')) Setting::set('auto_release_enabled', $request->boolean('enabled') ? 'true' : 'false');
        if ($request->has('timeout_minutes')) Setting::set('auto_release_timeout', $request->input('timeout_minutes'));
        return response()->json(['message' => 'Auto-release settings updated']);
    }

    public function getEmailSettings(Request $request)
    {
        $this->requireAdmin($request);
        return response()->json([
            'smtp_host'  => Setting::get('smtp_host', ''),
            'smtp_port'  => (int)Setting::get('smtp_port', 587),
            'smtp_user'  => Setting::get('smtp_user', ''),
            'from_email' => Setting::get('from_email', ''),
            'from_name'  => Setting::get('from_name', 'ParkHub'),
            'enabled'    => Setting::get('email_enabled', 'false') === 'true',
        ]);
    }

    public function updateEmailSettings(Request $request)
    {
        $this->requireAdmin($request);
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'from_email', 'from_name'] as $key) {
            if ($request->has($key)) Setting::set($key, $request->input($key));
        }
        if ($request->has('enabled')) Setting::set('email_enabled', $request->boolean('enabled') ? 'true' : 'false');
        return response()->json(['message' => 'Email settings updated']);
    }

    public function getWebhookSettings(Request $request)
    {
        $this->requireAdmin($request);
        $hooks = \App\Models\Webhook::all();
        return response()->json($hooks);
    }

    public function updateWebhookSettings(Request $request)
    {
        $this->requireAdmin($request);
        if ($request->has('webhooks')) {
            \App\Models\Webhook::query()->delete();
            foreach ($request->input('webhooks') as $hook) {
                \App\Models\Webhook::create([
                    'url' => $hook['url'],
                    'events' => $hook['events'] ?? [],
                    'secret' => $hook['secret'] ?? null,
                    'active' => $hook['active'] ?? true,
                ]);
            }
        }
        return response()->json(['message' => 'Webhook settings updated']);
    }

    public function updateSlot(Request $request, string $id)
    {
        $this->requireAdmin($request);
        $slot = \App\Models\ParkingSlot::findOrFail($id);
        $slot->update($request->only(['slot_number', 'status', 'reserved_for_department', 'zone_id']));
        return response()->json($slot->fresh());
    }

    public function deleteLot(Request $request, string $id)
    {
        $this->requireAdmin($request);
        $lot = ParkingLot::findOrFail($id);
        $lot->delete();
        return response()->json(['message' => 'Lot deleted']);
    }

    public function deleteUser(Request $request, string $id)
    {
        $this->requireAdmin($request);
        if ($id === $request->user()->id) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
