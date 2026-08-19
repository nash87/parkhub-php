<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Setting;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    /** Seconds to wait for a single push endpoint before giving up. */
    private const int PUSH_TIMEOUT_SECONDS = 5;

    public static function sendToUser(string $userId, string $title, string $body, array $extra = []): int
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $publicKey = Setting::get('vapid_public_key');
        $privateKey = Setting::get('vapid_private_key');
        if (! $publicKey || ! $privateKey) {
            return 0;
        }

        $auth = [
            'VAPID' => [
                'subject' => Setting::get('vapid_subject', config('app.vapid_subject', 'mailto:admin@parkhub.test')),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        // minishlink/web-push defaults to a 30-second timeout per endpoint
        // and `flush()` blocks on each one. Even from a queue worker that is
        // far longer than a push is worth waiting for; a browser push service
        // that has not answered in five seconds is not going to.
        $webPush = new WebPush($auth, [], self::PUSH_TIMEOUT_SECONDS);
        $payload = json_encode(array_merge(['title' => $title, 'body' => $body], $extra));
        $sent = 0;

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh,
                'authToken' => $sub->auth,
            ]);

            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                // Remove expired/invalid subscriptions
                if (in_array($report->getResponse()?->getStatusCode(), [404, 410])) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
            }
        }

        return $sent;
    }
}
