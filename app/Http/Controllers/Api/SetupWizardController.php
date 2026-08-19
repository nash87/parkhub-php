<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SetupWizardController extends Controller
{
    /**
     * GET /api/v1/setup/wizard/status
     *
     * Check wizard completion state and which steps are done.
     */
    public function status(): JsonResponse
    {
        $steps = [];

        $companyDone = (bool) Setting::get('company_name');
        $steps[] = ['step' => 1, 'name' => 'company', 'completed' => $companyDone];

        $lotDone = ParkingLot::count() > 0;
        $steps[] = ['step' => 2, 'name' => 'lot', 'completed' => $lotDone];

        $usersDone = User::count() > 1; // more than the initial admin
        $steps[] = ['step' => 3, 'name' => 'users', 'completed' => $usersDone];

        $themeDone = (bool) Setting::get('wizard_theme');
        $steps[] = ['step' => 4, 'name' => 'theme', 'completed' => $themeDone];

        $allCompleted = filter_var(Setting::get('wizard_completed', false), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'success' => true,
            'data' => [
                'completed' => $allCompleted,
                'steps' => $steps,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * POST /api/v1/setup/wizard
     *
     * Process individual wizard steps (1-4).
     */
    public function store(Request $request): JsonResponse
    {
        // The setup routes are unauthenticated by design: during first run
        // there is no admin to authenticate as. That is only safe while the
        // instance is still unconfigured, so every mutating setup endpoint
        // has to close once setup is complete.
        //
        // `/setup`, `/setup/complete` and `/setup/change-password` each
        // carried this check; the wizard did not, so it stayed writable for
        // the whole life of a deployment — including step 3, which creates
        // active user accounts.
        if (filter_var(Setting::get('setup_completed', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'SETUP_COMPLETED', 'message' => 'Setup has already been completed'],
                'meta' => null,
            ], 403);
        }

        // T-1749-intentional: private step helpers — would need per-step routing refactor to benefit from FormRequest.
        $request->validate([
            'step' => 'required|integer|min:1|max:4',
        ]);

        $step = (int) $request->input('step');

        return match ($step) {
            1 => $this->processCompanyStep($request),
            2 => $this->processLotStep($request),
            3 => $this->processUsersStep($request),
            4 => $this->processThemeStep($request),
            default => response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'INVALID_STEP', 'message' => 'Invalid wizard step'],
                'meta' => null,
            ], 422),
        };
    }

    private function processCompanyStep(Request $request): JsonResponse
    {
        // T-1749-intentional: private step helpers — would need per-step routing refactor to benefit from FormRequest.
        $request->validate([
            'company_name' => 'required|string|max:255',
            'timezone' => 'nullable|string|max:100',
        ]);

        Setting::set('company_name', $request->input('company_name'));

        if ($request->input('timezone')) {
            Setting::set('timezone', $request->input('timezone'));
        }

        if ($request->input('logo_base64')) {
            Setting::set('logo_base64', $request->input('logo_base64'));
        }

        return response()->json([
            'success' => true,
            'data' => ['step' => 1, 'message' => 'Company info saved'],
            'error' => null,
            'meta' => null,
        ]);
    }

    private function processLotStep(Request $request): JsonResponse
    {
        // T-1749-intentional: private step helpers — would need per-step routing refactor to benefit from FormRequest.
        $request->validate([
            'lot_name' => 'required|string|max:255',
            'floor_count' => 'nullable|integer|min:1|max:20',
            'slots_per_floor' => 'nullable|integer|min:1|max:500',
        ]);

        $floorCount = $request->input('floor_count', 1);
        $slotsPerFloor = $request->input('slots_per_floor', 10);
        $totalSlots = $floorCount * $slotsPerFloor;

        $lot = ParkingLot::create([
            'name' => $request->input('lot_name'),
            'total_slots' => $totalSlots,
            'available_slots' => $totalSlots,
            'status' => 'open',
        ]);

        // Create zones (floors) and slots
        for ($f = 1; $f <= $floorCount; $f++) {
            $zone = Zone::create([
                'lot_id' => $lot->id,
                'name' => $floorCount > 1 ? "Floor {$f}" : 'Ground',
                'color' => '#'.substr(md5("floor-{$f}"), 0, 6),
            ]);

            for ($s = 1; $s <= $slotsPerFloor; $s++) {
                ParkingSlot::create([
                    'lot_id' => $lot->id,
                    'slot_number' => "F{$f}-".str_pad((string) $s, 3, '0', STR_PAD_LEFT),
                    'status' => 'available',
                    'zone_id' => $zone->id,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => ['step' => 2, 'message' => 'Parking lot created', 'lot_id' => $lot->id],
            'error' => null,
            'meta' => null,
        ]);
    }

    private function processUsersStep(Request $request): JsonResponse
    {
        // T-1749-intentional: private step helpers — would need per-step routing refactor to benefit from FormRequest.
        $request->validate([
            'invite_emails' => 'nullable|array',
            'invite_emails.*' => 'email',
        ]);

        $invited = 0;
        $emails = $request->input('invite_emails', []);

        foreach ($emails as $email) {
            if (User::where('email', $email)->exists()) {
                continue;
            }

            User::create([
                'username' => Str::before($email, '@'),
                'email' => $email,
                'password' => bcrypt(Str::random(16)),
                'name' => Str::before($email, '@'),
                'role' => 'user',
                'is_active' => true,
                'preferences' => ['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true],
            ]);
            $invited++;
        }

        return response()->json([
            'success' => true,
            'data' => ['step' => 3, 'message' => "Invited {$invited} users"],
            'error' => null,
            'meta' => null,
        ]);
    }

    private function processThemeStep(Request $request): JsonResponse
    {
        // T-1749-intentional: private step helpers — would need per-step routing refactor to benefit from FormRequest.
        $request->validate([
            'theme' => 'required|string|max:50',
        ]);

        $allowedThemes = [
            'classic', 'glass', 'bento', 'brutalist', 'neon', 'warm',
            'liquid', 'mono', 'ocean', 'forest', 'synthwave', 'zen',
        ];

        $theme = $request->input('theme');
        if (! in_array($theme, $allowedThemes, true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'INVALID_THEME', 'message' => 'Invalid theme selected'],
                'meta' => null,
            ], 422);
        }

        Setting::set('wizard_theme', $theme);
        Setting::set('theme', $theme);
        Setting::set('wizard_completed', 'true');

        return response()->json([
            'success' => true,
            'data' => ['step' => 4, 'message' => 'Setup complete', 'theme' => $theme],
            'error' => null,
            'meta' => null,
        ]);
    }
}
