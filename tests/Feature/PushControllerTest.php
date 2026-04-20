<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_vapid_key_returns_public_key_when_configured(): void
    {
        config(['services.webpush.vapid_public_key' => 'BFakePublicKeyForTestingPurposes123456789']);

        $response = $this->getJson('/api/v1/push/vapid-key');

        $response->assertStatus(200)
            ->assertJsonPath('data.public_key', 'BFakePublicKeyForTestingPurposes123456789');
    }

    public function test_vapid_key_returns_404_when_not_configured(): void
    {
        config(['services.webpush.vapid_public_key' => null]);

        $response = $this->getJson('/api/v1/push/vapid-key');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_CONFIGURED');
    }

    public function test_subscribe_stores_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
                'keys' => [
                    'p256dh' => 'BFakeP256dhKey',
                    'auth' => 'FakeAuthKey',
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.endpoint', 'https://fcm.googleapis.com/fcm/send/test-endpoint')
            ->assertJsonStructure(['data' => ['id', 'endpoint', 'created_at']]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        ]);
    }

    public function test_subscribe_requires_auth(): void
    {
        $this->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://example.com/push',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])
            ->assertStatus(401);
    }

    public function test_subscribe_validates_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/push/subscribe', [
                'endpoint' => 'not-a-url',
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            ])
            ->assertStatus(422);
    }

    public function test_unsubscribe_removes_subscriptions(): void
    {
        $user = User::factory()->create();

        DB::table('push_subscriptions')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'endpoint' => 'https://example.com/push/1',
            'p256dh' => 'key1',
            'auth' => 'auth1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/push/unsubscribe')
            ->assertStatus(200)
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_push_disabled_when_module_off(): void
    {
        config(['modules.push_notifications' => false]);

        $this->getJson('/api/v1/push/vapid-key')
            ->assertStatus(404);
    }

    public function test_subscribe_upserts_same_endpoint(): void
    {
        $user = User::factory()->create();

        // Subscribe twice with same endpoint
        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/same-endpoint',
            'keys' => ['p256dh' => 'key1', 'auth' => 'auth1'],
        ];

        $this->actingAs($user)->postJson('/api/v1/push/subscribe', $payload)->assertStatus(201);
        $payload['keys'] = ['p256dh' => 'key2', 'auth' => 'auth2'];
        $this->actingAs($user)->postJson('/api/v1/push/subscribe', $payload)->assertStatus(201);

        // Should have only 1 row
        $count = DB::table('push_subscriptions')
            ->where('user_id', $user->id)
            ->where('endpoint', 'https://fcm.googleapis.com/fcm/send/same-endpoint')
            ->count();

        $this->assertEquals(1, $count);
    }
}
