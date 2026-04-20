<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribePushRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /**
     * POST /api/v1/push/subscribe
     *
     * Store a Web Push subscription for the authenticated user.
     */
    public function subscribe(SubscribePushRequest $request): JsonResponse
    {
        $user = $request->user();

        $subscription = PushSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $request->input('endpoint'),
            ],
            [
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
            ],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $subscription->id,
                'endpoint' => $subscription->endpoint,
                'created_at' => $subscription->created_at?->toJSON(),
            ],
            'error' => null,
            'meta' => null,
        ], 201);
    }

    /**
     * DELETE /api/v1/push/unsubscribe
     *
     * Remove all push subscriptions for the authenticated user.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        PushSubscription::query()
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/push/vapid-key
     *
     * Return the VAPID public key so the frontend can subscribe.
     */
    public function vapidKey(): JsonResponse
    {
        $publicKey = config('services.webpush.vapid_public_key');

        if (! $publicKey) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'NOT_CONFIGURED', 'message' => 'Web Push not configured'],
                'meta' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['public_key' => $publicKey],
            'error' => null,
            'meta' => null,
        ]);
    }
}
