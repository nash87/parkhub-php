<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpOnlyCookieAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_httponly_cookie(): void
    {
        User::factory()->create([
            'username' => 'cookieuser',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'cookieuser',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Verify the cookie is set
        $cookie = collect($response->headers->getCookies())->first(
            fn ($c) => $c->getName() === 'parkhub_token'
        );

        $this->assertNotNull($cookie, 'parkhub_token cookie should be set');
        $this->assertTrue($cookie->isHttpOnly(), 'Cookie should be httpOnly');
        $this->assertSame('lax', $cookie->getSameSite(), 'Cookie should be SameSite=Lax');
        $this->assertSame('/', $cookie->getPath(), 'Cookie path should be /');

        // Token in cookie should match the one in the JSON response
        $json = $response->json();
        $this->assertSame(
            $json['data']['tokens']['access_token'],
            $cookie->getValue(),
            'Cookie token should match response token'
        );
    }

    public function test_register_sets_httponly_cookie(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'name' => 'New User',
        ]);

        $response->assertStatus(201);

        $cookie = collect($response->headers->getCookies())->first(
            fn ($c) => $c->getName() === 'parkhub_token'
        );

        $this->assertNotNull($cookie, 'parkhub_token cookie should be set on register');
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_cookie_auth_works_with_csrf_header(): void
    {
        $user = User::factory()->create([
            'username' => 'cookieauth',
            'password' => bcrypt('password123'),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Simulate cookie-based auth with X-Requested-With header.
        // Use withUnencryptedCookie because parkhub_token is excluded from encryption,
        // and withCredentials() so JSON requests include cookies.
        $response = $this->withCredentials()
            ->withUnencryptedCookie('parkhub_token', $token)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.username', 'cookieauth');
    }

    public function test_cookie_auth_skipped_without_csrf_header(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Cookie present but no X-Requested-With: the middleware silently
        // declines to inject a Bearer header (so public routes that only
        // carry cookies, like App.tsx's `fetch('/api/v1/theme')`, still
        // work), and auth:sanctum then serves a normal 401 for protected
        // routes. A blanket 403 broke every raw fetch() on the frontend.
        $response = $this->withCredentials()
            ->withUnencryptedCookie('parkhub_token', $token)
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401);
    }

    public function test_bearer_header_takes_precedence_over_cookie(): void
    {
        $user1 = User::factory()->create(['username' => 'user_header']);
        $user2 = User::factory()->create(['username' => 'user_cookie']);

        $headerToken = $user1->createToken('header-token')->plainTextToken;
        $cookieToken = $user2->createToken('cookie-token')->plainTextToken;

        // Both header and cookie present — header should win
        $response = $this->withCredentials()
            ->withHeader('Authorization', 'Bearer '.$headerToken)
            ->withUnencryptedCookie('parkhub_token', $cookieToken)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.username', 'user_header');
    }

    public function test_logout_clears_cookie_and_revokes_token(): void
    {
        $user = User::factory()->create([
            'username' => 'logoutuser',
            'password' => bcrypt('password123'),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('data.message', 'Logged out');

        // Cookie should be cleared (expired)
        $cookie = collect($response->headers->getCookies())->first(
            fn ($c) => $c->getName() === 'parkhub_token'
        );

        $this->assertNotNull($cookie, 'Logout should set a clearing cookie');
        $this->assertTrue(
            $cookie->getExpiresTime() < time(),
            'Cookie should be expired to clear it'
        );

        // Token should be revoked — verify it no longer exists in the DB
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_refresh_sets_new_cookie(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200);

        $cookie = collect($response->headers->getCookies())->first(
            fn ($c) => $c->getName() === 'parkhub_token'
        );

        $this->assertNotNull($cookie, 'Refresh should set new cookie');
        $this->assertTrue($cookie->isHttpOnly());

        // New token should match
        $json = $response->json();
        $this->assertSame(
            $json['data']['tokens']['access_token'],
            $cookie->getValue()
        );
    }

    public function test_invalid_cookie_token_falls_through_to_401(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie('parkhub_token', 'invalid-token-value')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401);
    }
}
