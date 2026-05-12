<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\Setting;
use App\Services\ModuleRegistry;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use JsonException;

class PublicController extends Controller
{
    /**
     * Return a keyed map of lot_id => occupied count using a single aggregation query.
     */
    private function occupiedCountsByLot(): array
    {
        $now = now();

        return Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->select('lot_id', DB::raw('COUNT(*) as occupied'))
            ->groupBy('lot_id')
            ->pluck('occupied', 'lot_id')
            ->all();
    }

    public function occupancy()
    {
        // withCount('slots') loads slot totals in a single query (no N+1)
        $lots = ParkingLot::withCount('slots')->get();
        $occupied = $this->occupiedCountsByLot();

        $result = $lots->map(function ($lot) use ($occupied) {
            $totalSlots = $lot->slots_count;
            $occupiedCount = $occupied[$lot->id] ?? 0;

            return [
                'lot_id' => $lot->id,
                'lot_name' => $lot->name,
                'total' => $totalSlots,
                'occupied' => $occupiedCount,
                'available' => $totalSlots - $occupiedCount,
                'percentage' => $totalSlots > 0 ? round(($occupiedCount / $totalSlots) * 100) : 0,
            ];
        });

        return response()->json($result);
    }

    public function display()
    {
        $lots = ParkingLot::withCount('slots')->get();
        $occupied = $this->occupiedCountsByLot();

        $result = $lots->map(function ($lot) use ($occupied) {
            $totalSlots = $lot->slots_count;
            $occupiedCount = $occupied[$lot->id] ?? 0;

            return [
                'id' => $lot->id,
                'name' => $lot->name,
                'total' => $totalSlots,
                'occupied' => $occupiedCount,
                'available' => $totalSlots - $occupiedCount,
            ];
        });

        $announcements = Announcement::where('active', true)->get();
        $companyName = Setting::get('company_name', 'ParkHub');

        return response()->json([
            'company_name' => $companyName,
            'lots' => $result,
            'announcements' => $announcements,
        ]);
    }

    public function activeAnnouncements(): JsonResponse
    {
        $announcements = Announcement::where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($announcements);
    }

    public function activeAnnouncementsWrapped(): JsonResponse
    {
        $announcements = Announcement::where('active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $announcements,
            'error' => null,
            'meta' => null,
        ]);
    }

    public function vapidKey(): JsonResponse
    {
        return response()->json(['publicKey' => Setting::get('vapid_public_key', '')]);
    }

    public function branding(): JsonResponse
    {
        $s = Setting::pluck('value', 'key')->toArray();

        return response()->json([
            'company_name' => $s['company_name'] ?? 'ParkHub',
            'primary_color' => $s['primary_color'] ?? '#d97706',
            'secondary_color' => $s['secondary_color'] ?? '#475569',
            'logo_url' => $s['logo_url'] ?? null,
            'favicon_url' => null,
            'login_background_color' => '#0f172a',
            'custom_css' => null,
        ]);
    }

    public function legalPrivacy(): JsonResponse
    {
        return response()->json(['type' => 'privacy', 'url' => '/datenschutz']);
    }

    public function legalImpressum(): JsonResponse
    {
        return response()->json(['type' => 'impressum', 'url' => '/impressum']);
    }

    public function healthCheck(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'version' => SystemController::appVersion()]);
    }

    #[Header('Location', 'Scramble documentation UI URL.', type: 'string', status: 302, example: '/docs/api')]
    #[Response(302, 'Redirects to the Scramble documentation UI.', mediaType: 'text/html', type: 'string')]
    public function apiDocs(): RedirectResponse
    {
        /**
         * @status 302
         *
         * @body string
         */
        return redirect('/docs/api');
    }

    #[Header('Location', 'Scramble OpenAPI JSON URL.', type: 'string', status: 302, example: '/docs/api.json')]
    #[Response(302, 'Redirects to the Scramble OpenAPI JSON document.', mediaType: 'text/html', type: 'string')]
    public function openApiSpecification(): RedirectResponse
    {
        /**
         * @status 302
         *
         * @body string
         */
        return redirect('/docs/api.json');
    }

    #[Response(200, 'Postman collection JSON.', type: 'array{info: array, item: array}')]
    #[Response(404, 'Postman collection asset not found.', type: 'array{error: string, message: string}')]
    #[Response(500, 'Postman collection asset is unreadable or invalid.', type: 'array{error: string, message: string}')]
    public function postmanCollection(): JsonResponse
    {
        $path = resource_path('docs/ParkHub.postman_collection.json');

        if (! is_readable($path)) {
            return response()->json([
                'error' => 'DOCS_ASSET_NOT_FOUND',
                'message' => 'Postman collection asset is not available.',
            ], 404);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return response()->json([
                'error' => 'DOCS_ASSET_UNREADABLE',
                'message' => 'Postman collection asset could not be read.',
            ], 500);
        }

        try {
            $collection = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json([
                'error' => 'DOCS_ASSET_INVALID',
                'message' => 'Postman collection asset contains invalid JSON.',
            ], 500);
        }

        if (! is_array($collection)) {
            return response()->json([
                'error' => 'DOCS_ASSET_INVALID',
                'message' => 'Postman collection asset must be a JSON object.',
            ], 500);
        }

        return response()->json($collection);
    }

    public function updateCheck(): JsonResponse
    {
        return response()->json(['update_available' => false, 'current_version' => SystemController::appVersion()]);
    }

    public function featureFlags(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['enabled' => ['micro_animations', 'credits']],
            'error' => null,
            'meta' => null,
        ]);
    }

    public function adminFeatureFlags(): JsonResponse
    {
        $available = [
            ['id' => 'micro_animations', 'name' => 'Micro Animations', 'description' => 'Subtle hover/tap animations'],
            ['id' => 'credits', 'name' => 'Credits System', 'description' => 'Credit-based booking'],
        ];

        return response()->json([
            'success' => true,
            'data' => ['enabled' => ['micro_animations', 'credits'], 'available' => $available],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/discover
     * Handshake/discovery endpoint — returns API version, capabilities, and available modules.
     */
    public function discover(): JsonResponse
    {
        $enabledModules = ModuleRegistry::enabledMap();
        $realtimeEnabled = $enabledModules['realtime'] ?? false;
        $pushConfigured = ! empty(config('services.webpush.vapid_public_key'));

        $modules = collect($enabledModules)
            ->filter()
            ->keys()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => Setting::get('company_name', 'ParkHub'),
                'version' => SystemController::appVersion(),
                'api_version' => 'v1',
                'modules' => $modules,
                'capabilities' => [
                    'auth' => 'sanctum',
                    'realtime' => $realtimeEnabled,
                    'realtime_transport' => $realtimeEnabled ? 'sse' : null,
                    'push' => ($enabledModules['push'] ?? false) && $pushConfigured,
                    'email' => $enabledModules['email'] ?? true,
                    'demo_mode' => (bool) config('parkhub.demo_mode'),
                ],
                'endpoints' => [
                    'auth' => '/api/v1/auth/login',
                    'health' => '/api/v1/health',
                    'docs' => '/api/v1/docs',
                    'openapi' => '/api/v1/docs/openapi.json',
                    'postman' => '/api/v1/docs/postman.json',
                ],
            ],
            'error' => null,
            'meta' => null,
        ]);
    }
}
