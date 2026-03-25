<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesExternalUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AdminSettingsController extends Controller
{
    use ValidatesExternalUrls;

    /**
     * Allowlist of setting keys that may be written through the public API.
     * Shared by updateSettings and importBackup to prevent drift.
     */
    private const ALLOWED_SETTING_KEYS = [
        'company_name', 'use_case', 'self_registration', 'license_plate_mode',
        'display_name_format', 'max_bookings_per_day', 'allow_guest_bookings',
        'auto_release_minutes', 'require_vehicle', 'waitlist_enabled',
        'min_booking_duration_hours', 'max_booking_duration_hours',
        'credits_enabled', 'credits_per_booking',
        'primary_color', 'secondary_color',
    ];

    public function getSettings(Request $request): JsonResponse
    {

        $settings = Setting::query()->pluck('value', 'key')->all();
        $defaults = [
            'company_name' => 'ParkHub',
            'use_case' => 'company',
            'self_registration' => 'true',
            'license_plate_mode' => 'optional',
            'display_name_format' => 'first_name',
            'max_bookings_per_day' => '3',
            'allow_guest_bookings' => 'false',
            'auto_release_minutes' => '30',
            'require_vehicle' => 'false',
            'waitlist_enabled' => 'true',
            'min_booking_duration_hours' => '0',
            'max_booking_duration_hours' => '0',
            'credits_enabled' => 'false',
            'credits_per_booking' => '1',
            'primary_color' => '#d97706',
            'secondary_color' => '#475569',
        ];

        return response()->json(array_merge($defaults, $settings));
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {

        // Allowlist of keys that can be set via this endpoint.
        // Prevents injection of arbitrary/internal settings keys.
        foreach ($request->only(self::ALLOWED_SETTING_KEYS) as $key => $value) {
            // Normalize booleans to 'true'/'false' strings for consistent Setting::get() checks
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            Setting::set($key, is_array($value) ? json_encode($value) : (string) $value);
        }

        return response()->json(['message' => 'Settings updated']);
    }

    public function getBranding(Request $request): JsonResponse
    {

        return response()->json([
            'company_name' => Setting::get('company_name', 'ParkHub'),
            'primary_color' => Setting::get('brand_primary_color', '#3b82f6'),
            'logo_url' => Setting::get('logo_url', null),
            'use_case' => Setting::get('use_case', 'corporate'),
        ]);
    }

    public function updateBranding(Request $request): JsonResponse
    {

        $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'primary_color' => ['sometimes', 'string', 'max:7', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_url' => 'sometimes|nullable|string|max:2048',
            'use_case' => 'sometimes|string|in:company,residential,shared,rental,personal',
        ]);
        foreach (['company_name', 'primary_color', 'logo_url', 'use_case'] as $key) {
            if ($request->has($key)) {
                Setting::set('brand_'.$key, $request->input($key));
            }
        }
        if ($request->has('company_name')) {
            Setting::set('company_name', $request->input('company_name'));
        }
        if ($request->has('use_case')) {
            Setting::set('use_case', $request->input('use_case'));
        }

        return response()->json(['message' => 'Branding updated']);
    }

    public function uploadBrandingLogo(Request $request): JsonResponse
    {

        $request->validate(['logo' => 'required|image|mimes:jpeg,png,gif,svg,webp|max:2048']);
        $path = $request->file('logo')->store('branding', 'public');
        Setting::set('logo_url', '/storage/'.$path);

        return response()->json(['logo_url' => '/storage/'.$path]);
    }

    public function serveBrandingLogo(Request $request)
    {
        $logoUrl = Setting::get('logo_url', null);
        if (! $logoUrl) {
            // Return default SVG icon
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#3b82f6"><circle cx="32" cy="32" r="30"/><text x="32" y="42" text-anchor="middle" font-size="32" fill="white" font-family="Arial" font-weight="bold">P</text></svg>';

            return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
        }
        if (str_starts_with($logoUrl, '/storage/')) {
            $filePath = storage_path('app/public'.substr($logoUrl, 8));
            if (file_exists($filePath)) {
                return response()->file($filePath, ['Cache-Control' => 'public, max-age=86400']);
            }
        }

        return redirect($logoUrl);
    }

    public function getPrivacy(Request $request): JsonResponse
    {

        return response()->json([
            'policy_text' => Setting::get('privacy_policy', ''),
            'data_retention_days' => (int) Setting::get('data_retention_days', 365),
            'gdpr_enabled' => Setting::get('gdpr_enabled', 'true') === 'true',
        ]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {

        foreach (['policy_text', 'data_retention_days', 'gdpr_enabled'] as $key) {
            if ($request->has($key)) {
                Setting::set('privacy_'.str_replace('_text', '_policy', $key), (string) $request->input($key));
            }
        }
        if ($request->has('policy_text')) {
            Setting::set('privacy_policy', $request->input('policy_text'));
        }
        if ($request->has('data_retention_days')) {
            Setting::set('data_retention_days', $request->input('data_retention_days'));
        }
        if ($request->has('gdpr_enabled')) {
            Setting::set('gdpr_enabled', $request->boolean('gdpr_enabled') ? 'true' : 'false');
        }

        return response()->json(['message' => 'Privacy settings updated']);
    }

    public function getAutoReleaseSettings(Request $request): JsonResponse
    {

        return response()->json([
            'enabled' => Setting::get('auto_release_enabled', 'false') === 'true',
            'timeout_minutes' => (int) Setting::get('auto_release_timeout', 30),
        ]);
    }

    public function updateAutoReleaseSettings(Request $request): JsonResponse
    {

        if ($request->has('enabled')) {
            Setting::set('auto_release_enabled', $request->boolean('enabled') ? 'true' : 'false');
        }
        if ($request->has('timeout_minutes')) {
            Setting::set('auto_release_timeout', $request->input('timeout_minutes'));
        }

        return response()->json(['message' => 'Auto-release settings updated']);
    }

    public function getEmailSettings(Request $request): JsonResponse
    {

        return response()->json([
            'smtp_host' => Setting::get('smtp_host', ''),
            'smtp_port' => (int) Setting::get('smtp_port', 587),
            'smtp_user' => Setting::get('smtp_user', ''),
            'from_email' => Setting::get('from_email', ''),
            'from_name' => Setting::get('from_name', 'ParkHub'),
            'enabled' => Setting::get('email_enabled', 'false') === 'true',
        ]);
    }

    public function updateEmailSettings(Request $request): JsonResponse
    {

        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'from_email', 'from_name'] as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }
        // Encrypt SMTP password at rest
        if ($request->has('smtp_pass')) {
            Setting::set('smtp_pass', Crypt::encryptString($request->input('smtp_pass')));
        }
        if ($request->has('enabled')) {
            Setting::set('email_enabled', $request->boolean('enabled') ? 'true' : 'false');
        }

        return response()->json(['message' => 'Email settings updated']);
    }

    public function getWebhookSettings(Request $request): JsonResponse
    {

        $hooks = Webhook::all();

        return response()->json($hooks);
    }

    public function updateWebhookSettings(Request $request): JsonResponse
    {

        if ($request->has('webhooks')) {
            Webhook::query()->delete();
            foreach ($request->input('webhooks') as $hook) {
                $url = $hook['url'] ?? '';
                // SSRF protection: reject private/internal IP ranges
                if (! $this->isExternalUrl($url)) {
                    return response()->json(['error' => 'SSRF_BLOCKED', 'message' => 'Webhook URL must not target internal/private networks'], 422);
                }
                Webhook::create([
                    'url' => $url,
                    'events' => $hook['events'] ?? [],
                    'secret' => $hook['secret'] ?? null,
                    'active' => $hook['active'] ?? true,
                ]);
            }
        }

        return response()->json(['message' => 'Webhook settings updated']);
    }

    // ── Impressum (DDG § 5 — legally required for German operators) ──────────

    public function getImpress(Request $request)
    {

        return response()->json([
            'provider_name' => Setting::get('impressum_provider_name', ''),
            'provider_legal_form' => Setting::get('impressum_legal_form', ''),
            'street' => Setting::get('impressum_street', ''),
            'zip_city' => Setting::get('impressum_zip_city', ''),
            'country' => Setting::get('impressum_country', 'Deutschland'),
            'email' => Setting::get('impressum_email', ''),
            'phone' => Setting::get('impressum_phone', ''),
            'register_court' => Setting::get('impressum_register_court', ''),
            'register_number' => Setting::get('impressum_register_number', ''),
            'vat_id' => Setting::get('impressum_vat_id', ''),
            'responsible_person' => Setting::get('impressum_responsible', ''),
            'custom_text' => Setting::get('impressum_custom_text', ''),
        ]);
    }

    public function updateImpress(Request $request)
    {

        $fields = [
            'provider_name', 'provider_legal_form', 'street', 'zip_city', 'country',
            'email', 'phone', 'register_court', 'register_number', 'vat_id',
            'responsible_person', 'custom_text',
        ];
        $keyMap = [
            'provider_name' => 'impressum_provider_name',
            'provider_legal_form' => 'impressum_legal_form',
            'street' => 'impressum_street',
            'zip_city' => 'impressum_zip_city',
            'country' => 'impressum_country',
            'email' => 'impressum_email',
            'phone' => 'impressum_phone',
            'register_court' => 'impressum_register_court',
            'register_number' => 'impressum_register_number',
            'vat_id' => 'impressum_vat_id',
            'responsible_person' => 'impressum_responsible',
            'custom_text' => 'impressum_custom_text',
        ];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::set($keyMap[$field], (string) $request->input($field));
            }
        }
        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'impressum_updated',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Impressum updated']);
    }

    // Route-named aliases expected by api.php
    public function getImpressum(Request $request)
    {
        return $this->getImpress($request);
    }

    public function updateImpressum(Request $request)
    {
        return $this->updateImpress($request);
    }

    // Public Impressum endpoint (no auth — must be accessible to all visitors)
    public function publicImpress()
    {
        return response()->json([
            'provider_name' => Setting::get('impressum_provider_name', ''),
            'provider_legal_form' => Setting::get('impressum_legal_form', ''),
            'street' => Setting::get('impressum_street', ''),
            'zip_city' => Setting::get('impressum_zip_city', ''),
            'country' => Setting::get('impressum_country', 'Deutschland'),
            'email' => Setting::get('impressum_email', ''),
            'phone' => Setting::get('impressum_phone', ''),
            'register_court' => Setting::get('impressum_register_court', ''),
            'register_number' => Setting::get('impressum_register_number', ''),
            'vat_id' => Setting::get('impressum_vat_id', ''),
            'responsible_person' => Setting::get('impressum_responsible', ''),
            'custom_text' => Setting::get('impressum_custom_text', ''),
        ]);
    }

    public function resetDatabase(Request $request)
    {

        $request->validate(['confirm' => 'required|in:RESET']);
        // Delete all user data but keep admin account
        $admin = $request->user();
        Booking::query()->delete();
        Absence::query()->delete();
        Vehicle::query()->delete();
        ParkingSlot::query()->update(['status' => 'available']);
        User::where('id', '!=', $admin->id)->delete();
        AuditLog::log([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'action' => 'database_reset',
        ]);

        return response()->json(['message' => 'Database reset. All user data deleted.']);
    }

    /**
     * GET /api/v1/admin/backup
     * Export all settings, users, lots, slots, and bookings as JSON.
     */
    public function exportBackup(Request $request): JsonResponse
    {
        $data = [
            'exported_at' => now()->toISOString(),
            'version' => SystemController::appVersion(),
            'settings' => Setting::query()->pluck('value', 'key'),
            'users' => User::all()->makeHidden(['password', 'remember_token']),
            'lots' => ParkingLot::with('slots')->get(),
            'bookings' => Booking::limit(10000)->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/admin/restore
     * Restore settings from a backup JSON payload.
     */
    public function importBackup(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        // Strip any keys not on the allowlist before writing to the settings store.
        $imported = 0;
        foreach (array_intersect_key($request->settings, array_flip(self::ALLOWED_SETTING_KEYS)) as $key => $value) {
            Setting::set($key, is_array($value) ? json_encode($value) : (string) $value);
            $imported++;
        }

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'settings_restored',
            'details' => ['settings_count' => $imported],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['settings_imported' => $imported],
        ]);
    }

    private static function useCaseTheme(string $key): array
    {
        $themes = [
            'company' => [
                'key' => 'company', 'name' => 'Company Parking',
                'description' => 'Employee parking for offices and campuses',
                'icon' => 'buildings', 'primary_color' => '#0d9488', 'accent_color' => '#0ea5e9',
                'terminology' => ['user' => 'Employee', 'users' => 'Employees', 'lot' => 'Parking Area', 'slot' => 'Spot', 'booking' => 'Reservation', 'department' => 'Department'],
                'features_emphasis' => ['team_calendar', 'absence_tracking', 'departments', 'credits'],
            ],
            'residential' => [
                'key' => 'residential', 'name' => 'Residential Parking',
                'description' => 'Parking for apartment buildings and housing complexes',
                'icon' => 'house-line', 'primary_color' => '#059669', 'accent_color' => '#84cc16',
                'terminology' => ['user' => 'Resident', 'users' => 'Residents', 'lot' => 'Parking Area', 'slot' => 'Space', 'booking' => 'Reservation', 'department' => 'Unit'],
                'features_emphasis' => ['guest_parking', 'long_term_bookings', 'public_display'],
            ],
            'shared' => [
                'key' => 'shared', 'name' => 'Shared Parking',
                'description' => 'Community or co-working parking spaces',
                'icon' => 'users-three', 'primary_color' => '#7c3aed', 'accent_color' => '#06b6d4',
                'terminology' => ['user' => 'Member', 'users' => 'Members', 'lot' => 'Parking Zone', 'slot' => 'Spot', 'booking' => 'Booking', 'department' => 'Group'],
                'features_emphasis' => ['quick_book', 'waitlist', 'public_display', 'qr_codes'],
            ],
            'rental' => [
                'key' => 'rental', 'name' => 'Rental / Commercial',
                'description' => 'Paid parking for customers and tenants',
                'icon' => 'currency-circle-dollar', 'primary_color' => '#2563eb', 'accent_color' => '#f59e0b',
                'terminology' => ['user' => 'Customer', 'users' => 'Customers', 'lot' => 'Parking Facility', 'slot' => 'Bay', 'booking' => 'Rental', 'department' => 'Account'],
                'features_emphasis' => ['invoicing', 'pricing', 'revenue_reports', 'guest_bookings'],
            ],
            'personal' => [
                'key' => 'personal', 'name' => 'Personal / Private',
                'description' => 'Private parking for family and friends',
                'icon' => 'car-simple', 'primary_color' => '#e11d48', 'accent_color' => '#f97316',
                'terminology' => ['user' => 'Person', 'users' => 'People', 'lot' => 'Driveway', 'slot' => 'Spot', 'booking' => 'Booking', 'department' => 'Group'],
                'features_emphasis' => ['simple_booking', 'guest_parking'],
            ],
        ];

        return $themes[$key] ?? $themes['personal'];
    }

    public function getUseCase(Request $request)
    {

        $current = Setting::get('use_case', 'company');
        $allOptions = array_map(fn ($k) => self::useCaseTheme($k), ['company', 'residential', 'shared', 'rental', 'personal']);

        return response()->json([
            'success' => true,
            'data' => ['current' => self::useCaseTheme($current), 'available' => $allOptions],
        ]);
    }

    public static function getPublicTheme()
    {
        $useCase = Setting::get('use_case', 'company');
        $companyName = Setting::get('company_name', 'ParkHub');

        return response()->json([
            'success' => true,
            'data' => ['use_case' => self::useCaseTheme($useCase), 'company_name' => $companyName],
        ]);
    }
}
