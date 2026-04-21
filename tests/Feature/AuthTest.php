<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['user' => ['id', 'username', 'email', 'name'], 'tokens' => ['access_token', 'token_type']]]);

        $this->assertDatabaseHas('users', ['username' => 'testuser']);
    }

    public function test_user_can_register_without_username(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'generated@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'name' => 'Generated User',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.username', 'generated');

        $this->assertDatabaseHas('users', [
            'email' => 'generated@example.com',
            'username' => 'generated',
        ]);
    }

    public function test_register_without_username_generates_unique_suffix_when_needed(): void
    {
        User::factory()->create([
            'username' => 'existing_user',
            'email' => 'first@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'existing.user@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'name' => 'Existing User',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.username', 'existing_user_2');

        $this->assertDatabaseHas('users', [
            'email' => 'existing.user@example.com',
            'username' => 'existing_user_2',
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['user', 'tokens' => ['access_token', 'token_type']]]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.username', $user->username);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/users/me', [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['name' => 'Updated Name']);
    }

    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/users/me/delete', ['password' => 'password123']);

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
