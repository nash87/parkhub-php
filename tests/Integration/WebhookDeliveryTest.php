<?php

namespace Tests\Integration;

use App\Models\Webhook;

class WebhookDeliveryTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.webhooks_v2' => true]);

        // Clean up webhook v2 files
        $path = storage_path('app/webhooks_v2.json');
        if (file_exists($path)) {
            unlink($path);
        }
        foreach (glob(storage_path('app/webhook_deliveries_*.json')) ?: [] as $file) {
            unlink($file);
        }
    }

    // ── V1 Webhook registration and lifecycle ────────────────────────────

    public function test_register_webhook_and_verify_stored(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://1.1.1.1/webhook-test',
                'events' => ['booking.created', 'booking.cancelled'],
                'secret' => 'test-secret-key',
                'active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('webhooks', ['url' => 'https://1.1.1.1/webhook-test']);
    }

    public function test_webhook_list_returns_registered_webhooks(): void
    {
        Webhook::create([
            'url' => 'https://1.1.1.1/hook1',
            'events' => ['booking.created'],
            'active' => true,
        ]);
        Webhook::create([
            'url' => 'https://1.1.1.1/hook2',
            'events' => ['booking.cancelled'],
            'active' => false,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/webhooks');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_webhook_update(): void
    {
        $webhook = Webhook::create([
            'url' => 'https://1.1.1.1/original',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'url' => 'https://1.1.1.1/updated',
                'active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'url' => 'https://1.1.1.1/updated',
        ]);
    }

    public function test_webhook_delete(): void
    {
        $webhook = Webhook::create([
            'url' => 'https://1.1.1.1/to-delete',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/v1/webhooks/{$webhook->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    // ── V2 Webhook with HMAC ──────────────────────────────────────────

    public function test_v2_webhook_has_hmac_secret(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/v2-hook',
                'events' => ['booking.created'],
            ]);

        $response->assertStatus(201);
        $secret = $response->json('data.secret');
        $this->assertStringStartsWith('whsec_', $secret);
    }

    public function test_v2_webhook_secret_produces_valid_hmac_sha256(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/hmac-hook',
                'events' => ['booking.created'],
            ]);

        $response->assertStatus(201);
        $secret = $response->json('data.secret');

        // Verify HMAC-SHA256 signature can be computed
        $payload = json_encode(['event' => 'booking.created', 'data' => ['id' => 'test']]);
        $signature = hash_hmac('sha256', $payload, $secret);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);

        // Verify the same payload + secret always produce the same signature
        $signature2 = hash_hmac('sha256', $payload, $secret);
        $this->assertEquals($signature, $signature2);

        // Verify a different secret produces a different signature
        $wrongSignature = hash_hmac('sha256', $payload, 'wrong-secret');
        $this->assertNotEquals($signature, $wrongSignature);
    }

    // ── V2 Webhook test delivery ──────────────────────────────────────

    public function test_v2_test_delivery_creates_delivery_record(): void
    {
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'http://127.0.0.1:1/webhook',
                'events' => ['booking.created'],
            ]);
        $createResponse->assertStatus(201);
        $webhookId = $createResponse->json('data.id');

        // Trigger test delivery
        $testResponse = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/admin/webhooks-v2/{$webhookId}/test");
        $testResponse->assertStatus(200);
        $testResponse->assertJsonStructure(['data' => ['success', 'status_code']]);

        // Verify delivery record
        $deliveries = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v1/admin/webhooks-v2/{$webhookId}/deliveries");
        $deliveries->assertStatus(200);

        $data = $deliveries->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('test.ping', $data[0]['event_type']);
        $this->assertStringStartsWith('del-', $data[0]['id']);
    }

    public function test_v2_delivery_failure_recorded(): void
    {
        // Use port 1 which causes immediate connection-refused
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'http://127.0.0.1:1/unreachable',
                'events' => ['booking.created'],
            ]);
        $webhookId = $createResponse->json('data.id');

        $testResponse = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/admin/webhooks-v2/{$webhookId}/test");
        $testResponse->assertStatus(200);
        $this->assertFalse($testResponse->json('data.success'));

        $deliveries = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v1/admin/webhooks-v2/{$webhookId}/deliveries");
        $this->assertFalse($deliveries->json('data.0.success'));
    }

    // ── V2 Webhook format ─────────────────────────────────────────────

    public function test_v2_webhook_create_returns_correct_format(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/format-test',
                'events' => ['booking.created', 'payment.completed'],
                'description' => 'Format test webhook',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'url', 'secret', 'events', 'active'],
            ]);

        $data = $response->json('data');
        $this->assertEquals('https://example.com/format-test', $data['url']);
        $this->assertContains('booking.created', $data['events']);
        $this->assertTrue($data['active']);
    }

    // ── Multiple deliveries ────────────────────────────────────────────

    public function test_v2_multiple_deliveries_ordered_newest_first(): void
    {
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'http://127.0.0.1:1/webhook',
                'events' => ['booking.created'],
            ]);
        $webhookId = $createResponse->json('data.id');

        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders($this->adminHeaders())
                ->postJson("/api/v1/admin/webhooks-v2/{$webhookId}/test");
        }

        $deliveries = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v1/admin/webhooks-v2/{$webhookId}/deliveries");

        $data = $deliveries->json('data');
        $this->assertCount(3, $data);

        // Verify ordering (newest first)
        $this->assertTrue(
            $data[0]['delivered_at'] >= $data[1]['delivered_at'],
            'Deliveries should be ordered newest first'
        );
    }

    // ── Authorization ──────────────────────────────────────────────────

    public function test_regular_user_cannot_manage_webhooks(): void
    {
        $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/webhooks')
            ->assertStatus(403);

        $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/hack',
                'events' => ['booking.created'],
            ])
            ->assertStatus(403);
    }

    public function test_regular_user_cannot_access_v2_webhooks(): void
    {
        $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/admin/webhooks-v2')
            ->assertStatus(403);
    }
}
