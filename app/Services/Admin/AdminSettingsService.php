<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Webhook;
use Illuminate\Support\Facades\Crypt;

/**
 * Owns admin setting writes, branding/privacy/impressum mutation, database
 * reset + backup/restore flows extracted from AdminSettingsController
 * (T-1742, pass 3).
 *
 * Pure extraction — the allowlists, key renaming, impressum key map,
 * SMTP password encryption and audit log emission all match the
 * previous inline controller implementation. Controllers remain
 * responsible for FormRequest validation, admin gating and HTTP shaping.
 */
final class AdminSettingsService
{
    /**
     * Allowlist of setting keys that may be written via updateSettings
     * and importBackup. Shared by both paths to prevent drift.
     */
    public const array ALLOWED_SETTING_KEYS = [
        'company_name', 'use_case', 'self_registration', 'license_plate_mode',
        'display_name_format', 'booking_visibility', 'max_bookings_per_day', 'allow_guest_bookings',
        'auto_release_minutes', 'require_vehicle', 'waitlist_enabled',
        'min_booking_duration_hours', 'max_booking_duration_hours',
        'credits_enabled', 'credits_per_booking',
        'primary_color', 'secondary_color',
        // Multi-country VAT profile configuration (see App\Services\Tax).
        'tax_default_country', 'tax_seller_country',
    ];

    /**
     * Impressum (DDG § 5) request-key to storage-key map.
     */
    private const array IMPRESSUM_KEY_MAP = [
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

    /**
     * Apply a batch of general settings writes. Keys outside the
     * allowlist are silently ignored so arbitrary/internal settings
     * keys can never be injected from the API surface.
     *
     * @param  array<string, mixed>  $payload
     * @return int Number of setting rows written.
     */
    public function updateSettings(array $payload): int
    {
        $written = 0;

        foreach (array_intersect_key($payload, array_flip(self::ALLOWED_SETTING_KEYS)) as $key => $value) {
            // Normalize booleans to 'true'/'false' strings for consistent Setting::get() checks.
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            Setting::set($key, is_array($value) ? json_encode($value) : (string) $value);
            $written++;
        }

        return $written;
    }

    /**
     * Apply branding updates (company name, theme colors, logo, use case).
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateBranding(array $payload): void
    {
        foreach (['company_name', 'primary_color', 'logo_url', 'use_case'] as $key) {
            if (array_key_exists($key, $payload)) {
                Setting::set('brand_'.$key, (string) $payload[$key]);
            }
        }
        if (array_key_exists('company_name', $payload)) {
            Setting::set('company_name', (string) $payload['company_name']);
        }
        if (array_key_exists('use_case', $payload)) {
            Setting::set('use_case', (string) $payload['use_case']);
        }
    }

    /**
     * Persist an uploaded branding logo and record its public URL.
     */
    public function storeBrandingLogo(string $storedPath): string
    {
        $url = '/storage/'.$storedPath;
        Setting::set('logo_url', $url);

        return $url;
    }

    /**
     * Apply privacy/GDPR settings updates.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updatePrivacy(array $payload): void
    {
        foreach (['policy_text', 'data_retention_days', 'gdpr_enabled'] as $key) {
            if (array_key_exists($key, $payload)) {
                Setting::set('privacy_'.str_replace('_text', '_policy', $key), (string) $payload[$key]);
            }
        }
        if (array_key_exists('policy_text', $payload)) {
            Setting::set('privacy_policy', (string) $payload['policy_text']);
        }
        if (array_key_exists('data_retention_days', $payload)) {
            Setting::set('data_retention_days', (string) $payload['data_retention_days']);
        }
        if (array_key_exists('gdpr_enabled', $payload)) {
            $value = filter_var($payload['gdpr_enabled'], FILTER_VALIDATE_BOOLEAN);
            Setting::set('gdpr_enabled', $value ? 'true' : 'false');
        }
    }

    /**
     * Apply auto-release settings updates.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateAutoRelease(array $payload): void
    {
        if (array_key_exists('enabled', $payload)) {
            $value = filter_var($payload['enabled'], FILTER_VALIDATE_BOOLEAN);
            Setting::set('auto_release_enabled', $value ? 'true' : 'false');
        }
        if (array_key_exists('timeout_minutes', $payload)) {
            Setting::set('auto_release_timeout', (string) $payload['timeout_minutes']);
        }
    }

    /**
     * Apply email/SMTP settings updates. SMTP password (when provided)
     * is encrypted at rest via the application cipher.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateEmail(array $payload): void
    {
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'from_email', 'from_name'] as $key) {
            if (array_key_exists($key, $payload)) {
                Setting::set($key, (string) $payload[$key]);
            }
        }
        if (array_key_exists('smtp_pass', $payload)) {
            Setting::set('smtp_pass', Crypt::encryptString((string) $payload['smtp_pass']));
        }
        if (array_key_exists('enabled', $payload)) {
            $value = filter_var($payload['enabled'], FILTER_VALIDATE_BOOLEAN);
            Setting::set('email_enabled', $value ? 'true' : 'false');
        }
    }

    /**
     * Replace the full set of outbound webhooks with the provided list.
     *
     * Returns the first URL that failed SSRF validation, or null when
     * every URL was accepted. Caller shapes the 422 on rejection.
     *
     * @param  array<int, array<string, mixed>>  $webhooks
     * @param  callable(string): bool  $isExternalUrl
     */
    public function replaceWebhooks(array $webhooks, callable $isExternalUrl): ?string
    {
        foreach ($webhooks as $hook) {
            $url = (string) ($hook['url'] ?? '');
            if (! $isExternalUrl($url)) {
                return $url;
            }
        }

        Webhook::query()->delete();
        foreach ($webhooks as $hook) {
            Webhook::create([
                'url' => (string) ($hook['url'] ?? ''),
                'events' => $hook['events'] ?? [],
                'secret' => $hook['secret'] ?? null,
                'active' => $hook['active'] ?? true,
            ]);
        }

        return null;
    }

    /**
     * Apply impressum (DDG § 5) field updates and emit an audit log.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateImpressum(array $payload, User $admin, ?string $ip = null): void
    {
        foreach (self::IMPRESSUM_KEY_MAP as $field => $settingKey) {
            if (array_key_exists($field, $payload)) {
                Setting::set($settingKey, (string) $payload[$field]);
            }
        }

        AuditLog::log([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'action' => 'impressum_updated',
            'ip_address' => $ip,
        ]);
    }

    /**
     * Purge all user-facing data (bookings, absences, vehicles, users
     * except the invoking admin). Parking slots are returned to
     * `available`. Emits an audit log.
     *
     * @return array<string, int> Counts per deleted category.
     */
    public function resetDatabase(User $admin): array
    {
        $counts = [
            'bookings' => Booking::query()->count(),
            'absences' => Absence::query()->count(),
            'vehicles' => Vehicle::query()->count(),
            'users' => User::where('id', '!=', $admin->id)->count(),
        ];

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

        return $counts;
    }

    /**
     * Snapshot the exportable shared state — settings, users, lots
     * (with slots) and the most recent bookings — for backup.
     *
     * @return array<string, mixed>
     */
    public function exportBackup(string $appVersion): array
    {
        return [
            'exported_at' => now()->toISOString(),
            'version' => $appVersion,
            'settings' => Setting::query()->pluck('value', 'key'),
            'users' => User::all()->makeHidden(['password', 'remember_token']),
            'lots' => ParkingLot::with('slots')->get(),
            'bookings' => Booking::limit(10000)->get(),
        ];
    }

    /**
     * Restore settings from a backup payload, silently skipping any
     * key outside the allowlist. Emits an audit log with the count of
     * settings actually written.
     *
     * @param  array<string, mixed>  $settings
     * @return int Number of settings restored.
     */
    public function importBackup(array $settings, User $admin, ?string $ip = null): int
    {
        $imported = 0;

        foreach (array_intersect_key($settings, array_flip(self::ALLOWED_SETTING_KEYS)) as $key => $value) {
            Setting::set($key, is_array($value) ? json_encode($value) : (string) $value);
            $imported++;
        }

        AuditLog::log([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'action' => 'settings_restored',
            'details' => ['settings_count' => $imported],
            'ip_address' => $ip,
        ]);

        return $imported;
    }

    /**
     * Look up the theme descriptor for a use-case key. Unknown keys
     * fall back to the personal theme to match the legacy behaviour.
     *
     * @return array<string, mixed>
     */
    public function useCaseTheme(string $key): array
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

    /**
     * Full list of available use-case themes in the canonical order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableUseCases(): array
    {
        return array_map(
            fn (string $k) => $this->useCaseTheme($k),
            ['company', 'residential', 'shared', 'rental', 'personal'],
        );
    }
}
