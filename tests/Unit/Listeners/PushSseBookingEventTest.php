<?php

namespace Tests\Unit\Listeners;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Http\Controllers\Api\SseController;
use App\Listeners\PushSseBookingEvent;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PushSseBookingEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_handles_booking_created_event(): void
    {
        $booking = new Booking;
        $booking->id = 'test-booking-id';
        $booking->user_id = 'test-user-id';
        $booking->lot_name = 'Test Lot';
        $booking->slot_number = 'A1';
        $booking->start_time = '2026-06-15 08:00:00';
        $booking->end_time = '2026-06-15 17:00:00';

        $event = new BookingCreated($booking);
        $listener = new PushSseBookingEvent;

        // Should not throw
        $listener->handleCreated($event);
        $this->assertTrue(true);
    }

    public function test_handles_booking_cancelled_event(): void
    {
        $booking = new Booking;
        $booking->id = 'test-booking-id';
        $booking->user_id = 'test-user-id';
        $booking->lot_name = 'Test Lot';
        $booking->slot_number = 'A1';

        $event = new BookingCancelled($booking);
        $listener = new PushSseBookingEvent;

        // Should not throw
        $listener->handleCancelled($event);
        $this->assertTrue(true);
    }

    public function test_pushes_event_to_sse_queue(): void
    {
        $booking = new Booking;
        $booking->id = 'booking-123';
        $booking->user_id = 'user-456';
        $booking->lot_name = 'Main Parking';
        $booking->slot_number = 'B2';
        $booking->start_time = '2026-06-15 08:00:00';
        $booking->end_time = '2026-06-15 17:00:00';

        $event = new BookingCreated($booking);
        $listener = new PushSseBookingEvent;
        $listener->handleCreated($event);

        // Check that SSE event was pushed to cache
        $events = Cache::get('sse_events:user-456', []);
        $this->assertNotEmpty($events);

        $lastEvent = end($events);
        $this->assertEquals('booking_created', $lastEvent['event']);
        $this->assertEquals('booking-123', $lastEvent['data']['booking_id']);
    }
}
