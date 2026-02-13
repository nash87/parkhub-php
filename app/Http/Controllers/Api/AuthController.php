<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'username' => 'required|string|min:3|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'name' => 'required|string',
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
        $data = $request->only(['name', 'email', 'phone', 'department']);

        if ($request->has('password') && $request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        return response()->json($this->userResponse($user->fresh()));
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
}
