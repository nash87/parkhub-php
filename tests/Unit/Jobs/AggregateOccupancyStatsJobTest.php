<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AggregateOccupancyStatsJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AggregateOccupancyStatsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_and_caches_stats(): void
    {
        $date = now()->subDay()->toDateString();

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 10]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => $date.' 08:00:00',
            'end_time' => $date.' 17:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        (new AggregateOccupancyStatsJob($date))->handle();

        $stats = Cache::get("occupancy_stats:{$date}");
        $this->assertNotNull($stats);
        $this->assertEquals($date, $stats['date']);
        $this->assertEquals(1, $stats['total_bookings']);
        $this->assertEquals(1, $stats['unique_users']);
        $this->assertGreaterThanOrEqual(1, $stats['peak_occupancy']);
    }

    public function test_excludes_cancelled_bookings(): void
    {
        $date = now()->subDay()->toDateString();

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 10]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => $date.' 08:00:00',
            'end_time' => $date.' 17:00:00',
            'status' => Booking::STATUS_CANCELLED,
        ]);

        (new AggregateOccupancyStatsJob($date))->handle();

        $stats = Cache::get("occupancy_stats:{$date}");
        $this->assertEquals(0, $stats['total_bookings']);
    }

    public function test_handles_no_bookings(): void
    {
        $date = now()->subDay()->toDateString();

        (new AggregateOccupancyStatsJob($date))->handle();

        $stats = Cache::get("occupancy_stats:{$date}");
        $this->assertNotNull($stats);
        $this->assertEquals(0, $stats['total_bookings']);
        $this->assertEquals(0, $stats['unique_users']);
    }

    public function test_stores_latest_date_pointer(): void
    {
        $date = now()->subDay()->toDateString();

        (new AggregateOccupancyStatsJob($date))->handle();

        $this->assertEquals($date, Cache::get('occupancy_stats:latest_date'));
    }
}
