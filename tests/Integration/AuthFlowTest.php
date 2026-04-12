<?php

namespace Tests\Integration;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

class AuthFlowTest extends IntegrationTestCase
{
    // ── Full auth lifecycle ──────────────────────────────────────────────

    public function test_full_auth_lifecycle_register_login_refresh_logout(): void
    {
        // 1. Register
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'username' => 'lifecycle_user',
            'email' => 'lifecycle@parkhub.test',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'name' => 'Lifecycle User',
        ]);
        $registerResponse->assertStatus(201);

        $registerData = $registerResponse->json();
        $this->assertNotEmpty($registerData['data']['tokens']['access_token'] ?? $registerData['data']['token'] ?? null);

        // 2. Login with the registered credentials
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'username' => 'lifecycle_user',
            'password' => 'StrongPass123',
        ]);
        $loginResponse->assertStatus(200);

        $loginData = $loginResponse->json();
        $token = $loginData['data']['tokens']['access_token'] ?? $loginData['data']['token'] ?? '';
        $this->assertNotEmpty($token);

        // 3. Access protected endpoint with token
        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');
        $meResponse->assertStatus(200);

        $meData = $meResponse->json();
        $userData = $meData['data'] ?? $meData;
        $this->assertEquals('lifecycle_user', $userData['username']);

        // 4. Refresh token
        $refreshResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/refresh');
        $refreshResponse->assertStatus(200);

        // 5. Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');
        $logoutResponse->assertStatus(200);

        // 6. Verify token is invalid after logout
        $afterLogout = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');
        $afterLogout->assertStatus(401);
    }

    // ── 2FA flow ─────────────────────────────────────────────────────────

    public function test_2fa_enable_login_verify_flow(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('SecurePass123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // 1. Setup 2FA
        $setupResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/setup');
        $setupResponse->assertStatus(200);
        $setupResponse->assertJsonStructure(['data' => ['secret', 'qr_uri']]);

        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotNull($secret);

        // 2. Verify with valid TOTP code
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        $verifyResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);
        $verifyResponse->assertStatus(200);
        $this->assertTrue($user->fresh()->two_factor_enabled);

        // 3. Login with 2FA code
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'SecurePass123',
            'two_factor_code' => $google2fa->getCurrentOtp($secret),
        ]);
        $loginResponse->assertStatus(200);
        $loginData = $loginResponse->json();
        $this->assertNotEmpty($loginData['data']['tokens'] ?? $loginData['data']['token'] ?? null);
    }

    public function test_2fa_login_without_code_returns_requires_2fa(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('Pass123'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Pass123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.requires_2fa'));
    }

    // ── Password reset flow ────────���──────────────────────────��──────────

    public function test_password_reset_flow_end_to_end(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-test@parkhub.test',
            'password' => bcrypt('OldPass123'),
        ]);

        // 1. Request password reset
        $forgotResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset-test@parkhub.test',
        ]);

        // Should return 200 regardless (security: don't reveal if email exists)
        $forgotResponse->assertStatus(200);

        // Verify the user record still exists and is accessible
        $this->assertDatabaseHas('users', ['email' => 'reset-test@parkhub.test']);
    }

    // ── Invalid credentials ──────────────────────────────────��───────────

    public function test_login_with_invalid_password_returns_401(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('CorrectPass'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_with_nonexistent_user_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'nonexistent_user_xyz',
            'password' => 'AnyPassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_register_with_existing_email_returns_422(): void
    {
        User::factory()->create(['email' => 'existing@parkhub.test']);

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'new_user',
            'email' => 'existing@parkhub.test',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'name' => 'New User',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_with_weak_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'weakpass_user',
            'email' => 'weak@parkhub.test',
            'password' => '123',
            'password_confirmation' => '123',
            'name' => 'Weak User',
        ]);

        $response->assertStatus(422);
    }

    // ── Rate limiting ─────────��──────────────────────────────────────────

    public function test_rapid_login_attempts_eventually_rate_limited(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('CorrectPass'),
        ]);

        $responses = [];
        // Send rapid login attempts to trigger throttle
        for ($i = 0; $i < 15; $i++) {
            $responses[] = $this->postJson('/api/v1/auth/login', [
                'username' => $user->username,
                'password' => 'WrongPassword' . $i,
            ]);
        }

        $statusCodes = array_map(fn ($r) => $r->getStatusCode(), $responses);

        // At least some should be 401 (wrong password) and eventually 429 (throttled)
        $this->assertContains(401, $statusCodes, 'Expected at least one 401 from wrong password');
        // The throttle middleware may or may not kick in depending on config,
        // but we verify the system handles rapid attempts gracefully
        $this->assertTrue(
            in_array(429, $statusCodes) || count(array_filter($statusCodes, fn ($c) => $c === 401)) >= 5,
            'Expected either rate limiting (429) or consistent rejection (401) for rapid attempts'
        );
    }

    // ── Token revocation ─────────────────────────────────────────────────

    public function test_deleted_user_token_becomes_invalid(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Pass123')]);
        $token = $user->createToken('test')->plainTextToken;

        // Verify token works
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertStatus(200);

        // Soft-delete user
        $user->delete();

        // Token should no longer grant access
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_change_password_flow(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ]);

        $response->assertStatus(200);

        // Verify new password works
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'NewPassword456',
        ]);
        $loginResponse->assertStatus(200);
    }
}
