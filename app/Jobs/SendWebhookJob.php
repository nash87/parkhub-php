<?php
namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private string $webhookId,
        private string $event,
        private array  $payload,
    ) {}

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);
        if (!$webhook || !$webhook->active) {
            return;
        }

        $body = json_encode([
            'event'   => $this->event,
            'payload' => $this->payload,
            'sent_at' => now()->toIso8601String(),
        ]);

        $headers = ['Content-Type' => 'application/json'];
        if ($webhook->secret) {
            $sig = hash_hmac('sha256', $body, $webhook->secret);
            $headers['X-Parkhub-Signature'] = 'sha256=' . $sig;
        }

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post($webhook->url, json_decode($body, true));

        if (!$response->successful()) {
            Log::warning("Webhook delivery failed", [
                'webhook_id' => $this->webhookId,
                'event'      => $this->event,
                'status'     => $response->status(),
            ]);
            $this->fail("HTTP {$response->status()}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Webhook permanently failed", [
            'webhook_id' => $this->webhookId,
            'event'      => $this->event,
            'error'      => $e->getMessage(),
        ]);
    }
}
