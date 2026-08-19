<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed per-user queue behind the server-sent-events stream.
 *
 * This logic used to live inside `SseController`, welded into a
 * `while (true)` streaming loop that no test can drive — which is why two
 * defects survived in it:
 *
 *  - the reader was typed `int $userId` while user ids are UUID strings
 *    (`users.id` is a `uuid` primary key and `User` uses `HasUuids`).
 *    Under `strict_types=1` the very first poll threw a `TypeError`, so no
 *    `booking_created` / `booking_cancelled` / `occupancy_changed` event
 *    was ever delivered. The writer had already been widened to
 *    `int|string` during the UUID migration and the reader had not — the
 *    asymmetry is the tell.
 *  - the cleanup step wrote `array_slice($events, count($events))`, which
 *    is always `[]`. Every poll emptied the queue, while a monotonically
 *    increasing counter was used as an array offset into it.
 *
 * Extracted so the behaviour is testable on its own terms.
 */
final class SseEventQueue
{
    /** Events retained per user. Older events fall off the front. */
    private const int MAX_EVENTS = 100;

    /** How long an idle queue survives, in seconds. */
    private const int TTL_SECONDS = 300;

    private static function key(int|string $userId): string
    {
        return "sse_events:{$userId}";
    }

    /**
     * Append an event to a user's queue.
     *
     * @param  array<string, mixed>  $data
     */
    public static function push(int|string $userId, string $eventType, array $data): void
    {
        $key = self::key($userId);
        $events = Cache::get($key, []);

        $events[] = [
            'event' => $eventType,
            'data' => array_merge($data, ['timestamp' => now()->toIso8601String()]),
            'created_at' => now()->timestamp,
        ];

        Cache::put($key, array_slice($events, -self::MAX_EVENTS), self::TTL_SECONDS);
    }

    /**
     * Read every event after $cursor, without consuming the queue.
     *
     * The reader owns its cursor; the queue is bounded, so old events age
     * out on their own. Deleting on read is what made delivery depend on
     * how far a counter happened to have advanced.
     *
     * @return list<array<string, mixed>>
     */
    public static function pull(int|string $userId, int $cursor): array
    {
        $events = Cache::get(self::key($userId), []);

        if ($events === []) {
            return [];
        }

        return array_values(array_slice($events, max(0, $cursor)));
    }
}
