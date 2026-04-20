<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/users/me', [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_user_can_update_phone(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/users/me', [
                'phone' => '+49 170 1234567',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '+49 170 1234567']);
    }

    public function test_user_can_update_department(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/users/me', [
                'department' => 'Engineering',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'department' => 'Engineering']);
    }

    public function test_user_can_get_stats(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create([
            'name' => 'Stats Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'S1',
            'status' => 'available',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'slot_number' => 'S1',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_bookings',
                    'bookings_this_month',
                    'homeoffice_days_this_month',
                    'avg_duration_minutes',
                    'favorite_slot',
                ],
            ]);

        $this->assertEquals(1, $response->json('data.total_bookings'));
    }

    public function test_user_stats_empty_for_new_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_bookings', 0)
            ->assertJsonPath('data.avg_duration_minutes', 0);
    }

    public function test_user_can_get_preferences(): void
    {
        $user = User::factory()->create([
            'preferences' => ['theme' => 'dark', 'language' => 'de'],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.language', 'de');
    }

    public function test_user_preferences_canonicalize_legacy_push_key_on_read(): void
    {
        $user = User::factory()->create([
            'preferences' => ['theme' => 'dark', 'push_notifications' => false],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.push', false);

        $this->assertArrayNotHasKey('push_notifications', $response->json('data'));
    }

    public function test_user_can_update_preferences(): void
    {
        $user = User::factory()->create([
            'preferences' => ['theme' => 'light'],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/user/preferences', [
                'theme' => 'dark',
                'language' => 'de',
                'notifications_enabled' => false,
                'push' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.language', 'de')
            ->assertJsonPath('data.notifications_enabled', false)
            ->assertJsonPath('data.push', false);
    }

    public function test_user_can_update_preferences_with_legacy_push_alias(): void
    {
        $user = User::factory()->create([
            'preferences' => ['theme' => 'light'],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/user/preferences', [
                'push_notifications' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.push', false);

        $this->assertArrayNotHasKey('push_notifications', $response->json('data'));
        $this->assertSame(false, $user->fresh()->preferences['push']);
        $this->assertArrayNotHasKey('push_notifications', $user->fresh()->preferences);
    }

    public function test_user_cannot_send_conflicting_push_alias_values(): void
    {
        $user = User::factory()->create([
            'preferences' => ['theme' => 'light'],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/user/preferences', [
                'push' => true,
                'push_notifications' => false,
            ]);

        $response->assertStatus(422);
        $this->assertSame(['theme' => 'light'], $user->fresh()->preferences);
    }

    public function test_preferences_merge_not_replace(): void
    {
        $user = User::factory()->create([
            'preferences' => ['theme' => 'light', 'language' => 'en'],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // Update only theme — language should be preserved
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/user/preferences', [
                'theme' => 'dark',
            ]);

        $user->refresh();
        $this->assertEquals('dark', $user->preferences['theme']);
        $this->assertEquals('en', $user->preferences['language']);
    }

    public function test_change_password_with_correct_current(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPass123',
                'new_password' => 'NewPass456',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['message', 'tokens' => ['access_token', 'token_type']]]);
    }

    public function test_change_password_with_wrong_current_fails(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'WrongPassword1',
                'new_password' => 'NewPass456',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_PASSWORD');
    }

    public function test_change_password_with_weak_password_fails(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPass123',
                'new_password' => 'short',
            ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_cannot_access_profile(): void
    {
        $this->getJson('/api/v1/users/me')->assertStatus(401);
        $this->putJson('/api/v1/users/me')->assertStatus(401);
        $this->getJson('/api/v1/user/stats')->assertStatus(401);
        $this->getJson('/api/v1/user/preferences')->assertStatus(401);
    }

    public function test_profile_returns_correct_user_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'profile@test.com',
            'role' => 'user',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Test User')
            ->assertJsonPath('data.email', 'profile@test.com')
            ->assertJsonPath('data.role', 'user');
    }
}
