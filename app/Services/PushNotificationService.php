<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Setting;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
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
                'subject' => env('VAPID_SUBJECT', 'mailto:admin@'.parse_url(config('app.url'), PHP_URL_HOST)),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth);
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
