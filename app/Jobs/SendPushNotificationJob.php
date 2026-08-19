<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deliver a web-push notification outside the request.
 *
 * `PushNotificationService` ends in `foreach ($webPush->flush() as $report)`,
 * and minishlink/web-push's `flush()` is `yield $promise->wait()` — a
 * blocking round-trip to every registered endpoint. Called from a model
 * hook, that ran inside whatever request happened to create the
 * notification.
 *
 * Failures are logged rather than rethrown on the last attempt: a push that
 * cannot be delivered must not fail the surrounding work, which is what the
 * original swallowed `catch (\Throwable)` was reaching for — just in the
 * wrong place.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $extra = [],
    ) {}

    public function handle(): void
    {
        PushNotificationService::sendToUser($this->userId, $this->title, $this->body, $this->extra);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('SendPushNotificationJob: giving up on a push notification', [
            'user_id' => $this->userId,
            'error' => $e->getMessage(),
        ]);
    }
}
