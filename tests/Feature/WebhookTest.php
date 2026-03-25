<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/webhooks')->assertStatus(401);
        $this->postJson('/api/v1/webhooks')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_webhooks(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/webhooks');

        $response->assertStatus(403);
    }

    public function test_admin_can_list_webhooks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        Webhook::create([
            'url' => 'https://example.com/hook1',
            'events' => ['booking.created'],
            'active' => true,
        ]);
        Webhook::create([
            'url' => 'https://example.com/hook2',
            'events' => ['booking.cancelled'],
            'active' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/webhooks');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_admin_can_create_webhook(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        // Use a URL whose host resolves to a public IP in the test environment
        $url = 'https://1.1.1.1/webhook';

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/webhooks', [
                'url' => $url,
                'events' => ['booking.created', 'booking.cancelled'],
                'secret' => 'my-secret',
                'active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('webhooks', ['url' => $url]);
    }

    public function test_non_admin_cannot_create_webhook(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['booking.created'],
            ]);

        $response->assertStatus(403);
    }

    public function test_webhook_creation_requires_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/webhooks', [
                'events' => ['booking.created'],
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_delete_webhook(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $webhook = Webhook::create([
            'url' => 'https://example.com/to-delete',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/webhooks/'.$webhook->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_non_admin_cannot_delete_webhook(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $webhook = Webhook::create([
            'url' => 'https://example.com/protected',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/webhooks/'.$webhook->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id]);
    }

    public function test_admin_can_update_webhook(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $webhook = Webhook::create([
            'url' => 'https://1.1.1.1/original',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/webhooks/'.$webhook->id, [
                'url' => 'https://1.1.1.1/updated',
                'active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'url' => 'https://1.1.1.1/updated',
        ]);
    }

    public function test_delete_nonexistent_webhook_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/webhooks/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
