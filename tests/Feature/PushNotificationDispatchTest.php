<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Web push must not happen inside the request.
 *
 * `Notification::booted()` called `PushNotificationService::sendToUser`
 * straight from the model's `created` hook, under a comment reading
 * "fire-and-forget push notification (non-blocking)". It is not
 * non-blocking: `PushNotificationService` ends in
 * `foreach ($webPush->flush() as $report)`, and minishlink/web-push's
 * `flush()` is `yield $promise->wait()` with a 30-second default timeout
 * per endpoint.
 *
 * There are ten `Notification::create` call sites in request handlers.
 * `BookingController::destroy` alone creates up to three in a loop, so one
 * cancellation could pin a PHP-FPM worker for 3 x subscriptions x 30s. The
 * project ships `deploy-shared-hosting.sh`; on a shared-hosting worker pool
 * that is a self-inflicted denial of service.
 */
class PushNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_notification_queues_the_push_instead_of_sending_it(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Booking cancelled',
            'message' => 'Your slot was released.',
            'type' => 'booking',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_the_queued_job_carries_what_the_push_needs(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Slot free',
            'message' => 'A slot opened up.',
            'type' => 'waitlist',
        ]);

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job) => $job->userId === $user->id
                && $job->title === 'Slot free'
                && $job->body === 'A slot opened up.',
        );
    }

    /**
     * Several notifications in one request must not become several blocking
     * calls — this is the shape that pins a worker.
     */
    public function test_a_burst_of_notifications_queues_one_job_each(): void
    {
        Queue::fake();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Slot free',
                'message' => 'A slot opened up.',
                'type' => 'waitlist',
            ]);
        }

        Queue::assertPushed(SendPushNotificationJob::class, 3);
    }
}
