<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Jobs\SendPasswordResetNotificationJob;
use App\Mail\WelcomeEmail;
use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\Authentication\AuthenticationService;
use App\Services\Authentication\AuthOutcome;
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
        // Mark the cookie Secure only when the actual request came in
        // over HTTPS. `isSecure()` consults both the protocol and
        // X-Forwarded-Proto (via TrustProxies), so this is correct
        // behind Render/Cloudflare/Fly reverse proxies AND on a local
        // http://127.0.0.1 demo. Pinning it to APP_ENV=production
        // broke WebKit mobile-safari E2E tests: WebKit's mobile
        // emulation drops Secure cookies on HTTP even on localhost,
        // so the session vanished between requests and every
        // ProtectedRoute navigation redirected to /welcome.
        $secure = request()?->isSecure() ?? app()->environment('production');

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
        // Match authCookie() — the Secure attribute has to line up or
        // WebKit won't find the cookie to unset.
        $secure = request()?->isSecure() ?? app()->environment('production');

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

    public function login(LoginRequest $request, AuthenticationService $service): JsonResponse
    {
        $result = $service->attempt(
            [
                'username' => (string) $request->input('username', ''),
                'password' => (string) $request->input('password', ''),
                'two_factor_code' => $request->input('two_factor_code'),
            ],
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        return match ($result->outcome) {
            AuthOutcome::InvalidCredentials => response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Invalid username or password'],
                'meta' => null,
            ], 401),

            AuthOutcome::AccountDisabled => response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'ACCOUNT_DISABLED', 'message' => 'Account is disabled'],
                'meta' => null,
            ], 403),

            // Challenge returns HTTP 200 with success=true + data.requires_2fa
            // so the frontend request() helper can inspect data without treating
            // the challenge as an error.
            AuthOutcome::RequiresTwoFactor => response()->json([
                'success' => true,
                'data' => [
                    'requires_2fa' => true,
                    'message' => 'Two-factor authentication code required.',
                ],
                'error' => null,
                'meta' => null,
            ]),

            AuthOutcome::InvalidTwoFactorCode => response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'INVALID_2FA_CODE', 'message' => 'Invalid two-factor authentication code.'],
                'meta' => null,
            ], 401),

            AuthOutcome::Success => response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->userResponse($result->user),
                    'tokens' => [
                        'access_token' => $result->token->plainTextToken,
                        'token_type' => 'Bearer',
                        'expires_at' => now()->addDays(7)->toISOString(),
                    ],
                ],
                'error' => null,
                'meta' => null,
            ])->withCookie($this->authCookie($result->token->plainTextToken)),
        };
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

        $username = $this->resolveRegistrationUsername($request);

        $user = User::create([
            'username' => $username,
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

    private function resolveRegistrationUsername(RegisterRequest $request): string
    {
        $provided = trim((string) $request->input('username', ''));
        if ($provided !== '') {
            return $provided;
        }

        $base = $this->normalizeGeneratedUsernameBase(Str::before((string) $request->email, '@'));
        if ($base === '') {
            $base = $this->normalizeGeneratedUsernameBase((string) $request->name);
        }
        if ($base === '') {
            $base = 'user';
        }
        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '_');
        }

        $candidate = substr($base, 0, 50);
        $suffix = 2;

        while (User::where('username', $candidate)->exists()) {
            $counter = '_'.$suffix;
            $candidate = substr($base, 0, 50 - strlen($counter)).$counter;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeGeneratedUsernameBase(string $value): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', Str::lower($value)) ?? '';

        return trim($normalized, '_');
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

    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->only(['name', 'email', 'phone', 'department']);
        $user->update($data);

        return response()->json($this->userResponse($user->fresh()));
    }

    /**
     * GET /api/v1/me/settings — return the authenticated user's v5
     * customization blob. Returns `null` when the user has never
     * customized; clients fall back to factory defaults.
     */
    public function getMySettings(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->settings,
        ]);
    }

    /**
     * PUT /api/v1/me/settings — replace the user's v5 settings blob.
     *
     * Body: `{ "settings": { ... } }` — schema is opaque to the server.
     * Validation is structural only (must be a JSON object, capped size);
     * the frontend's V5SettingsProvider.migrate() owns shape correctness.
     */
    public function updateMySettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
        ]);

        // Cap at 32 KB serialized — protects the column from runaway clients.
        $payload = $request->input('settings');
        $encoded = json_encode($payload);
        if ($encoded === false || strlen($encoded) > 32768) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYLOAD_TOO_LARGE',
                    'message' => 'Einstellungen-Blob darf 32 KB nicht überschreiten.',
                ],
            ], 413);
        }

        $user = $request->user();
        $user->settings = $payload;
        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user->settings,
        ]);
    }

    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
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

        return response()->json($history->map(fn ($h) => $this->loginHistoryEntry($h)));
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

        return response()->json($history->map(fn ($h) => $this->loginHistoryEntry($h)));
    }

    /**
     * Serialize a login history row into the shape the frontend expects.
     *
     * The database column is `logged_in_at` but the shared TypeScript type
     * and the Rust backend both use `timestamp`. Returning the raw column
     * name broke the Profile page with "Invalid time value" when date-fns
     * tried to format `undefined`.
     */
    private function loginHistoryEntry(LoginHistory $h): array
    {
        return [
            'timestamp' => $h->logged_in_at?->toIso8601String(),
            'ip_address' => $h->ip_address,
            'user_agent' => $h->user_agent,
            // Only successful logins are recorded today; failed attempts
            // live in the audit_log table and aren't surfaced here.
            'success' => true,
        ];
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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
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

        // Regenerate the session ID on a privilege change — defends against
        // session fixation and ensures the `auth_at` anchor resets for the
        // absolute-lifetime cap. See BSI TR-03107 / OWASP ASVS 3.3.2.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

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
