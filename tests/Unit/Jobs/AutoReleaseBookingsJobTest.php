<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AutoReleaseBookingsJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutoReleaseBookingsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_nothing_when_auto_release_disabled(): void
    {
        Setting::set('auto_release_enabled', 'false');

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->addHours(2),
            'status' => 'confirmed',
        ]);

        (new AutoReleaseBookingsJob)->handle();

        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'status' => 'confirmed']);
    }

    public function test_cancels_stale_bookings_without_checkin(): void
    {
        Setting::set('auto_release_enabled', 'true');
        Setting::set('auto_release_timeout', '30');
        Mail::fake();

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subMinutes(60),
            'end_time' => now()->addHours(2),
            'status' => 'confirmed',
        ]);

        (new AutoReleaseBookingsJob)->handle();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_does_not_cancel_checked_in_bookings(): void
    {
        Setting::set('auto_release_enabled', 'true');
        Setting::set('auto_release_timeout', '30');

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subMinutes(60),
            'end_time' => now()->addHours(2),
            'status' => 'active',
            'checked_in_at' => now()->subMinutes(55),
        ]);

        (new AutoReleaseBookingsJob)->handle();

        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_does_not_cancel_future_bookings(): void
    {
        Setting::set('auto_release_enabled', 'true');
        Setting::set('auto_release_timeout', '30');

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'status' => 'confirmed',
        ]);

        (new AutoReleaseBookingsJob)->handle();

        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'status' => 'confirmed']);
    }
}
