<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Return whether 2FA is enabled for the current user.
     *
     * The Profile page calls this on mount to decide whether to render the
     * "Enable" or "Disable" button. Without the route, the request 404'd
     * and every navigation to /profile surfaced as a blank error page.
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) $request->user()->two_factor_enabled,
        ]);
    }

    /**
     * Generate a 2FA secret and provisioning URI for QR code.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'error' => 'TWO_FACTOR_ALREADY_ENABLED',
                'message' => '2FA is already enabled. Disable it first.',
            ], 400);
        }

        $secret = $this->google2fa->generateSecretKey();

        // Store secret temporarily (not yet enabled)
        $user->update(['two_factor_secret' => $secret]);

        $qrUri = $this->google2fa->getQRCodeUrl(
            config('app.name', 'ParkHub'),
            $user->email ?? $user->username,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_uri' => $qrUri,
        ]);
    }

    /**
     * Verify a TOTP code and enable 2FA.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'error' => 'TWO_FACTOR_ALREADY_ENABLED',
                'message' => '2FA is already enabled.',
            ], 400);
        }

        if (! $user->two_factor_secret) {
            return response()->json([
                'error' => 'TWO_FACTOR_NOT_SETUP',
                'message' => 'Call setup endpoint first.',
            ], 400);
        }

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (! $valid) {
            return response()->json([
                'error' => 'INVALID_CODE',
                'message' => 'The verification code is invalid.',
            ], 422);
        }

        $user->update(['two_factor_enabled' => true]);

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => '2fa_enabled',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => '2FA enabled successfully.']);
    }

    /**
     * Disable 2FA (requires password confirmation).
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return response()->json([
                'error' => 'TWO_FACTOR_NOT_ENABLED',
                'message' => '2FA is not enabled.',
            ], 400);
        }

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'INVALID_PASSWORD',
                'message' => 'Password confirmation failed.',
            ], 403);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
        ]);

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => '2fa_disabled',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => '2FA disabled successfully.']);
    }

    /**
     * Validate a 2FA code during login (called after initial credentials check).
     */
    public static function validateLoginCode(User $user, string $code): bool
    {
        $google2fa = new Google2FA;

        return $google2fa->verifyKey($user->two_factor_secret, $code);
    }
}
