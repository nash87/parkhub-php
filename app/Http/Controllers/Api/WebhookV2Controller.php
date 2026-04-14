<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesExternalUrls;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Webhooks v2 controller — CRUD, test, and delivery log endpoints.
 *
 * Supports HMAC-SHA256 signing and retry logic for webhook deliveries.
 *
 * Admin endpoints:
 *   GET    /api/v1/admin/webhooks-v2                 — list all webhooks
 *   POST   /api/v1/admin/webhooks-v2                 — create webhook
 *   GET    /api/v1/admin/webhooks-v2/{id}            — get single webhook
 *   PUT    /api/v1/admin/webhooks-v2/{id}            — update webhook
 *   DELETE /api/v1/admin/webhooks-v2/{id}            — delete webhook
 *   POST   /api/v1/admin/webhooks-v2/{id}/test       — send test event
 *   GET    /api/v1/admin/webhooks-v2/{id}/deliveries — delivery history
 */
class WebhookV2Controller extends Controller
{
    use ValidatesExternalUrls;
    /**
     * List all v2 webhooks.
     */
    public function index(): JsonResponse
    {
        $webhooks = $this->loadWebhooks();

        return response()->json([
            'success' => true,
            'data' => $webhooks,
        ]);
    }

    /**
     * Get a single webhook by ID.
     */
    public function show(string $id): JsonResponse
    {
        $webhook = collect($this->loadWebhooks())->firstWhere('id', $id);

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Webhook not found'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ]);
    }

    /**
     * Create a new v2 webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'description' => 'nullable|string|max:500',
            'active' => 'boolean',
        ]);

        $url = $request->input('url');
        if (! $this->isExternalUrl($url)) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'URL must be a valid external (non-private) HTTP(S) address'],
            ], 422);
        }

        $webhooks = $this->loadWebhooks();

        $webhook = [
            'id' => 'wh-'.Str::random(12),
            'url' => $url,
            'secret' => 'whsec_'.Str::random(32),
            'events' => $request->input('events'),
            'active' => $request->boolean('active', true),
            'description' => $request->input('description'),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $webhooks[] = $webhook;
        $this->saveWebhooks($webhooks);

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ], 201);
    }

    /**
     * Update an existing v2 webhook.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'url' => 'sometimes|url|max:2048',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string',
            'description' => 'nullable|string|max:500',
            'active' => 'boolean',
        ]);

        $webhooks = $this->loadWebhooks();
        $index = collect($webhooks)->search(fn ($w) => $w['id'] === $id);

        if ($index === false) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Webhook not found'],
            ], 404);
        }

        $webhook = $webhooks[$index];
        $newUrl = $request->input('url', $webhook['url']);
        if ($newUrl !== $webhook['url'] && ! $this->isExternalUrl($newUrl)) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'URL must be a valid external (non-private) HTTP(S) address'],
            ], 422);
        }
        $webhook['url'] = $newUrl;
        $webhook['events'] = $request->input('events', $webhook['events']);
        $webhook['description'] = $request->input('description', $webhook['description']);
        $webhook['active'] = $request->boolean('active', $webhook['active']);
        $webhook['updated_at'] = now()->toIso8601String();

        $webhooks[$index] = $webhook;
        $this->saveWebhooks($webhooks);

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ]);
    }

    /**
     * Delete a v2 webhook.
     */
    public function destroy(string $id): JsonResponse
    {
        $webhooks = $this->loadWebhooks();
        $filtered = collect($webhooks)->reject(fn ($w) => $w['id'] === $id)->values()->toArray();

        if (count($filtered) === count($webhooks)) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Webhook not found'],
            ], 404);
        }

        $this->saveWebhooks($filtered);

        // Clean up deliveries
        $this->deleteDeliveries($id);

        return response()->json(['success' => true, 'message' => 'Webhook deleted']);
    }

    /**
     * Send a test event to a webhook endpoint.
     */
    public function test(string $id): JsonResponse
    {
        $webhook = collect($this->loadWebhooks())->firstWhere('id', $id);

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Webhook not found'],
            ], 404);
        }

        $payload = json_encode([
            'event' => 'test.ping',
            'timestamp' => now()->toIso8601String(),
            'data' => ['message' => 'This is a test event from ParkHub'],
        ]);

        $signature = hash_hmac('sha256', $payload, $webhook['secret']);
        $success = false;
        $statusCode = null;
        $error = null;

        try {
            $ch = curl_init($webhook['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-ParkHub-Signature: sha256='.$signature,
                    'X-ParkHub-Event: test.ping',
                    'X-ParkHub-Delivery: '.Str::uuid(),
                    'User-Agent: ParkHub-Webhooks/2.0',
                ],
            ]);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $success = $statusCode >= 200 && $statusCode < 300;

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                $success = false;
            }
            curl_close($ch);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        // Record delivery
        $this->recordDelivery($id, [
            'id' => 'del-'.Str::random(12),
            'event_type' => 'test.ping',
            'status_code' => $statusCode,
            'success' => $success,
            'attempt' => 1,
            'error' => $error,
            'delivered_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'success' => $success,
                'status_code' => $statusCode,
                'error' => $error,
            ],
        ]);
    }

    /**
     * Get delivery history for a webhook.
     */
    public function deliveries(string $id): JsonResponse
    {
        $webhook = collect($this->loadWebhooks())->firstWhere('id', $id);

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Webhook not found'],
            ], 404);
        }

        $deliveries = $this->loadDeliveries($id);

        return response()->json([
            'success' => true,
            'data' => $deliveries,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function loadWebhooks(): array
    {
        $path = storage_path('app/webhooks_v2.json');

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    private function saveWebhooks(array $webhooks): void
    {
        $path = storage_path('app/webhooks_v2.json');
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode(array_values($webhooks), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function loadDeliveries(string $webhookId): array
    {
        $path = storage_path("app/webhook_deliveries_{$webhookId}.json");

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    private function recordDelivery(string $webhookId, array $delivery): void
    {
        $deliveries = $this->loadDeliveries($webhookId);
        array_unshift($deliveries, $delivery);
        // Keep last 100 deliveries
        $deliveries = array_slice($deliveries, 0, 100);

        $path = storage_path("app/webhook_deliveries_{$webhookId}.json");
        file_put_contents($path, json_encode($deliveries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function deleteDeliveries(string $webhookId): void
    {
        $path = storage_path("app/webhook_deliveries_{$webhookId}.json");

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
