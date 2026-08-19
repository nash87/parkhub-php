<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesExternalUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSettingsBackupRequest;
use App\Http\Requests\ResetDatabaseRequest;
use App\Http\Requests\UpdateBrandingRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Requests\UploadBrandingLogoRequest;
use App\Models\Setting;
use App\Models\Webhook;
use App\Services\Admin\AdminSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    use ValidatesExternalUrls;

    public function __construct(private readonly AdminSettingsService $service) {}

    public function getSettings(Request $request): JsonResponse
    {
        $settings = Setting::query()->pluck('value', 'key')->all();
        $defaults = [
            'company_name' => 'ParkHub',
            'use_case' => 'company',
            'self_registration' => 'true',
            'license_plate_mode' => 'optional',
            'display_name_format' => 'first_name',
            'booking_visibility' => 'full',
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
        $this->service->updateSettings($request->only(AdminSettingsService::ALLOWED_SETTING_KEYS));

        return response()->json(['message' => 'Settings updated']);
    }

    public function getBranding(Request $request): JsonResponse
    {
        Setting::preload(['company_name', 'brand_primary_color', 'logo_url', 'use_case']);

        return response()->json([
            'company_name' => Setting::get('company_name', 'ParkHub'),
            'primary_color' => Setting::get('brand_primary_color', '#3b82f6'),
            'logo_url' => Setting::get('logo_url', null),
            'use_case' => Setting::get('use_case', 'corporate'),
        ]);
    }

    public function updateBranding(UpdateBrandingRequest $request): JsonResponse
    {
        $this->service->updateBranding($request->only(['company_name', 'primary_color', 'logo_url', 'use_case']));

        return response()->json(['message' => 'Branding updated']);
    }

    public function uploadBrandingLogo(UploadBrandingLogoRequest $request): JsonResponse
    {
        $path = $request->file('logo')->store('branding', 'public');
        $url = $this->service->storeBrandingLogo($path);

        return response()->json(['logo_url' => $url]);
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
        Setting::preload(['privacy_policy', 'data_retention_days', 'gdpr_enabled']);

        return response()->json([
            'policy_text' => Setting::get('privacy_policy', ''),
            'data_retention_days' => (int) Setting::get('data_retention_days', 365),
            'gdpr_enabled' => Setting::get('gdpr_enabled', 'true') === 'true',
        ]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $payload = [];
        if ($request->has('policy_text')) {
            $payload['policy_text'] = $request->input('policy_text');
        }
        if ($request->has('data_retention_days')) {
            $payload['data_retention_days'] = $request->input('data_retention_days');
        }
        if ($request->has('gdpr_enabled')) {
            $payload['gdpr_enabled'] = $request->input('gdpr_enabled');
        }
        $this->service->updatePrivacy($payload);

        return response()->json(['message' => 'Privacy settings updated']);
    }

    public function getAutoReleaseSettings(Request $request): JsonResponse
    {
        Setting::preload(['auto_release_enabled', 'auto_release_timeout']);

        return response()->json([
            'enabled' => Setting::get('auto_release_enabled', 'false') === 'true',
            'timeout_minutes' => (int) Setting::get('auto_release_timeout', 30),
        ]);
    }

    public function updateAutoReleaseSettings(Request $request): JsonResponse
    {
        $payload = [];
        if ($request->has('enabled')) {
            $payload['enabled'] = $request->input('enabled');
        }
        if ($request->has('timeout_minutes')) {
            $payload['timeout_minutes'] = $request->input('timeout_minutes');
        }
        $this->service->updateAutoRelease($payload);

        return response()->json(['message' => 'Auto-release settings updated']);
    }

    public function getEmailSettings(Request $request): JsonResponse
    {
        Setting::preload(['smtp_host', 'smtp_port', 'smtp_user', 'from_email', 'from_name', 'email_enabled']);

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
        $payload = [];
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'from_email', 'from_name'] as $key) {
            if ($request->has($key)) {
                $payload[$key] = $request->input($key);
            }
        }
        if ($request->has('smtp_pass')) {
            $payload['smtp_pass'] = $request->input('smtp_pass');
        }
        if ($request->has('enabled')) {
            $payload['enabled'] = $request->input('enabled');
        }
        $this->service->updateEmail($payload);

        return response()->json(['message' => 'Email settings updated']);
    }

    public function getWebhookSettings(Request $request): JsonResponse
    {
        return response()->json(Webhook::all());
    }

    public function updateWebhookSettings(Request $request): JsonResponse
    {
        if (! $request->has('webhooks')) {
            return response()->json(['message' => 'Webhook settings updated']);
        }

        $webhooks = (array) $request->input('webhooks');
        $rejected = $this->service->replaceWebhooks($webhooks, fn (string $url) => $this->isExternalUrl($url));

        if ($rejected !== null) {
            return response()->json([
                'error' => 'SSRF_BLOCKED',
                'message' => 'Webhook URL must not target internal/private networks',
            ], 422);
        }

        return response()->json(['message' => 'Webhook settings updated']);
    }

    // ── Impressum (DDG § 5 — legally required for German operators) ──────────

    public function getImpress(Request $request)
    {
        Setting::preload([
            'impressum_provider_name', 'impressum_legal_form', 'impressum_street',
            'impressum_zip_city', 'impressum_country', 'impressum_email',
            'impressum_phone', 'impressum_register_court', 'impressum_register_number',
            'impressum_vat_id', 'impressum_responsible', 'impressum_custom_text',
        ]);

        return response()->json($this->impressumPayload());
    }

    public function updateImpress(Request $request)
    {
        $this->service->updateImpressum(
            $request->only([
                'provider_name', 'provider_legal_form', 'street', 'zip_city', 'country',
                'email', 'phone', 'register_court', 'register_number', 'vat_id',
                'responsible_person', 'custom_text',
            ]),
            $request->user(),
            $request->ip(),
        );

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
        Setting::preload([
            'impressum_provider_name', 'impressum_legal_form', 'impressum_street',
            'impressum_zip_city', 'impressum_country', 'impressum_email',
            'impressum_phone', 'impressum_register_court', 'impressum_register_number',
            'impressum_vat_id', 'impressum_responsible', 'impressum_custom_text',
        ]);

        return response()->json($this->impressumPayload());
    }

    public function resetDatabase(ResetDatabaseRequest $request)
    {
        $this->service->resetDatabase($request->user());

        return response()->json(['message' => 'Database reset. All user data deleted.']);
    }

    /**
     * GET /api/v1/admin/backup
     * Export all settings, users, lots, slots, and bookings as JSON.
     */
    public function exportBackup(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->exportBackup(SystemController::appVersion()),
        ]);
    }

    /**
     * POST /api/v1/admin/restore
     * Restore settings from a backup JSON payload.
     */
    public function importBackup(ImportSettingsBackupRequest $request): JsonResponse
    {
        $imported = $this->service->importBackup(
            $request->settings,
            $request->user(),
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'data' => ['settings_imported' => $imported],
        ]);
    }

    public function getUseCase(Request $request)
    {
        $current = Setting::get('use_case', 'company');

        return response()->json([
            'success' => true,
            'data' => [
                'current' => $this->service->useCaseTheme(is_string($current) ? $current : 'company'),
                'available' => $this->service->availableUseCases(),
            ],
        ]);
    }

    public function getPublicTheme()
    {
        Setting::preload(['use_case', 'company_name']);
        $useCase = Setting::get('use_case', 'company');
        $companyName = Setting::get('company_name', 'ParkHub');

        return response()->json([
            'success' => true,
            'data' => [
                'use_case' => $this->service->useCaseTheme(is_string($useCase) ? $useCase : 'company'),
                'company_name' => $companyName,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function impressumPayload(): array
    {
        return [
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
        ];
    }
}
