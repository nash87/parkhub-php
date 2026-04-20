<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events controller for real-time booking/occupancy updates.
 *
 * GET /api/v1/sse?token=<sanctum_token>
 *
 * Streams events:
 *   - booking_created   — a booking was made in one of the user's lots
 *   - booking_cancelled — a booking was cancelled
 *   - occupancy_changed — lot occupancy snapshot changed
 *   - slot_status_change — a slot changed status
 *   - announcement_published — new announcement
 *
 * Auth: bearer token via query parameter (SSE cannot send headers).
 */
class SseController extends Controller
{
    /**
     * SSE stream endpoint.
     *
     * Pushes real-time events to the client via Server-Sent Events.
     * The stream checks a cache-based event queue and lot occupancy
     * changes every polling interval.
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $lastEventId = $request->header('Last-Event-ID', '0');

        return new StreamedResponse(function () use ($user, $lastEventId) {
            // Disable output buffering for streaming
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send initial connection event
            $this->sendEvent('connected', [
                'user_id' => $user->id,
                'timestamp' => now()->toIso8601String(),
            ], 'connection');

            $pollInterval = 2; // seconds
            $heartbeatInterval = 15; // seconds
            $maxDuration = 300; // 5 minutes max, client should reconnect
            $startTime = time();
            $lastHeartbeat = time();
            $lastOccupancy = [];
            $eventCounter = (int) $lastEventId;

            while (true) {
                // Check if max duration exceeded
                if ((time() - $startTime) >= $maxDuration) {
                    $this->sendEvent('stream_end', [
                        'reason' => 'max_duration',
                        'reconnect_ms' => 1000,
                    ]);
                    break;
                }

                // Check connection (client disconnect)
                if (connection_aborted()) {
                    break;
                }

                // Poll for new booking events from cache queue
                $events = $this->pollBookingEvents($user->id, $eventCounter);
                foreach ($events as $event) {
                    $eventCounter++;
                    $this->sendEvent($event['event'], $event['data'], (string) $eventCounter);
                }

                // Check occupancy changes
                $occupancyEvents = $this->checkOccupancyChanges($lastOccupancy);
                foreach ($occupancyEvents as $event) {
                    $eventCounter++;
                    $this->sendEvent('occupancy_changed', $event, (string) $eventCounter);
                }

                // Heartbeat to keep connection alive
                if ((time() - $lastHeartbeat) >= $heartbeatInterval) {
                    echo ": heartbeat\n\n";
                    $lastHeartbeat = time();
                }

                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
                sleep($pollInterval);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Push a booking event to the SSE event queue for a specific user.
     *
     * Called internally by event listeners to enqueue real-time events.
     */
    public static function pushEvent(int|string $userId, string $eventType, array $data): void
    {
        $key = "sse_events:{$userId}";
        $events = Cache::get($key, []);
        $events[] = [
            'event' => $eventType,
            'data' => array_merge($data, [
                'timestamp' => now()->toIso8601String(),
            ]),
            'created_at' => now()->timestamp,
        ];

        // Keep only last 100 events, expire after 5 minutes
        $events = array_slice($events, -100);
        Cache::put($key, $events, 300);
    }

    /**
     * Get current SSE connection status info.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $realtime = ModuleRegistry::get('realtime');

        return response()->json([
            'module' => 'realtime',
            'enabled' => $realtime['runtime_enabled'] ?? false,
            'user_id' => $user?->id,
            'pending_events' => count(Cache::get("sse_events:{$user?->id}", [])),
        ]);
    }

    /**
     * Poll the cache-based event queue for new events since last counter.
     */
    private function pollBookingEvents(int $userId, int $lastEventId): array
    {
        $key = "sse_events:{$userId}";
        $events = Cache::get($key, []);

        if (empty($events)) {
            return [];
        }

        // Return events added after lastEventId (simple counter-based)
        $newEvents = array_slice($events, $lastEventId);

        // Clean up delivered events
        if (! empty($newEvents)) {
            Cache::put($key, array_slice($events, count($events)), 300);
        }

        return $newEvents;
    }

    /**
     * Check for occupancy changes across all lots.
     */
    private function checkOccupancyChanges(array &$lastOccupancy): array
    {
        $events = [];

        try {
            $lots = ParkingLot::select('id', 'name', 'total_slots', 'available_slots')
                ->where('status', 'open')
                ->get();

            foreach ($lots as $lot) {
                $currentKey = "{$lot->id}:{$lot->available_slots}";
                $lastKey = $lastOccupancy[$lot->id] ?? null;

                if ($lastKey !== null && $lastKey !== $currentKey) {
                    $events[] = [
                        'lot_id' => (string) $lot->id,
                        'lot_name' => $lot->name,
                        'available' => $lot->available_slots,
                        'total' => $lot->total_slots,
                        'timestamp' => now()->toIso8601String(),
                    ];
                }

                $lastOccupancy[$lot->id] = $currentKey;
            }
        } catch (\Throwable) {
            // Silently skip on DB errors during streaming
        }

        return $events;
    }

    /**
     * Format and send an SSE event to the client.
     */
    private function sendEvent(string $event, array $data, ?string $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data)."\n\n";
    }
}
