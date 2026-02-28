<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetEmail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            AuditLog::create([
                'action' => 'login_failed',
                'details' => ['username' => $request->username],
                'ip_address' => $request->ip(),
            ]);
            return response()->json(['error' => 'INVALID_CREDENTIALS', 'message' => 'Invalid username or password'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'ACCOUNT_DISABLED', 'message' => 'Account is disabled'], 403);
        }

        $user->update(['last_login' => now()]);
        $token = $user->createToken('auth-token');

        AuditLog::create([
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

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3|max:50|unique:users|alpha_dash',
            'email'    => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|max:128',
            'name'     => 'required|string|max:255',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name' => $request->name,
            'role' => 'user',
            'is_active' => true,
            'preferences' => ['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true],
        ]);

        $token = $user->createToken('auth-token');

        AuditLog::create([
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

    public function refresh(Request $request)
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

    public function me(Request $request)
    {
        return response()->json($this->userResponse($request->user()));
    }

    public function updateMe(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone'      => 'sometimes|nullable|string|max:50',
            'department' => 'sometimes|nullable|string|max:255',
            // Password changes should go through /users/me/password (requires current_password)
        ]);

        $data = $request->only(['name', 'email', 'phone', 'department']);
        $user->update($data);
        return response()->json($this->userResponse($user->fresh()));
    }


    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'INVALID_PASSWORD', 'message' => 'Password confirmation failed'], 403);
        }

        AuditLog::create([
            'user_id'    => $user->id,
            'username'   => $user->username,
            'action'     => 'account_deleted',
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
        ];
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        AuditLog::create([
            'action'     => 'forgot_password',
            'details'    => ['email_hash' => md5($request->email)],
            'ip_address' => $request->ip(),
        ]);

        // Look up user — use generic response regardless to prevent user enumeration
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Delete any existing token for this email, then insert a fresh one
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $token = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email'      => $user->email,
                'token'      => Hash::make($token),
                'created_at' => now(),
            ]);

            $appUrl = config('app.url', 'http://localhost');

            // Queue the email — non-blocking, fails silently if mail not configured
            if ($user->email) {
                Mail::to($user->email)
                    ->queue(new PasswordResetEmail($user->name, $token, $appUrl));
            }
        }

        return response()->json(['message' => 'If an account with that email exists, a reset link has been sent.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);
        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'INVALID_PASSWORD', 'message' => 'Current password is incorrect'], 400);
        }
        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();
        $token = $user->createToken('auth-token');
        AuditLog::create([
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
                'expires_at' => (new \DateTime('+7 days'))->format('c'),
            ],
        ]);
    }
}
