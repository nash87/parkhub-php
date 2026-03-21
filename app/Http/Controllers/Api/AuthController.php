<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetNotificationJob;
use App\Mail\WelcomeEmail;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/login",
     *     summary="Login with username/email and password",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"username","password"},
     *
     *             @OA\Property(property="username", type="string", example="admin"),
     *             @OA\Property(property="password", type="string", format="password", example="secret")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Login successful, returns user and token"),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=403, description="Account disabled")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

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

        $user->update(['last_login' => now()]);
        $token = $user->createToken('auth-token');

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
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     summary="Register a new user",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"username","email","password","password_confirmation","name"},
     *
     *             @OA\Property(property="username", type="string", example="johndoe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Secret123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="name", type="string", example="John Doe")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="User registered"),
     *     @OA\Response(response=403, description="Registration disabled"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        if (Setting::get('self_registration', 'true') !== 'true') {
            return response()->json(['message' => 'Registration is currently disabled'], 403);
        }

        $request->validate([
            'username' => 'required|string|min:3|max:50|unique:users|alpha_dash',
            'email' => 'required|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'name' => 'required|string|max:255',
        ]);

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
        ], 201);
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
        ]);
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

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'password_confirmation' => 'required|same:password',
        ]);

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

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
        ]);
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
        ]);
    }
}
