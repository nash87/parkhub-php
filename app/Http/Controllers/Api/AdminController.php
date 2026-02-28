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
use Illuminate\Support\Str;

class AdminController extends Controller
{


    private function requireAdmin($request): void
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
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

        $request->validate(['days' => 'integer|min:1|max:365']);
        $days = (int) $request->get('days', 30);

        // Use DB-agnostic expressions: DAYOFWEEK (MySQL) vs strftime (SQLite)
        $driver = \Illuminate\Support\Facades\DB::getDriverName();

        if ($driver === 'sqlite') {
            $bookings = Booking::where('start_time', '>=', now()->subDays($days))
                ->selectRaw('CAST(strftime("%w", start_time) AS INTEGER) as day_of_week, CAST(strftime("%H", start_time) AS INTEGER) as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        } else {
            // MySQL / MariaDB (DAYOFWEEK returns 1=Sunday…7=Saturday, normalise to 0=Sunday…6=Saturday)
            $bookings = Booking::where('start_time', '>=', now()->subDays($days))
                ->selectRaw('(DAYOFWEEK(start_time) - 1) as day_of_week, HOUR(start_time) as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        }

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

    public function announcements(Request $request)
    {
        $this->requireAdmin($request);
        return response()->json(Announcement::orderBy('created_at', 'desc')->get());
    }

    public function createAnnouncement(Request $request)
    {
        $this->requireAdmin($request);

        $request->validate([
            'title'      => 'required|string|max:255',
            'message'    => 'required|string|max:10000',
            'severity'   => 'nullable|in:info,warning,error,success',
            'expires_at' => 'nullable|date',
        ]);
        $ann = Announcement::create(array_merge(
            $request->only(['title', 'message', 'severity', 'expires_at']),
            ['created_by' => $request->user()->id, 'active' => true]
        ));
        return response()->json($ann, 201);
    }

    public function updateAnnouncement(Request $request, string $id)
    {
        $this->requireAdmin($request);
        $request->validate([
            'title'      => 'sometimes|string|max:255',
            'message'    => 'sometimes|string',
            'severity'   => 'sometimes|in:info,warning,error,success',
            'active'     => 'sometimes|boolean',
            'expires_at' => 'sometimes|nullable|date',
        ]);
        $ann = Announcement::findOrFail($id);
        $ann->update($request->only(['title', 'message', 'severity', 'active', 'expires_at']));
        return response()->json($ann);
    }

    public function deleteAnnouncement(Request $request, string $id)
    {
        $this->requireAdmin($request);
        Announcement::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function importUsers(Request $request)
    {
        $this->requireAdmin($request);

        $request->validate([
            'users'              => 'required|array|max:500',
            'users.*.username'   => 'required|string|min:3|max:50|alpha_dash',
            'users.*.email'      => 'required|email|max:255',
            'users.*.name'       => 'nullable|string|max:255',
            'users.*.role'       => 'nullable|in:user,admin',
            'users.*.department' => 'nullable|string|max:255',
            'users.*.password'   => 'nullable|string|min:8|max:128',
        ]);

        $imported = 0;

        foreach ($request->users as $userData) {
            if (User::where('username', $userData['username'])->orWhere('email', $userData['email'])->exists()) {
                continue;
            }
            User::create([
                'username'   => $userData['username'],
                'email'      => $userData['email'],
                'password'   => Hash::make($userData['password'] ?? \Illuminate\Support\Str::random(16)),
                'name'       => $userData['name'] ?? $userData['username'],
                'role'       => $userData['role'] ?? 'user',
                'is_active'  => true,
                'department' => $userData['department'] ?? null,
                'preferences'=> ['language' => 'en', 'theme' => 'system'],
            ]);
            $imported++;
        }

        return response()->json(['imported' => $imported]);
    }

public function getSettings(Request $request)
    {
        $this->requireAdmin($request);

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

        // Allowlist of keys that can be set via this endpoint.
        // Prevents injection of arbitrary/internal settings keys.
        $allowed = [
            'company_name', 'use_case', 'self_registration', 'license_plate_mode',
            'display_name_format', 'max_bookings_per_day', 'allow_guest_bookings',
            'auto_release_minutes', 'require_vehicle', 'primary_color', 'secondary_color',
        ];

        $request->validate([
            'company_name'         => 'sometimes|string|max:255',
            'use_case'             => 'sometimes|in:corporate,university,residential,other',
            'self_registration'    => 'sometimes|in:true,false',
            'license_plate_mode'   => 'sometimes|in:required,optional,disabled',
            'display_name_format'  => 'sometimes|in:first_name,full_name,username',
            'max_bookings_per_day' => 'sometimes|integer|min:1|max:50',
            'allow_guest_bookings' => 'sometimes|in:true,false',
            'auto_release_minutes' => 'sometimes|integer|min:0|max:480',
            'require_vehicle'      => 'sometimes|in:true,false',
            'primary_color'        => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/',
            'secondary_color'      => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        foreach ($request->only($allowed) as $key => $value) {
            Setting::set($key, is_array($value) ? json_encode($value) : (string) $value);
        }
        return response()->json(['message' => 'Settings updated']);
    }

    public function users(Request $request)
    {
        $this->requireAdmin($request);
        $perPage = min((int) request('per_page', 20), 100);
        $users = User::paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'error' => null,
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function bookings(Request $request)
    {
        $this->requireAdmin($request);

        $query = Booking::with('user')->orderBy('start_time', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('lot_name') && $request->lot_name !== 'all') {
            $query->where('lot_name', $request->lot_name);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date . ' 23:59:59');
        }

        return response()->json($query->get());
    }

    public function cancelBooking(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $booking = Booking::findOrFail($id);
        $booking->update(['status' => 'cancelled']);

        AuditLog::create([
            'user_id'  => $request->user()->id,
            'username' => $request->user()->username,
            'action'   => 'admin_booking_cancelled',
            'details'  => ['booking_id' => $id],
        ]);

        return response()->json($booking->fresh());
    }

    public function updateUser(Request $request, string $id)
    {
        $this->requireAdmin($request);
        $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|max:255|unique:users,email,' . $id,
            'role'       => 'sometimes|in:user,admin,superadmin',
            'is_active'  => 'sometimes|boolean',
            'department' => 'sometimes|nullable|string|max:255',
            'password'   => 'sometimes|string|min:8',
        ]);
        $user = User::findOrFail($id);
        $data = $request->only(['name', 'email', 'role', 'is_active', 'department']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        $user->update($data);
        // Return via toArray() to respect $hidden
        return response()->json($user->fresh()->toArray());
    }

    public function exportBookingsCsv(Request $request)
    {
        $this->requireAdmin($request);

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

    // ── Impressum (DDG § 5 — legally required for German operators) ──────────

    public function getImpress(Request $request)
    {
        $this->requireAdmin($request);
        return response()->json([
            'provider_name'       => Setting::get('impressum_provider_name', ''),
            'provider_legal_form' => Setting::get('impressum_legal_form', ''),
            'street'              => Setting::get('impressum_street', ''),
            'zip_city'            => Setting::get('impressum_zip_city', ''),
            'country'             => Setting::get('impressum_country', 'Deutschland'),
            'email'               => Setting::get('impressum_email', ''),
            'phone'               => Setting::get('impressum_phone', ''),
            'register_court'      => Setting::get('impressum_register_court', ''),
            'register_number'     => Setting::get('impressum_register_number', ''),
            'vat_id'              => Setting::get('impressum_vat_id', ''),
            'responsible_person'  => Setting::get('impressum_responsible', ''),
            'custom_text'         => Setting::get('impressum_custom_text', ''),
        ]);
    }

    public function updateImpress(Request $request)
    {
        $this->requireAdmin($request);
        $fields = [
            'provider_name', 'provider_legal_form', 'street', 'zip_city', 'country',
            'email', 'phone', 'register_court', 'register_number', 'vat_id',
            'responsible_person', 'custom_text',
        ];
        $keyMap = [
            'provider_name'       => 'impressum_provider_name',
            'provider_legal_form' => 'impressum_legal_form',
            'street'              => 'impressum_street',
            'zip_city'            => 'impressum_zip_city',
            'country'             => 'impressum_country',
            'email'               => 'impressum_email',
            'phone'               => 'impressum_phone',
            'register_court'      => 'impressum_register_court',
            'register_number'     => 'impressum_register_number',
            'vat_id'              => 'impressum_vat_id',
            'responsible_person'  => 'impressum_responsible',
            'custom_text'         => 'impressum_custom_text',
        ];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::set($keyMap[$field], (string)$request->input($field));
            }
        }
        AuditLog::create([
            'user_id'    => $request->user()->id,
            'username'   => $request->user()->username,
            'action'     => 'impressum_updated',
            'ip_address' => $request->ip(),
        ]);
        return response()->json(['message' => 'Impressum updated']);
    }

    // Public Impressum endpoint (no auth — must be accessible to all visitors)
    public function publicImpress()
    {
        return response()->json([
            'provider_name'       => Setting::get('impressum_provider_name', ''),
            'provider_legal_form' => Setting::get('impressum_legal_form', ''),
            'street'              => Setting::get('impressum_street', ''),
            'zip_city'            => Setting::get('impressum_zip_city', ''),
            'country'             => Setting::get('impressum_country', 'Deutschland'),
            'email'               => Setting::get('impressum_email', ''),
            'phone'               => Setting::get('impressum_phone', ''),
            'register_court'      => Setting::get('impressum_register_court', ''),
            'register_number'     => Setting::get('impressum_register_number', ''),
            'vat_id'              => Setting::get('impressum_vat_id', ''),
            'responsible_person'  => Setting::get('impressum_responsible', ''),
            'custom_text'         => Setting::get('impressum_custom_text', ''),
        ]);
    }
}
