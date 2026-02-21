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
        $response = $this->postJson('/api/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['user' => ['id', 'username', 'email', 'name'], 'tokens' => ['access_token', 'token_type']]]);

        $this->assertDatabaseHas('users', ['username' => 'testuser']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
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

        $response = $this->postJson('/api/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.username', $user->username);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/me', [
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/users/me/delete');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
