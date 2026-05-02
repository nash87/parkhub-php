<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateModuleConfigRequest;
use App\Http\Requests\UpdateModuleRuntimeStateRequest;
use App\Services\ModuleRegistry;
use App\Services\Modules\ModuleConfigurationService;
use App\Services\Modules\ModuleConfigurationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modules API — backwards-compatible envelope plus enriched metadata.
 *
 * Historic contract: `GET /api/v1/modules` returned `{modules: {name: bool}}`.
 * That map is still there. The new `module_info` array carries the
 * ModuleInfo-shaped records the shared frontend (parkhub-web) uses to
 * render the Modules Dashboard and to auto-register command-palette
 * entries per active module.
 *
 * Responses are pre-shaped with `success`/`data`/`error` so the global
 * ApiResponseWrapper skips re-wrapping — mirrors the pattern used by
 * EVChargingController and PluginController. The `data` payload is the
 * `{modules, module_info, version}` envelope the Rust backend also
 * ships, so the shared parkhub-web client sees the same shape from
 * either backend.
 *
 * The JSON-Schema validation + runtime-toggle audit flow lives in
 * ModuleConfigurationService (T-1742 pass 4). Each controller action
 * below inlines its response envelope literally so Scramble can
 * introspect the exact shape per-path — never route the happy path
 * through a helper with a heterogeneous union return type, or the
 * generated OpenAPI doc collapses to generic `array|null`.
 */
class ModuleController extends Controller
{
    public function __construct(private readonly ModuleConfigurationService $service) {}

    /**
     * GET /api/v1/modules
     *
     * Response payload (inside the global wrapper's `data` key):
     *   {
     *     "modules":     { "bookings": true, "stripe": false, ... },
     *     "module_info": [ { name, category, description, ... }, ... ],
     *     "version":     "4.12.0"
     *   }
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'modules' => ModuleRegistry::enabledMap(),
                'module_info' => ModuleRegistry::all(),
                'version' => SystemController::appVersion(),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/modules/info
     *
     * Legacy Rust-compatible alias consumed by older shared parkhub-web
     * builds. It keeps `modules` / `module_info` at the top level while also
     * exposing `data` as the ModuleInfo array for the shared React view.
     */
    public function info(): JsonResponse
    {
        $moduleInfo = ModuleRegistry::all();

        return response()->json([
            'success' => true,
            'data' => $moduleInfo,
            'modules' => ModuleRegistry::enabledMap(),
            'module_info' => $moduleInfo,
            'version' => SystemController::appVersion(),
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/modules/{name}
     *
     * Returns the single ModuleInfo-shaped record or a 404 when the
     * slug is unknown.
     */
    public function show(string $name): JsonResponse
    {
        $info = ModuleRegistry::get($name);

        if ($info === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_NOT_FOUND',
                    'message' => "Unknown module '{$name}'",
                ],
                'meta' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $info,
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * PATCH /api/v1/admin/modules/{name}
     *
     * Admin-only endpoint to hot-toggle a module without a redeploy.
     * The module must be on the registry's runtime-toggleable allowlist
     * (security / payment / tenancy modules stay env-driven).
     *
     * Response codes:
     *   - 200: toggle persisted, returns the fresh ModuleInfo shape.
     *   - 404: module name unknown. `error.code = MODULE_NOT_FOUND`.
     *   - 409: module exists but is not runtime-toggleable.
     *     `error.code = MODULE_NOT_TOGGLEABLE`.
     *
     * Writes an `AuditLog` entry with the new state so an operator can
     * always reconstruct who flipped what, when — same pattern as the
     * Rust edition's `admin_modules::patch`.
     */
    public function updateRuntimeState(UpdateModuleRuntimeStateRequest $request, string $name): JsonResponse
    {
        $result = $this->service->toggleRuntimeState(
            $name,
            $request->boolean('runtime_enabled'),
            $this->actor($request),
        );

        if ($result->status === ModuleConfigurationStatus::NotFound) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_NOT_FOUND',
                    'message' => "Unknown module '{$name}'",
                ],
                'meta' => null,
            ], 404);
        }

        if ($result->status === ModuleConfigurationStatus::NotToggleable) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_NOT_TOGGLEABLE',
                    'message' => "Module '{$name}' is not runtime-toggleable; change its env flag to alter state.",
                ],
                'meta' => null,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => ModuleRegistry::get($name),
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/modules/{name}/config
     *
     * Return the module's JSON Schema plus the currently-persisted
     * values for each schema property. Shape:
     *   { schema: { ...JSON Schema 2020-12 object... },
     *     values: { default_theme: "dark", ... } }
     *
     * Only modules that declare a `config_schema` are editable via this
     * endpoint. Unknown module → 404; known but schema-less → 400. The
     * Rust edition returns the same codes so the shared frontend sees
     * one contract.
     */
    public function getConfig(Request $request, string $name): JsonResponse
    {
        if (ModuleRegistry::get($name) === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_NOT_FOUND',
                    'message' => "Unknown module '{$name}'",
                ],
                'meta' => null,
            ], 404);
        }

        $schema = ModuleRegistry::configSchema($name);

        if ($schema === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_HAS_NO_CONFIG_SCHEMA',
                    'message' => "Module '{$name}' does not expose a config schema; it is env-only.",
                ],
                'meta' => null,
            ], 400);
        }

        $values = $this->service->readPersistedValues($name);

        return response()->json([
            'success' => true,
            'data' => [
                'schema' => $schema,
                'values' => (object) $values,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * PATCH /api/v1/modules/{name}/config
     *
     * Validate the payload against the module's JSON Schema via
     * opis/json-schema. On success, persist each property to the
     * `settings` table under `module.{name}.config.{key}` and emit an
     * AuditLog entry. On failure, return 422 with `{error.code:
     * CONFIG_VALIDATION_FAILED, details: [...]}` — the Rust edition
     * surfaces the exact same error envelope.
     *
     * Shape of the 200 response mirrors `getConfig`, so the frontend
     * can do a single-call round-trip without re-fetching.
     */
    public function updateConfig(UpdateModuleConfigRequest $request, string $name): JsonResponse
    {
        if (ModuleRegistry::get($name) === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_NOT_FOUND',
                    'message' => "Unknown module '{$name}'",
                ],
                'meta' => null,
            ], 404);
        }

        $schema = ModuleRegistry::configSchema($name);

        if ($schema === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_HAS_NO_CONFIG_SCHEMA',
                    'message' => "Module '{$name}' does not expose a config schema; it is env-only.",
                ],
                'meta' => null,
            ], 400);
        }

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);

        $result = $this->service->updateConfig($name, $schema, $values, $this->actor($request));

        if ($result->status === ModuleConfigurationStatus::ValidationFailed) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'CONFIG_VALIDATION_FAILED',
                    'message' => 'Config payload failed schema validation.',
                    'details' => $result->details,
                ],
                'meta' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'schema' => $schema,
                'values' => (object) $this->service->readPersistedValues($name),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * @return array{user_id: ?string, username: ?string, ip_address: ?string}
     */
    private function actor(Request $request): array
    {
        return [
            'user_id' => $request->user()?->id,
            'username' => $request->user()?->username,
            'ip_address' => $request->ip(),
        ];
    }
}
