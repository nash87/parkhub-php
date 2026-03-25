<?php

namespace Tests\Unit\Mail;

use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createBookingAndUser(): array
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $lot = ParkingLot::create(['name' => 'Main Lot', 'total_slots' => 10]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'B3', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Main Lot',
            'slot_number' => 'B3',
            'vehicle_plate' => 'AB-CD-1234',
            'start_time' => '2026-06-15 08:00:00',
            'end_time' => '2026-06-15 17:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        return [$booking, $user];
    }

    public function test_envelope_has_correct_subject(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingConfirmation($booking, $user);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Buchungsbestätigung', $envelope->subject);
    }

    public function test_content_includes_user_name(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingConfirmation($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString('Test User', $content->htmlString);
    }

    public function test_content_includes_lot_name(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingConfirmation($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString('Main Lot', $content->htmlString);
    }

    public function test_content_includes_slot_number(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingConfirmation($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString('B3', $content->htmlString);
    }

    public function test_content_includes_booking_id(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingConfirmation($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString($booking->id, $content->htmlString);
    }

    public function test_content_is_valid_html(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingConfirmation($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString('<!DOCTYPE html>', $content->htmlString);
    }
}
