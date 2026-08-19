<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Realtime\SseEventQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The realtime event queue.
 *
 * Two defects lived here, both invisible because the queue logic was
 * welded inside a `while (true)` streaming loop that no test can drive:
 *
 *  - the reader was typed `int $userId` while user ids are UUID strings.
 *    Under `strict_types=1` the first poll threw a `TypeError`, so no
 *    booking event was ever delivered. The writer had already been widened
 *    to `int|string` for the UUID migration; the reader was not.
 *  - the cleanup wrote `array_slice($events, count($events))`, which is
 *    always `[]`. Every poll wiped the whole queue while a monotonically
 *    increasing counter was used as an array offset.
 */
class SseEventQueueTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '9f1b0c62-6d3a-4f5e-9a1b-0c626d3a4f5e';

    public function test_a_pushed_event_can_be_read_back_for_a_uuid_user(): void
    {
        SseEventQueue::push(self::UUID, 'booking_created', ['booking_id' => 'b-1']);

        $events = SseEventQueue::pull(self::UUID, 0);

        $this->assertCount(1, $events);
        $this->assertSame('booking_created', $events[0]['event']);
        $this->assertSame('b-1', $events[0]['data']['booking_id']);
    }

    public function test_integer_user_ids_still_work(): void
    {
        SseEventQueue::push(42, 'occupancy_changed', ['free' => 3]);

        $this->assertCount(1, SseEventQueue::pull(42, 0));
    }

    public function test_a_cursor_returns_only_events_after_it(): void
    {
        SseEventQueue::push(self::UUID, 'first', []);
        SseEventQueue::push(self::UUID, 'second', []);
        SseEventQueue::push(self::UUID, 'third', []);

        $events = SseEventQueue::pull(self::UUID, 2);

        $this->assertCount(1, $events);
        $this->assertSame('third', $events[0]['event']);
    }

    /**
     * Reading must not destroy what has not been read. The old cleanup
     * emptied the queue on every poll, so an event pushed between two
     * polls could be dropped depending on where the counter had got to.
     */
    public function test_reading_does_not_discard_undelivered_events(): void
    {
        SseEventQueue::push(self::UUID, 'first', []);
        SseEventQueue::pull(self::UUID, 0);

        SseEventQueue::push(self::UUID, 'second', []);
        $events = SseEventQueue::pull(self::UUID, 1);

        $this->assertCount(1, $events);
        $this->assertSame('second', $events[0]['event'], 'an event pushed after the first poll was lost');
    }

    public function test_events_are_isolated_per_user(): void
    {
        SseEventQueue::push(self::UUID, 'mine', []);
        SseEventQueue::push('11111111-2222-3333-4444-555555555555', 'theirs', []);

        $events = SseEventQueue::pull(self::UUID, 0);

        $this->assertCount(1, $events);
        $this->assertSame('mine', $events[0]['event']);
    }

    public function test_the_queue_is_bounded(): void
    {
        for ($i = 0; $i < 120; $i++) {
            SseEventQueue::push(self::UUID, "e{$i}", []);
        }

        $this->assertLessThanOrEqual(100, count(SseEventQueue::pull(self::UUID, 0)));
    }

    public function test_an_empty_queue_reads_as_empty(): void
    {
        $this->assertSame([], SseEventQueue::pull(self::UUID, 0));
    }
}
