<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Jobs\SendPasswordResetNotificationJob;
use App\Mail\WelcomeEmail;
use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    /**
     * Build an httpOnly cookie containing the Sanctum token.
     */
    private function authCookie(string $token): Cookie
    {
        $secure = app()->environment('production');

        return new Cookie(
            name: 'parkhub_token',
            value: $token,
            expire: now()->addDays(7),
            path: '/',
            secure: $secure,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    /**
     * Build a cookie that clears the auth cookie.
     */
    private function forgetAuthCookie(): Cookie
    {
        $secure = app()->environment('production');

        return new Cookie(
            name: 'parkhub_token',
            value: '',
            expire: now()->subMinute(),
            path: '/',
            secure: $secure,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            AuditLog::log([
                'action' => 'login_failed',
                'details' => ['username' => $request->username],
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['error' => 'INVALID_CREDENTIALS', 'message' => 'Invalid username or password'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['error' => 'ACCOUNT_DISABLED', 'message' => 'Account is disabled'], 403);
        }

        // 2FA check: if enabled, require code or return challenge
        if ($user->two_factor_enabled) {
            if (! $request->has('two_factor_code')) {
                return response()->json([
                    'requires_2fa' => true,
                    'message' => 'Two-factor authentication code required.',
                ]);
            }

            $valid = TwoFactorController::validateLoginCode($user, $request->two_factor_code);
            if (! $valid) {
                return response()->json([
                    'error' => 'INVALID_2FA_CODE',
                    'message' => 'Invalid two-factor authentication code.',
                ], 401);
            }
        }

        $user->update(['last_login' => now()]);
        $token = $user->createToken('auth-token');

        // Record login history
        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'logged_in_at' => now(),
        ]);

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'login',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'user' => $this->userResponse($user),
            'tokens' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(7)->toISOString(),
            ],
        ])->withCookie($this->authCookie($token->plainTextToken));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        if (Setting::get('self_registration', 'true') !== 'true') {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'REGISTRATION_DISABLED',
                    'message' => 'Self-registration is currently disabled. Contact an administrator.',
                ],
                'meta' => null,
            ], 403);
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name' => $request->name,
            'is_active' => true,
            'preferences' => ['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true],
        ]);
        $user->role = 'user';
        $user->save();

        $token = $user->createToken('auth-token');

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'register',
            'ip_address' => $request->ip(),
        ]);

        // Send welcome email (queued — non-blocking)
        if ($user->email) {
            Mail::to($user->email)->queue(new WelcomeEmail($user));
        }

        return response()->json([
            'user' => $this->userResponse($user),
            'tokens' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(7)->toISOString(),
            ],
        ], 201)->withCookie($this->authCookie($token->plainTextToken));
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('auth-token');

        return response()->json([
            'tokens' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(7)->toISOString(),
            ],
        ])->withCookie($this->authCookie($token->plainTextToken));
    }

    /**
     * Logout — revoke current token, clear httpOnly cookie.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Revoke the token that was used for this request.
            // currentAccessToken() returns the PersonalAccessToken model
            // when authenticated via Sanctum Bearer token.
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            AuditLog::log([
                'user_id' => $user->id,
                'username' => $user->username,
                'action' => 'logout',
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json(['message' => 'Logged out'])
            ->withCookie($this->forgetAuthCookie());
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userResponse($request->user()));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'sometimes|nullable|string|max:50',
            'department' => 'sometimes|nullable|string|max:255',
            // Password changes should go through /users/me/password (requires current_password)
        ]);

        $data = $request->only(['name', 'email', 'phone', 'department']);
        $user->update($data);

        return response()->json($this->userResponse($user->fresh()));
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'INVALID_PASSWORD', 'message' => 'Password confirmation failed'], 403);
        }

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'account_deleted',
            'ip_address' => $request->ip(),
        ]);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted']);
    }

    /**
     * Get login history for the current user.
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $history = LoginHistory::where('user_id', $request->user()->id)
            ->orderBy('logged_in_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json($history);
    }

    /**
     * Admin: get login history for a specific user.
     */
    public function adminLoginHistory(Request $request, string $id): JsonResponse
    {
        User::findOrFail($id);

        $history = LoginHistory::where('user_id', $id)
            ->orderBy('logged_in_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($history);
    }

    private function userResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'name' => $user->name,
            'picture' => $user->picture,
            'phone' => $user->phone,
            'role' => $user->role,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'last_login' => $user->last_login?->toISOString(),
            'preferences' => $user->preferences ?? [],
            'is_active' => $user->is_active,
            'department' => $user->department,
            'credits_balance' => $user->credits_balance,
            'credits_monthly_quota' => $user->credits_monthly_quota,
            'two_factor_enabled' => $user->two_factor_enabled,
        ];
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        AuditLog::log([
            'action' => 'forgot_password',
            'details' => ['email_hash' => md5($request->email)],
            'ip_address' => $request->ip(),
        ]);

        // Look up user — use generic response regardless to prevent user enumeration
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Delete any existing token for this email, then insert a fresh one
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $token = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            $appUrl = config('app.url', 'http://localhost');

            // Dispatch password reset email via job queue
            if ($user->email) {
                SendPasswordResetNotificationJob::dispatch(
                    $user->email,
                    $user->name,
                    $token,
                    $appUrl,
                );
            }
        }

        return response()->json(['message' => 'If an account with that email exists, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {

        // Look up the single token row by email (O(1) instead of scanning all rows)
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('created_at', '>', now()->subMinutes(60)) // 60-minute expiry
            ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return response()->json([
                'error' => 'INVALID_TOKEN',
                'message' => 'The reset link is invalid or has expired.',
            ], 422);
        }

        $user = User::where('email', $record->email)->first();
        if (! $user) {
            return response()->json(['error' => 'USER_NOT_FOUND', 'message' => 'User not found.'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete(); // Invalidate all existing sessions

        DB::table('password_reset_tokens')->where('email', $record->email)->delete();

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'password_reset',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Passwort erfolgreich zurückgesetzt. Sie können sich nun anmelden.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'INVALID_PASSWORD', 'message' => 'Current password is incorrect'], 400);
        }
        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();
        $token = $user->createToken('auth-token');
        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'password_changed',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
            'tokens' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(7)->toISOString(),
            ],
        ])->withCookie($this->authCookie($token->plainTextToken));
    }
}
