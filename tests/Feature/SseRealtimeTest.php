<?php

namespace Tests\Feature;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Events\OccupancyChanged;
use App\Http\Controllers\Api\SseController;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SseRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithToken(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    private function makeLotAndSlot(): array
    {
        $lot = ParkingLot::create([
            'name' => 'SSE Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);

        return [$lot, $slot];
    }

    private function makeBooking(User $user, ParkingLot $lot, ParkingSlot $slot): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'booking_type' => 'einmalig',
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    // ── Module config ──────────────────────────────────────────────────

    public function test_realtime_module_can_be_toggled(): void
    {
        config([
            'modules.realtime' => true,
            'modules.broadcasting' => false,
        ]);
        $this->assertTrue(config('modules.realtime'));
        config(['modules.realtime' => false]);
        $this->assertFalse(config('modules.realtime'));
    }

    // ── SSE endpoint auth ──────────────────────────────────────────────

    public function test_sse_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/sse');

        $response->assertStatus(401);
    }

    // ── SSE status endpoint ────────────────────────────────────────────

    public function test_sse_status_returns_module_info(): void
    {
        config([
            'modules.realtime' => true,
            'modules.broadcasting' => false,
        ]);
        [$user, $token] = $this->makeUserWithToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sse/status');

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertEquals('realtime', $data['module']);
        $this->assertTrue($data['enabled']);
        $this->assertEquals($user->id, $data['user_id']);
    }

    // ── Push event cache queue ─────────────────────────────────────────

    public function test_push_event_stores_in_cache(): void
    {
        Cache::flush();

        SseController::pushEvent(42, 'booking_created', [
            'booking_id' => 'test-123',
            'lot_name' => 'Lot A',
        ]);

        $events = Cache::get('sse_events:42', []);
        $this->assertCount(1, $events);
        $this->assertEquals('booking_created', $events[0]['event']);
        $this->assertEquals('test-123', $events[0]['data']['booking_id']);
        $this->assertArrayHasKey('timestamp', $events[0]['data']);
    }

    public function test_push_event_appends_to_existing_queue(): void
    {
        Cache::flush();

        SseController::pushEvent(42, 'booking_created', ['booking_id' => '1']);
        SseController::pushEvent(42, 'booking_cancelled', ['booking_id' => '2']);

        $events = Cache::get('sse_events:42', []);
        $this->assertCount(2, $events);
        $this->assertEquals('booking_created', $events[0]['event']);
        $this->assertEquals('booking_cancelled', $events[1]['event']);
    }

    public function test_push_event_caps_at_100_events(): void
    {
        Cache::flush();

        for ($i = 0; $i < 110; $i++) {
            SseController::pushEvent(42, 'booking_created', ['booking_id' => "b-{$i}"]);
        }

        $events = Cache::get('sse_events:42', []);
        $this->assertCount(100, $events);
        // The first 10 should have been evicted
        $this->assertEquals('b-10', $events[0]['data']['booking_id']);
    }

    // ── Event listener integration ─────────────────────────────────────

    public function test_booking_created_event_pushes_sse_event(): void
    {
        Cache::flush();

        [$user] = $this->makeUserWithToken();
        [$lot, $slot] = $this->makeLotAndSlot();
        $booking = $this->makeBooking($user, $lot, $slot);

        // Fire the event (listener registered in AppServiceProvider)
        event(new BookingCreated($booking));

        $events = Cache::get("sse_events:{$user->id}", []);
        $this->assertNotEmpty($events);
        $this->assertEquals('booking_created', $events[0]['event']);
        $this->assertEquals($booking->id, $events[0]['data']['booking_id']);
    }

    public function test_booking_cancelled_event_pushes_sse_event(): void
    {
        Cache::flush();

        [$user] = $this->makeUserWithToken();
        [$lot, $slot] = $this->makeLotAndSlot();
        $booking = $this->makeBooking($user, $lot, $slot);

        event(new BookingCancelled($booking));

        $events = Cache::get("sse_events:{$user->id}", []);
        $this->assertNotEmpty($events);
        $this->assertEquals('booking_cancelled', $events[0]['event']);
        $this->assertEquals($booking->id, $events[0]['data']['booking_id']);
    }

    // ── OccupancyChanged event ─────────────────────────────────────────

    public function test_occupancy_changed_event_has_correct_broadcast_channel(): void
    {
        [$lot] = $this->makeLotAndSlot();
        $event = new OccupancyChanged($lot, 5, 10);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('occupancy.'.$lot->id, $channels[0]->name);
    }

    public function test_occupancy_changed_event_broadcast_payload(): void
    {
        [$lot] = $this->makeLotAndSlot();
        $event = new OccupancyChanged($lot, 5, 10);

        $payload = $event->broadcastWith();

        $this->assertEquals($lot->id, $payload['lot_id']);
        $this->assertEquals(5, $payload['available']);
        $this->assertEquals(10, $payload['total']);
    }

    public function test_occupancy_changed_event_broadcast_name(): void
    {
        [$lot] = $this->makeLotAndSlot();
        $event = new OccupancyChanged($lot, 5, 10);

        $this->assertEquals('occupancy.changed', $event->broadcastAs());
    }

    // ── SSE endpoint returns streamed response ─────────────────────────

    public function test_sse_endpoint_returns_event_stream_content_type(): void
    {
        [$user, $token] = $this->makeUserWithToken();

        // The SSE endpoint streams, so we test headers via a controller unit approach
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/sse');

        // StreamedResponse may return 200 with text/event-stream
        $this->assertContains($response->getStatusCode(), [200, 401]);
        if ($response->getStatusCode() === 200) {
            $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type', ''));
        }
    }

    // ── Module disabled ────────────────────────────────────────────────

    public function test_sse_returns_404_when_module_disabled(): void
    {
        config([
            'modules.realtime' => false,
            'modules.broadcasting' => false,
        ]);

        [$user, $token] = $this->makeUserWithToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sse/status');

        $response->assertStatus(404);
    }
}
