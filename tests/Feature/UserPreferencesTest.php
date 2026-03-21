<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_require_auth(): void
    {
        $this->getJson('/api/user/preferences')
            ->assertStatus(401);
    }

    public function test_user_can_get_preferences(): void
    {
        $user = User::factory()->create([
            'preferences' => ['language' => 'de', 'theme' => 'dark'],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('language', 'de')
            ->assertJsonPath('theme', 'dark');
    }

    public function test_user_can_update_preferences(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', [
                'language' => 'fr',
                'theme' => 'light',
                'notifications_enabled' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('language', 'fr')
            ->assertJsonPath('theme', 'light');
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', [
                'theme' => 'invalid_theme',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_update_timezone_preference(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', [
                'timezone' => 'Europe/Berlin',
                'locale' => 'de-DE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('timezone', 'Europe/Berlin');
    }

    public function test_user_stats_require_auth(): void
    {
        $this->getJson('/api/user/stats')
            ->assertStatus(401);
    }

    public function test_user_can_get_stats(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['total_bookings']);
    }

    public function test_user_notifications_require_auth(): void
    {
        $this->getJson('/api/user/notifications')
            ->assertStatus(401);
    }

    public function test_user_can_list_notifications(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/notifications');

        $response->assertStatus(200);
    }

    public function test_preferences_are_merged_not_replaced(): void
    {
        $user = User::factory()->create([
            'preferences' => ['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // Update only language
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', ['language' => 'de']);

        // Refresh and check that theme is preserved
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('language', 'de')
            ->assertJsonPath('theme', 'system');
    }
}
