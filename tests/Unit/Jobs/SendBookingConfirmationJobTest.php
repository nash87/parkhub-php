<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendBookingConfirmationJob;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendBookingConfirmationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_confirmation_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        (new SendBookingConfirmationJob($booking->id, $user->id))->handle();

        Mail::assertQueued(\App\Mail\BookingConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_skips_when_booking_not_found(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        (new SendBookingConfirmationJob('nonexistent-id', $user->id))->handle();

        Mail::assertNothingQueued();
    }

    public function test_skips_when_user_not_found(): void
    {
        Mail::fake();

        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);
        $user = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        (new SendBookingConfirmationJob($booking->id, 'nonexistent-id'))->handle();

        Mail::assertNothingQueued();
    }

    public function test_skips_when_user_has_empty_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => '']);
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        (new SendBookingConfirmationJob($booking->id, $user->id))->handle();

        Mail::assertNothingQueued();
    }
}
