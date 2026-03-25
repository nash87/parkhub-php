<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendWebhookJob;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_webhook_payload(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $webhook = Webhook::create([
            'url' => 'https://1.1.1.1/hook',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $job = new SendWebhookJob($webhook->id, 'booking.created', ['booking_id' => '123']);
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://1.1.1.1/hook'
                && $request['event'] === 'booking.created';
        });
    }

    public function test_skips_inactive_webhook(): void
    {
        Http::fake();

        $webhook = Webhook::create([
            'url' => 'https://1.1.1.1/hook',
            'events' => ['booking.created'],
            'active' => false,
        ]);

        $job = new SendWebhookJob($webhook->id, 'booking.created', ['booking_id' => '123']);
        $job->handle();

        Http::assertNothingSent();
    }

    public function test_skips_nonexistent_webhook(): void
    {
        Http::fake();

        $job = new SendWebhookJob('nonexistent-id', 'booking.created', ['booking_id' => '123']);
        $job->handle();

        Http::assertNothingSent();
    }

    public function test_includes_hmac_signature_when_secret_set(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $webhook = Webhook::create([
            'url' => 'https://1.1.1.1/hook',
            'events' => ['booking.created'],
            'secret' => 'my-webhook-secret',
            'active' => true,
        ]);

        $job = new SendWebhookJob($webhook->id, 'booking.created', ['booking_id' => '123']);
        $job->handle();

        Http::assertSent(function ($request) {
            return str_starts_with($request->header('X-Parkhub-Signature')[0] ?? '', 'sha256=');
        });
    }

    public function test_throws_on_failed_delivery(): void
    {
        Http::fake(['*' => Http::response('Server Error', 500)]);

        $webhook = Webhook::create([
            'url' => 'https://1.1.1.1/hook',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $this->expectException(\RuntimeException::class);

        $job = new SendWebhookJob($webhook->id, 'booking.created', ['booking_id' => '123']);
        $job->handle();
    }
}
