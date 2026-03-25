<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookV2DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $admin->createToken('test')->plainTextToken;
    }

    private function userToken(): string
    {
        $user = User::factory()->create(['role' => 'user']);

        return $user->createToken('test')->plainTextToken;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.webhooks_v2' => true]);
        $this->cleanupWebhookFiles();
    }

    private function cleanupWebhookFiles(): void
    {
        $path = storage_path('app/webhooks_v2.json');
        if (file_exists($path)) {
            unlink($path);
        }

        foreach (glob(storage_path('app/webhook_deliveries_*.json')) ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * Helper: create a webhook and return its data array.
     * Uses an immediately-failing URL so test deliveries resolve without network delay.
     */
    private function createWebhook(string $token, string $url = 'http://127.0.0.1:1/webhook'): array
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => $url,
                'events' => ['booking.created', 'payment.completed'],
                'description' => 'Delivery test webhook',
            ]);

        $response->assertStatus(201);

        return $response->json('data');
    }

    public function test_deliveries_for_nonexistent_webhook_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/webhooks-v2/wh-nonexistent/deliveries');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_test_delivery_for_nonexistent_webhook_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/webhooks-v2/wh-nonexistent/test');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_test_delivery_returns_correct_structure(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['success', 'status_code', 'error'],
            ]);
    }

    public function test_delivery_is_recorded_after_test(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/deliveries");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_delivery_record_has_correct_structure(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/deliveries");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'event_type', 'status_code', 'success', 'attempt', 'error', 'delivered_at'],
                ],
            ]);
    }

    public function test_delivery_event_type_is_test_ping(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/deliveries");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.event_type', 'test.ping');
    }

    public function test_delivery_id_has_del_prefix(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/deliveries");

        $this->assertStringStartsWith('del-', $response->json('data.0.id'));
    }

    public function test_delivery_attempt_is_one(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/deliveries");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.attempt', 1);
    }

    public function test_multiple_deliveries_accumulate_newest_first(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        for ($i = 0; $i < 3; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/deliveries");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Newest delivery should appear first (array_unshift prepends)
        $this->assertTrue(
            $data[0]['delivered_at'] >= $data[1]['delivered_at'],
            'First delivery should have a timestamp >= the second delivery'
        );
    }

    public function test_delivery_status_is_false_for_failed_connection(): void
    {
        $token = $this->adminToken();
        // Port 1 causes an immediate connection-refused error with no network round-trip
        $webhook = $this->createWebhook($token, 'http://127.0.0.1:1/webhook');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/test");

        $response->assertStatus(200)
            ->assertJsonPath('data.success', false);

        $deliveries = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$webhook['id']}/deliveries");

        $deliveries->assertStatus(200)
            ->assertJsonPath('data.0.success', false);
    }

    public function test_webhook_secret_is_usable_for_hmac_sha256(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);

        $secret = $webhook['secret'];
        $this->assertStringStartsWith('whsec_', $secret);
        // whsec_ prefix (6 chars) + 32 random chars = minimum 38 chars
        $this->assertGreaterThanOrEqual(38, strlen($secret));

        // Verify the secret produces a valid hex HMAC-SHA256 digest
        $payload = json_encode(['event' => 'test.ping', 'data' => []]);
        $signature = hash_hmac('sha256', $payload, $secret);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
    }

    public function test_deliveries_are_deleted_with_webhook(): void
    {
        $token = $this->adminToken();
        $webhook = $this->createWebhook($token);
        $id = $webhook['id'];

        // Record at least one delivery
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/webhooks-v2/{$id}/test");

        // Delete the webhook
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/admin/webhooks-v2/{$id}")
            ->assertStatus(200);

        // Delivery file must be gone
        $this->assertFileDoesNotExist(storage_path("app/webhook_deliveries_{$id}.json"));
    }

    public function test_unauthenticated_access_to_deliveries_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/webhooks-v2/wh-test/deliveries');

        $response->assertStatus(401);
    }

    public function test_regular_user_cannot_access_deliveries(): void
    {
        // Admin middleware fires before the controller, so no webhook needs to exist
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->getJson('/api/v1/admin/webhooks-v2/wh-testid/deliveries');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_trigger_test_delivery(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->postJson('/api/v1/admin/webhooks-v2/wh-testid/test');

        $response->assertStatus(403);
    }
}
