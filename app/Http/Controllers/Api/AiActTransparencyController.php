<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin endpoints for EU AI Act Art. 50 transparency configuration.
 *
 * Setting `allocation_transparency_mode`:
 *  - `algorithmic` (default): weighted scoring / exact-cover solver runs;
 *    every response carries an `automated_decision` transparency notice.
 *  - `fifo_only`: algorithmic endpoints return 409 ALGORITHMIC_DISABLED so
 *    operators can opt their deployment out of the AI Act scope entirely
 *    (FIFO waitlist already exists as the rule-based alternative).
 *
 * Routes are guarded by `module:aiact` + `admin` middleware.
 */
final class AiActTransparencyController extends Controller
{
    public const string SETTING_KEY_SUFFIX = 'allocation_transparency_mode';

    public const string MODE_ALGORITHMIC = 'algorithmic';

    public const string MODE_FIFO_ONLY = 'fifo_only';

    public const array VALID_MODES = [self::MODE_ALGORITHMIC, self::MODE_FIFO_ONLY];

    /**
     * GET /api/v1/admin/aiact/transparency-mode
     *
     * Returns the current allocation transparency mode and its description.
     */
    public function getMode(): JsonResponse
    {
        $mode = $this->currentMode();

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => $mode,
                'description' => $this->modeDescription($mode),
                'valid_modes' => self::VALID_MODES,
                'law_applies_from' => '2026-08-02',
                'article' => 'EU AI Act Art. 50',
            ],
            'error' => null,
        ]);
    }

    /**
     * PUT /api/v1/admin/aiact/transparency-mode
     *
     * Updates the allocation transparency mode. Requires admin role.
     * Writes an audit log entry on every successful change.
     */
    public function putMode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', self::VALID_MODES)],
        ]);

        $mode = $validated['mode'];
        $previous = $this->currentMode();

        Setting::set(
            ModuleRegistry::configSettingKey('aiact', self::SETTING_KEY_SUFFIX),
            $mode
        );

        $actor = $request->user();
        AuditLog::log([
            'user_id' => $actor?->id,
            'username' => $actor === null ? null : ($actor->email ?: $actor->username),
            'action' => 'aiact_transparency_mode_updated',
            'event_type' => 'AiActTransparencyModeUpdated',
            'target_type' => 'aiact_settings',
            'target_id' => null,
            'ip_address' => $request->ip(),
            'details' => [
                'previous_mode' => $previous,
                'new_mode' => $mode,
                'setting_key' => ModuleRegistry::configSettingKey('aiact', self::SETTING_KEY_SUFFIX),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => $mode,
                'description' => $this->modeDescription($mode),
            ],
            'error' => null,
        ]);
    }

    /**
     * Read the current mode from settings, defaulting to `algorithmic`.
     */
    public static function currentMode(): string
    {
        $raw = Setting::get(
            ModuleRegistry::configSettingKey('aiact', self::SETTING_KEY_SUFFIX)
        );
        $mode = is_string($raw) ? trim($raw) : '';

        return in_array($mode, self::VALID_MODES, true) ? $mode : self::MODE_ALGORITHMIC;
    }

    private function modeDescription(string $mode): string
    {
        return match ($mode) {
            self::MODE_FIFO_ONLY => 'Algorithmic allocation endpoints are disabled. All allocation follows deterministic FIFO rules (Art. 50 opt-out).',
            default => 'Algorithmic allocation is active. Every decision response carries an automated_decision transparency notice per EU AI Act Art. 50.',
        };
    }
}
