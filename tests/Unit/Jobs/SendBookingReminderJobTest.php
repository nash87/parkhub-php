<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendBookingReminderJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendBookingReminderJobTest extends TestCase
{
    use RefreshDatabase;

    private function createBookingStartingIn(int $minutes, User $user): Booking
    {
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        return Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addMinutes($minutes),
            'end_time' => now()->addMinutes($minutes + 240),
            'status' => 'confirmed',
        ]);
    }

    public function test_sends_reminders_for_bookings_starting_within_window(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->createBookingStartingIn(30, $user);

        (new SendBookingReminderJob(60))->handle();

        Mail::assertSent(\App\Mail\BookingReminderMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_does_not_send_reminders_for_bookings_outside_window(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->createBookingStartingIn(120, $user);

        (new SendBookingReminderJob(60))->handle();

        Mail::assertNothingSent();
    }

    public function test_does_not_send_reminders_for_checked_in_bookings(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addMinutes(30),
            'end_time' => now()->addMinutes(270),
            'status' => 'confirmed',
            'checked_in_at' => now(),
        ]);

        (new SendBookingReminderJob(60))->handle();

        Mail::assertNothingSent();
    }

    public function test_does_not_send_reminders_for_cancelled_bookings(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addMinutes(30),
            'end_time' => now()->addMinutes(270),
            'status' => 'cancelled',
        ]);

        (new SendBookingReminderJob(60))->handle();

        Mail::assertNothingSent();
    }
}
